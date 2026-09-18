<?php

namespace App\Http\Controllers;

use App\Http\Resources\CursoResumenResource;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Profesor;
use App\Notifications\GenericNotification;
use App\Rules\DiaHabil;
use App\Services\CursoEstadoService;
use Illuminate\Http\Request;
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
            'estudiantes' => Estudiante::whereNotNull('curso_id')->count(),
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

    public function show(string $id)
    {
        $curso = Curso::with('instructor.user', 'estudiantes.user', 'temario', 'sesiones')->findOrFail($id);

        return response()->json($curso);
    }

    public function update(Request $request, string $id)
    {
        $curso = Curso::findOrFail($id);

        $data = $request->validate([
            'profesor_id' => ['sometimes', Rule::exists('profesores', 'id')],
            'nombre' => 'sometimes|string|max:255',
            'limite_cupo' => 'sometimes|integer|min:1',
            'minimo_estudiantes' => 'nullable|integer|min:1',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
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
