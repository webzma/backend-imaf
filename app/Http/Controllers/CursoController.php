<?php

namespace App\Http\Controllers;

use App\Http\Resources\CursoResumenResource;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Profesor;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Rules\DiaHabil;
use App\Services\Auditoria;
use App\Services\CursoEstadoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CursoController extends Controller
{
    /** Columnas por las que la tabla del panel puede ordenar. */
    private const ORDEN_CURSOS = [
        'nombre' => 'nombre',
        'codigo' => 'codigo',
        'fecha_inicio' => 'fecha_inicio',
        'precio' => 'precio',
        'estado' => 'estado',
    ];

    /** @phpstan-return JsonResponse */
    public function index(Request $request): JsonResponse
    {
        CursoEstadoService::sincronizarConCache();

        $query = Curso::query()->with('instructor.user', 'nivelCurso', 'estudiantes.user');

        $this->acotarAlProfesor($query, $request->user());

        if ($search = $this->terminoBusqueda($request)) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%")
                    ->orWhere('descripcion', 'like', "%{$search}%")
                    ->orWhereHas('instructor.user', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('profesor_id')) {
            $request->profesor_id === 'sin_instructor'
                // También cuenta el curso cuyo profesor fue dado de baja.
                ? $query->whereDoesntHave('instructor')
                : $query->where('profesor_id', $request->profesor_id);
        }

        $this->aplicarOrden($query, $request, self::ORDEN_CURSOS, 'nombre');

        return response()->json($query->paginate($this->registrosPorPagina($request)));
    }

    /**
     * Totales sobre la tabla completa, para las tarjetas de resumen.
     *
     * @phpstan-return JsonResponse
     */
    public function resumen(): JsonResponse
    {
        return response()->json([
            'total' => Curso::query()->count(),
            'activos' => Curso::query()->where('estado', 'activo')->count(),
            'inactivos' => Curso::query()->where('estado', 'inactivo')->count(),
            'estudiantes' => Estudiante::query()->has('cursos')->count(),
            'con_estudiantes' => Curso::query()->has('estudiantes')->count(),
        ]);
    }

    /** @phpstan-return JsonResponse */
    public function indexActivos(Request $request): JsonResponse
    {
        CursoEstadoService::sincronizarConCache();

        return response()->json(
            Curso::query()->with('instructor.user', 'nivelCurso')
                ->where('estado', 'activo')
                ->latest('id')
                ->paginate($this->registrosPorPagina($request, self::MAX_PER_PAGE_CATALOGO))
        );
    }

    /** @phpstan-return JsonResponse */
    public function misCursos(Request $request): JsonResponse
    {
        $profesor = Profesor::query()->where('user_id', $request->user()->id)->firstOrFail();

        $cursos = Curso::query()->where('profesor_id', $profesor->id)
            ->latest('id')
            ->paginate($this->registrosPorPagina($request, self::MAX_PER_PAGE_CATALOGO));

        return response()->json([
            'data' => CursoResumenResource::collection($cursos),
            'total' => $cursos->total(),
            'current_page' => $cursos->currentPage(),
            'last_page' => $cursos->lastPage(),
            'per_page' => $cursos->perPage(),
        ]);
    }

    /** @phpstan-return JsonResponse */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'profesor_id' => ['required', Rule::exists('profesores', 'id')],
            'nivel_curso_id' => ['nullable', Rule::exists('niveles_curso', 'id')],
            'nombre' => 'required|string|max:255',
            'limite_cupo' => 'required|integer|min:1',
            'minimo_estudiantes' => 'nullable|integer|min:1|lte:limite_cupo',
            'fecha_inicio' => ['nullable', 'date', new DiaHabil()],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio', new DiaHabil()],
            'descripcion' => 'nullable|string',
            'requisitos' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'whatsapp_url' => 'nullable|url|max:500',
            'estado' => 'in:activo,inactivo',
        ]);

        /** @var Curso $curso */
        $curso = Curso::query()->create($data);

        // Notificar al profesor asignado
        $profesor = Profesor::query()->with('user')->find($curso->profesor_id);
        if ($profesor && $profesor->user) {
            $profesor->user->notify(new GenericNotification(
                'Asignación de Curso',
                "Has sido asignado como instructor del curso: {$curso->nombre}.",
                "/profesor/cursos/{$curso->id}"
            ));
        }

        return response()->json($curso->load('instructor', 'nivelCurso'), 201);
    }

    /** @phpstan-return JsonResponse */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var Curso $curso */
        $curso = Curso::query()->with('instructor.user', 'nivelCurso', 'temario', 'sesiones')->findOrFail($id);

        $usuario = $request->user();
        $esProfesor = $usuario?->isProfesor() ?? false;
        $interno = $usuario?->isAdmin() || $esProfesor;
        $estudiante = $interno ? null : $usuario?->estudiante;
        $profesorId = $usuario?->profesor?->id;

        if ($esProfesor && (int) $curso->profesor_id !== (int) $profesorId) {
            abort(404);
        }

        if (! $interno && (! $estudiante || ! $curso->estudiantes()->whereKey($estudiante->id)->exists())) {
            abort(404);
        }

        // Admin e instructor ven el listado interno completo: también a
        // quien no ha pagado, está inactivo o fue archivado. El estudiante
        // (catálogo) solo ve su propia inscripción con el pago aprobado.
        $estudiantes = $interno
            ? $this->matriculas($curso)->with('user')->get()
            : $curso->estudiantes()->with('user')->whereKey($estudiante->id)->get();

        // Los campos del estudiante describen su curso actual; en la lista
        // de este curso deben verse los de este curso.
        $estudiantes->each(fn (Estudiante $e) => $e->forceFill([
            'estado_pago' => $e->pivot->estado_pago,
            'estado_aprobacion_curso' => $e->pivot->estado_aprobacion_curso,
            'fecha_inscripcion' => $e->pivot->fecha_inscripcion ?? $e->fecha_inscripcion,
        ]));

        $curso->setRelation('estudiantes', $estudiantes);

        return response()->json($curso);
    }

    /**
     * Admin: inscribe a un estudiante en el curso (queda con el pago
     * aprobado y el curso pasa a ser el actual). También sirve para dar por
     * pagada una inscripción pendiente o rechazada.
     */
    /** @phpstan-return JsonResponse */
    public function inscribirEstudiante(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'estudiante_id' => 'required|integer|exists:estudiantes,id',
        ]);

        /** @var JsonResponse $respuesta */
        $respuesta = DB::transaction(function () use ($id, $data) {
            /** @var Curso $curso */
            $curso = Curso::query()->lockForUpdate()->findOrFail($id);
            /** @var Estudiante $estudiante */
            $estudiante = Estudiante::query()->findOrFail($data['estudiante_id']);

            $yaInscrito = $curso->estudiantes()->whereKey($estudiante->id)->exists();
            if (! $yaInscrito && $curso->estudiantes()->count() >= $curso->limite_cupo) {
                return response()->json([
                    'message' => 'El curso ya no tiene cupos disponibles.',
                ], 422);
            }

            $estudiante->asignarCursoActual($curso->id);

            Auditoria::registrar('inscripcion.asignada', "Inscripción manual de {$estudiante->nombre} en «{$curso->nombre}»", [
                'entidad' => $curso,
                'metadatos' => ['estudiante_id' => $estudiante->id],
            ]);

            return response()->json($this->estudianteEnCurso($curso, $estudiante->id), 201);
        });

        return $respuesta;
    }

    /**
     * Admin: quita al estudiante del curso. Si era su
     * curso actual, vuelve al último curso pagado que le quede.
     */
    /** @phpstan-return JsonResponse */
    public function quitarEstudiante(string $id, string $estudianteId): JsonResponse
    {
        /** @var Curso $curso */
        $curso = Curso::query()->findOrFail($id);
        /** @var Estudiante $estudiante */
        $estudiante = Estudiante::query()->withoutGlobalScope(SoftDeletingScope::class)->findOrFail($estudianteId);

        $estudiante->quitarDeCurso($curso->id);

        Auditoria::registrar('inscripcion.eliminada', "Se quitó a {$estudiante->nombre} del curso «{$curso->nombre}»", [
            'nivel' => 'aviso',
            'entidad' => $curso,
            'metadatos' => ['estudiante_id' => $estudiante->id],
        ]);

        return response()->json(['message' => 'Estudiante quitado del curso.']);
    }

    /**
     * @phpstan-param Builder<Curso> $query
     * @phpstan-param User|null $usuario
     *
     * @phpstan-return void
     */
    private function acotarAlProfesor(Builder $query, ?User $usuario): void
    {
        if (! $usuario || ! $usuario->isProfesor()) {
            return;
        }

        $query->where('profesor_id', $usuario->profesor?->id ?? -1);
    }

    /** @phpstan-return BelongsToMany<Estudiante, Curso> */
    private function matriculas(Curso $curso): BelongsToMany
    {
        $relacion = $curso->matriculas();

        /** @var Builder $query */
        $query = $relacion;
        $query->withoutGlobalScope(SoftDeletingScope::class);

        return $relacion;
    }

    /**
     * Un estudiante tal como aparece en el listado interno del curso.
     *
     * @phpstan-return Estudiante
     */
    private function estudianteEnCurso(Curso $curso, int $estudianteId): Estudiante
    {
        /** @var Estudiante $e */
        $e = $this->matriculas($curso)->with('user')->whereKey($estudianteId)->firstOrFail();

        return $e->forceFill([
            'estado_pago' => $e->pivot->estado_pago,
            'estado_aprobacion_curso' => $e->pivot->estado_aprobacion_curso,
            'fecha_inscripcion' => $e->pivot->fecha_inscripcion ?? $e->fecha_inscripcion,
        ]);
    }

    /** @phpstan-return JsonResponse */
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var Curso $curso */
        $curso = Curso::query()->findOrFail($id);

        $data = $request->validate([
            'profesor_id' => ['sometimes', Rule::exists('profesores', 'id')],
            'nivel_curso_id' => ['nullable', Rule::exists('niveles_curso', 'id')],
            'nombre' => 'sometimes|string|max:255',
            'limite_cupo' => 'sometimes|integer|min:1',
            'minimo_estudiantes' => 'nullable|integer|min:1',
            // Solo se exige día hábil a la fecha que cambia: un curso creado
            // antes de la regla debe poder editarse sin tocar sus fechas.
            'fecha_inicio' => ['nullable', 'date', Rule::when(
                $request->input('fecha_inicio') !== $curso->fecha_inicio?->toDateString(),
                [new DiaHabil()],
            )],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio', Rule::when(
                $request->input('fecha_fin') !== $curso->fecha_fin?->toDateString(),
                [new DiaHabil()],
            )],
            'descripcion' => 'nullable|string',
            'requisitos' => 'nullable|string',
            'precio' => 'sometimes|numeric|min:0',
            'whatsapp_url' => 'nullable|url|max:500',
            'estado' => 'in:activo,inactivo',
        ]);

        $oldProfesorId = $curso->profesor_id;
        $oldEstado = $curso->estado;
        $curso->update($data);

        // Notificar al profesor si el estado del curso cambió
        if (isset($data['estado']) && $data['estado'] !== $oldEstado) {
            $profesor = Profesor::query()->with('user')->find($curso->profesor_id);
            if ($profesor && $profesor->user) {
                $estadoTexto = $data['estado'] === 'activo' ? 'activado' : 'inactivado';
                $profesor->user->notify(new GenericNotification(
                    'Estado de Curso Actualizado',
                    "El curso '{$curso->nombre}' ha sido {$estadoTexto} por el administrador.",
                    "/profesor/cursos/{$curso->id}"
                ));
            }
        }

        // Si se cambió el profesor, notificar al nuevo profesor
        if (isset($data['profesor_id']) && (int) $data['profesor_id'] !== (int) $oldProfesorId) {
            $profesor = Profesor::query()->with('user')->find($data['profesor_id']);
            if ($profesor && $profesor->user) {
                $profesor->user->notify(new GenericNotification(
                    'Asignación de Curso',
                    "Has sido asignado como instructor del curso: {$curso->nombre}.",
                    "/profesor/cursos/{$curso->id}"
                ));
            }
        }

        return response()->json($curso->load('instructor', 'nivelCurso'));
    }

    /** @phpstan-return JsonResponse */
    public function destroy(string $id): JsonResponse
    {
        Curso::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Curso eliminado correctamente.']);
    }
}
