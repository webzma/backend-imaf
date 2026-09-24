<?php

namespace App\Http\Controllers;

use App\Http\Resources\CursoResumenResource;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Profesor;
use App\Notifications\GenericNotification;
use App\Rules\DiaHabil;
use App\Services\CursoEstadoService;
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

    public function index(Request $request)
    {
        CursoEstadoService::sincronizarConCache();

        $query = Curso::with('instructor.user', 'estudiantes.user');

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
                ? $query->whereNull('profesor_id')
                : $query->where('profesor_id', $request->profesor_id);
        }

        $this->aplicarOrden($query, $request, self::ORDEN_CURSOS, 'nombre');

        return response()->json($query->paginate($this->registrosPorPagina($request)));
    }

    /** Totales sobre la tabla completa, para las tarjetas de resumen. */
    public function resumen()
    {
        return response()->json([
            'total' => Curso::count(),
            'activos' => Curso::where('estado', 'activo')->count(),
            'inactivos' => Curso::where('estado', 'inactivo')->count(),
            'estudiantes' => Estudiante::has('cursos')->count(),
            'con_estudiantes' => Curso::has('estudiantes')->count(),
        ]);
    }

    public function indexActivos(Request $request)
    {
        CursoEstadoService::sincronizarConCache();

        return response()->json(
            Curso::with('instructor.user', 'estudiantes.user')
                ->where('estado', 'activo')
                ->latest('id')
                ->paginate($this->registrosPorPagina($request, self::MAX_PER_PAGE_CATALOGO))
        );
    }

    public function misCursos(Request $request)
    {
        $profesor = Profesor::where('user_id', $request->user()->id)->firstOrFail();

        $cursos = Curso::where('profesor_id', $profesor->id)
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

    public function store(Request $request)
    {
        $data = $request->validate([
            'profesor_id' => ['required', Rule::exists('profesores', 'id')],
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

        $curso = Curso::create($data);

        // Notificar al profesor asignado
        $profesor = Profesor::with('user')->find($curso->profesor_id);
        if ($profesor && $profesor->user) {
            $profesor->user->notify(new GenericNotification(
                'Asignación de Curso',
                "Has sido asignado como instructor del curso: {$curso->nombre}.",
                "/profesor/cursos/{$curso->id}"
            ));
        }

        return response()->json($curso->load('instructor'), 201);
    }

    public function show(Request $request, string $id)
    {
        $curso = Curso::with('instructor.user', 'temario', 'sesiones')->findOrFail($id);

        // Admin e instructor ven el listado interno completo: también a
        // quien no ha pagado, está inactivo o fue archivado. El estudiante
        // (catálogo) solo ve a los inscritos con el pago aprobado.
        $interno = $request->user()?->isAdmin() || $request->user()?->isProfesor();

        $estudiantes = $interno
            ? $curso->matriculas()->withTrashed()->with('user')->get()
            : $curso->estudiantes()->with('user')->get();

        // Los campos del estudiante describen su curso actual; en la lista
        // de este curso deben verse los de este curso.
        $estudiantes->each(fn ($e) => $e->forceFill([
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
    public function inscribirEstudiante(Request $request, string $id)
    {
        $data = $request->validate([
            'estudiante_id' => 'required|integer|exists:estudiantes,id',
        ]);

        return DB::transaction(function () use ($id, $data) {
            $curso = Curso::lockForUpdate()->findOrFail($id);
            $estudiante = Estudiante::findOrFail($data['estudiante_id']);

            $yaInscrito = $curso->estudiantes()->whereKey($estudiante->id)->exists();
            if (! $yaInscrito && $curso->estudiantes()->count() >= $curso->limite_cupo) {
                return response()->json([
                    'message' => 'El curso ya no tiene cupos disponibles.',
                ], 422);
            }

            $estudiante->update(['curso_id' => $curso->id, 'estado_pago' => 'aprobado']);
            Inscripcion::where('estudiante_id', $estudiante->id)
                ->where('curso_id', $curso->id)
                ->update(['estado_pago' => 'aprobado']);

            return response()->json($this->estudianteEnCurso($curso, $estudiante->id), 201);
        });
    }

    /**
     * Admin: quita al estudiante del curso (borra la inscripción). Si era su
     * curso actual, vuelve al último curso pagado que le quede.
     */
    public function quitarEstudiante(string $id, string $estudianteId)
    {
        $curso = Curso::findOrFail($id);
        $estudiante = Estudiante::withTrashed()->findOrFail($estudianteId);

        Inscripcion::where('estudiante_id', $estudiante->id)
            ->where('curso_id', $curso->id)
            ->delete();

        if ((int) $estudiante->curso_id === (int) $curso->id) {
            $anterior = $estudiante->inscripciones()
                ->where('estado_pago', 'aprobado')
                ->orderByDesc('fecha_inscripcion')
                ->orderByDesc('id')
                ->first();

            $estudiante->update(['curso_id' => $anterior?->curso_id]);
        }

        return response()->json(['message' => 'Estudiante quitado del curso.']);
    }

    /** Un estudiante tal como aparece en el listado interno del curso. */
    private function estudianteEnCurso(Curso $curso, int $estudianteId): Estudiante
    {
        $e = $curso->matriculas()->withTrashed()->with('user')->whereKey($estudianteId)->firstOrFail();

        return $e->forceFill([
            'estado_pago' => $e->pivot->estado_pago,
            'estado_aprobacion_curso' => $e->pivot->estado_aprobacion_curso,
            'fecha_inscripcion' => $e->pivot->fecha_inscripcion ?? $e->fecha_inscripcion,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $curso = Curso::findOrFail($id);

        $data = $request->validate([
            'profesor_id' => ['sometimes', Rule::exists('profesores', 'id')],
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
            $profesor = Profesor::with('user')->find($curso->profesor_id);
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
            $profesor = Profesor::with('user')->find($data['profesor_id']);
            if ($profesor && $profesor->user) {
                $profesor->user->notify(new GenericNotification(
                    'Asignación de Curso',
                    "Has sido asignado como instructor del curso: {$curso->nombre}.",
                    "/profesor/cursos/{$curso->id}"
                ));
            }
        }

        return response()->json($curso->load('instructor'));
    }

    public function destroy(string $id)
    {
        Curso::findOrFail($id)->delete();

        return response()->json(['message' => 'Curso eliminado correctamente.']);
    }
}
