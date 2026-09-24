<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\Sesion;
use App\Models\User;
use App\Notifications\SolicitudCursoProcesada;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EstudianteController extends Controller
{
    /** Columnas por las que la tabla del panel puede ordenar. */
    private const ORDEN_ESTUDIANTES = [
        'nombre' => 'nombre',
        'cedula' => 'cedula',
        'fecha_inscripcion' => 'fecha_inscripcion',
        'estado' => 'estado',
    ];

    public function index(Request $request)
    {
        $query = Estudiante::with('user', 'curso');

        $this->acotarAlProfesor($query, $request->user());

        // La búsqueda cubre lo que la tabla muestra: nombre, cédula y correo.
        // Antes solo miraba `nombre` y `cedula`, así que buscar por el correo
        // que aparece bajo el nombre no devolvía nada.
        if ($search = $this->terminoBusqueda($request)) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('cedula', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"))
                    ->orWhereHas('curso', fn ($c) => $c->where('nombre', 'like', "%{$search}%")
                        ->orWhere('codigo', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        // `sin_curso` / `sin_municipio` son filtros reales, no la ausencia de
        // filtro: la UI necesita poder listar a quien no tiene curso asignado.
        if ($request->filled('curso_id')) {
            $request->curso_id === 'sin_curso'
                ? $query->whereNull('curso_id')
                : $query->whereHas('cursos', fn ($c) => $c->whereKey($request->curso_id));
        }

        if ($request->filled('municipio')) {
            $request->municipio === 'sin_municipio'
                ? $query->whereNull('municipio')
                : $query->where('municipio', $request->municipio);
        }

        if ($request->filled('estado_pago')) {
            $query->where('estado_pago', $request->estado_pago);
        }

        $this->aplicarOrden($query, $request, self::ORDEN_ESTUDIANTES, 'nombre');

        return response()->json(
            $query->paginate($this->registrosPorPagina($request)),
        );
    }

    /**
     * Totales de la lista completa, no de la página visible.
     *
     * Las tarjetas de resumen contaban sobre los 10 registros cargados, así
     * que con 300 estudiantes decían "10". Respeta los mismos filtros que
     * `index` para que el resumen describa lo que se está viendo.
     */
    public function resumen(Request $request)
    {
        $query = Estudiante::query();
        $this->acotarAlProfesor($query, $request->user());

        if ($request->filled('curso_id') && $request->curso_id !== 'sin_curso') {
            $query->whereHas('cursos', fn ($c) => $c->whereKey($request->curso_id));
        }

        return response()->json([
            'total' => (clone $query)->count(),
            'activos' => (clone $query)->where('estado', 'activo')->count(),
            'inactivos' => (clone $query)->where('estado', 'inactivo')->count(),
            'graduados' => (clone $query)->where('estado', 'graduado')->count(),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                'primer_nombre' => [
                    'required',
                    'string',
                    'max:100',
                    self::REGEX_NOMBRES,
                ],
                'segundo_nombre' => [
                    'nullable',
                    'string',
                    'max:100',
                    self::REGEX_NOMBRES,
                ],
                'primer_apellido' => [
                    'required',
                    'string',
                    'max:100',
                    self::REGEX_NOMBRES,
                ],
                'segundo_apellido' => [
                    'required',
                    'string',
                    'max:100',
                    self::REGEX_NOMBRES,
                ],
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:8',
                'curso_id' => 'nullable|exists:cursos,id',
                'nacionalidad' => 'required|in:V,E',
                'cedula' => [
                    'required',
                    'string',
                    'max:15',
                    'unique:estudiantes,cedula',
                    self::REGEX_CEDULA,
                ],
                'telefono' => [
                    'nullable',
                    'string',
                    'max:20',
                    self::REGEX_NUMERICO,
                ],
                'municipio' => ['required', 'string', 'max:255'],
                'direccion' => [
                    'required',
                    'string',
                    'max:255',
                    self::REGEX_DIRECCION,
                ],
                'fecha_nacimiento' => 'nullable|date',
                'genero' => 'nullable|in:masculino,femenino,otro',
                'fecha_inscripcion' => 'required|date',
                'estado' => 'in:activo,inactivo,graduado',
            ],
            $this->mensajesTipoDato(),
        );

        $estudiante = DB::transaction(function () use ($request) {
            $user = User::create([
                'primer_nombre' => $request->primer_nombre,
                'segundo_nombre' => $request->segundo_nombre,
                'primer_apellido' => $request->primer_apellido,
                'segundo_apellido' => $request->segundo_apellido,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'estudiante',
            ]);

            return Estudiante::create([
                'user_id' => $user->id,
                'curso_id' => $request->curso_id,
                'nombre' => $user->name,
                'nacionalidad' => $request->nacionalidad,
                'cedula' => $request->cedula,
                'telefono' => $request->telefono,
                'municipio' => $request->municipio,
                'direccion' => $request->direccion,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'genero' => $request->genero,
                'fecha_inscripcion' => $request->fecha_inscripcion,
                'estado' => $request->estado ?? 'activo',
            ]);
        });

        return response()->json($estudiante->load('user', 'curso'), 201);
    }

    /**
     * Cambia el estado de varios estudiantes de una vez.
     *
     * `update` exige el registro completo (municipio y dirección incluidos),
     * así que no servía para tocar solo el estado: graduar a una promoción
     * obligaba a abrir y reenviar quince formularios enteros.
     */
    public function estadoMasivo(Request $request)
    {
        $datos = $request->validate([
            'ids' => 'required|array|min:1|max:200',
            'ids.*' => 'integer|exists:estudiantes,id',
            'estado' => 'required|in:activo,inactivo,graduado',
        ]);

        $actualizados = Estudiante::whereIn('id', $datos['ids'])
            ->update(['estado' => $datos['estado']]);

        return response()->json(['actualizados' => $actualizados]);
    }

    public function showMe()
    {
        $estudiante = Estudiante::with('user', 'curso.instructor.user')
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json($estudiante);
    }

    /**
     * Detalle del curso actual o, con `?curso_id=`, de cualquier curso en el
     * que el estudiante esté o haya estado inscrito.
     */
    public function miCurso(Request $request)
    {
        $estudiante = Estudiante::where('user_id', Auth::id())->firstOrFail();

        $cursoId = $request->filled('curso_id')
            ? (int) $request->curso_id
            : $estudiante->curso_id;

        $curso = $cursoId
            ? $estudiante->cursos()
                ->with('instructor.user', 'temario', 'sesiones')
                ->whereKey($cursoId)
                ->first()
            : null;

        if (! $curso) {
            return response()->json(
                [
                    'message' => 'No estás inscrito en ningún curso.',
                ],
                404,
            );
        }

        $esActual = (int) $curso->id === (int) $estudiante->curso_id;
        $profesor = $curso->instructor;

        return response()->json([
            'curso' => [
                'id' => $curso->id,
                'codigo' => $curso->codigo,
                'nombre' => $curso->nombre,
                'descripcion' => $curso->descripcion,
                'requisitos' => $curso->requisitos,
                'precio' => $curso->precio,
                'fecha_inicio' => $curso->fecha_inicio,
                'fecha_fin' => $curso->fecha_fin,
                'estado' => $curso->estado,
                'modalidad' => $curso->modalidad,
                'sede' => $curso->sede,
                'limite_cupo' => $curso->limite_cupo,
                'cupos_restantes' => $curso->cupos_restantes,
                'whatsapp_url' => $curso->whatsapp_url,
                'instructor' => $profesor
                    ? [
                        'id' => $profesor->id,
                        'nombre' => $profesor->user?->name,
                        'foto' => $profesor->foto,
                        'especialidad' => $profesor->especialidad,
                        'titulo' => $profesor->titulo,
                        'departamento' => $profesor->departamento,
                    ]
                    : null,
                'temario' => $curso->temario->map(
                    fn ($t) => [
                        'id' => $t->id,
                        'titulo' => $t->titulo,
                        'descripcion' => $t->descripcion,
                        'orden' => $t->orden,
                    ],
                ),
                'sesiones' => $curso->sesiones->map(
                    fn ($s) => [
                        'id' => $s->id,
                        'titulo' => $s->titulo,
                        'descripcion' => $s->descripcion,
                        'fecha' => $s->fecha,
                        'hora_inicio' => $s->hora_inicio,
                        'hora_fin' => $s->hora_fin,
                        'estado' => $s->estado,
                    ],
                ),
            ],
            // Solo el curso actual puede tener el pago en revisión; los
            // anteriores quedaron inscritos con el pago aprobado.
            'estado_pago' => $esActual ? $estudiante->estado_pago : 'aprobado',
            'estado_aprobacion_curso' => $curso->pivot->estado_aprobacion_curso,
            'es_actual' => $esActual,
        ]);
    }

    /**
     * Todos los cursos del estudiante (actual y anteriores) y las solicitudes
     * de inscripción que siguen esperando la revisión del pago.
     */
    public function misCursos()
    {
        $estudiante = Estudiante::where('user_id', Auth::id())->firstOrFail();

        $cursos = $estudiante->cursos()
            ->with('instructor.user')
            ->orderByPivot('fecha_inscripcion', 'desc')
            ->orderByPivot('id', 'desc')
            ->get()
            ->map(fn ($curso) => [
                'id' => $curso->id,
                'codigo' => $curso->codigo,
                'nombre' => $curso->nombre,
                'estado' => $curso->estado,
                'modalidad' => $curso->modalidad,
                'fecha_inicio' => $curso->fecha_inicio?->toDateString(),
                'fecha_fin' => $curso->fecha_fin?->toDateString(),
                'instructor' => $curso->instructor?->user?->name,
                'fecha_inscripcion' => $curso->pivot->fecha_inscripcion,
                'estado_aprobacion_curso' => $curso->pivot->estado_aprobacion_curso,
                'es_actual' => (int) $curso->id === (int) $estudiante->curso_id,
            ]);

        $pendientes = Pago::with('curso:id,codigo,nombre')
            ->where('user_id', $estudiante->user_id)
            ->where('estado', 'pendiente')
            ->whereNotIn('curso_id', $cursos->pluck('id'))
            ->latest()
            ->get()
            ->map(fn ($pago) => [
                'pago_id' => $pago->id,
                'curso_id' => $pago->curso_id,
                'codigo' => $pago->curso?->codigo,
                'nombre' => $pago->curso?->nombre,
                'fecha_solicitud' => $pago->created_at?->toDateString(),
            ]);

        return response()->json([
            'cursos' => $cursos,
            'solicitudes_pendientes' => $pendientes,
        ]);
    }

    public function updateMe(Request $request)
    {
        $estudiante = Estudiante::where('user_id', Auth::id())->firstOrFail();
        $id = $estudiante->id;

        $data = $request->validate(
            [
                'nacionalidad' => ['sometimes', 'in:V,E'],
                'telefono' => [
                    'nullable',
                    'string',
                    'max:20',
                    self::REGEX_NUMERICO,
                ],
                'municipio' => 'nullable|string|max:255',
                'fecha_nacimiento' => 'nullable|date',
                'genero' => 'nullable|in:masculino,femenino,otro',
            ],
            $this->mensajesTipoDato(),
        );

        $estudiante->update($data);
        $estudiante->refresh();

        return response()->json($estudiante->load('user', 'curso'));
    }

    public function uploadFotoMe(Request $request)
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        $estudiante = Estudiante::where('user_id', Auth::id())->firstOrFail();

        try {
            $upload = Cloudinary::uploadApi()->upload(
                $request->file('foto')->getRealPath(),
                [
                    'folder' => 'imaf/perfiles',
                    'public_id' => 'estudiante_'.$estudiante->id,
                    'overwrite' => true,
                    'invalidate' => true,
                    'transformation' => [
                        'width' => 400,
                        'height' => 400,
                        'crop' => 'fill',
                        'gravity' => 'face',
                    ],
                ],
            );
        } catch (\Throwable $e) {
            \Log::error(
                'Error subiendo foto de estudiante: '.$e->getMessage(),
            );

            return response()->json(
                [
                    'message' => 'No se pudo subir la imagen. Inténtalo de nuevo.',
                ],
                500,
            );
        }

        $estudiante->update(['foto' => $upload['secure_url']]);
        $estudiante->refresh();

        return response()->json($estudiante->load('user', 'curso'));
    }

    public function show(Request $request, string $id)
    {
        $query = Estudiante::with('user', 'curso', 'cursos.instructor.user');

        $this->acotarAlProfesor($query, $request->user());

        $estudiante = $query->findOrFail($id);

        return response()->json($estudiante);
    }

    /**
     * Restringe una consulta de estudiantes a los inscritos en cursos que dicta
     * el usuario, cuando ese usuario es profesor.
     *
     * Los endpoints `GET /estudiantes` y `GET /estudiantes/{id}` son compartidos
     * por admin y profesor. Sin este filtro un instructor podría leer la cédula,
     * la dirección y el teléfono de toda la base, no solo de sus alumnos. El
     * admin conserva la vista completa.
     */
    private function acotarAlProfesor(Builder $query, ?User $usuario): void
    {
        if (! $usuario || ! $usuario->isProfesor()) {
            return;
        }

        // Todas sus inscripciones, pagadas o no: el instructor ve en su
        // listado también a quien no ha pagado.
        $query->whereHas(
            'inscripciones.curso.instructor',
            fn (Builder $q) => $q->where('user_id', $usuario->id),
        );
    }

    public function update(Request $request, string $id)
    {
        $estudiante = Estudiante::findOrFail($id);

        $data = $request->validate(
            [
                'curso_id' => 'nullable|exists:cursos,id',
                'nombre' => [
                    'sometimes',
                    'string',
                    'max:255',
                    self::REGEX_ALFABETICO,
                ],
                'primer_nombre' => [
                    'sometimes',
                    'string',
                    'max:100',
                    self::REGEX_NOMBRES,
                ],
                'segundo_nombre' => [
                    'nullable',
                    'string',
                    'max:100',
                    self::REGEX_NOMBRES,
                ],
                'primer_apellido' => [
                    'sometimes',
                    'string',
                    'max:100',
                    self::REGEX_NOMBRES,
                ],
                'segundo_apellido' => [
                    'sometimes',
                    'string',
                    'max:100',
                    self::REGEX_NOMBRES,
                ],
                'nacionalidad' => ['sometimes', 'in:V,E'],
                'cedula' => [
                    'sometimes',
                    'string',
                    'max:15',
                    'unique:estudiantes,cedula,'.$id,
                    self::REGEX_CEDULA,
                ],
                'telefono' => [
                    'nullable',
                    'string',
                    'max:20',
                    self::REGEX_NUMERICO,
                ],
                'municipio' => ['required', 'string', 'max:255'],
                'direccion' => [
                    'required',
                    'string',
                    'max:255',
                    self::REGEX_DIRECCION,
                ],
                'fecha_nacimiento' => 'nullable|date',
                'genero' => 'nullable|in:masculino,femenino,otro',
                'fecha_inscripcion' => 'sometimes|date',
                'estado' => 'in:activo,inactivo,graduado',
            ],
            $this->mensajesTipoDato(),
        );

        $estudiante->load('user');

        $camposUsuario = array_intersect_key(
            $data,
            array_flip([
                'primer_nombre',
                'segundo_nombre',
                'primer_apellido',
                'segundo_apellido',
            ]),
        );

        if (! empty($camposUsuario) && $estudiante->user) {
            $estudiante->user->update($camposUsuario);
            $estudiante->user->refresh();
            $data['nombre'] = $estudiante->user->name;
        }

        $estudiante->update(
            array_diff_key(
                $data,
                array_flip([
                    'primer_nombre',
                    'segundo_nombre',
                    'primer_apellido',
                    'segundo_apellido',
                ]),
            ),
        );

        return response()->json($estudiante->load('user', 'curso'));
    }

    public function destroy(string $id)
    {
        Estudiante::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Estudiante eliminado correctamente.',
        ]);
    }

    /**
     * Admin: aprueba o rechaza el pago (acceso al curso).
     */
    public function updateEstadoPago(Request $request, string $id)
    {
        $estudiante = Estudiante::with('user', 'curso')->findOrFail($id);

        $data = $request->validate([
            'estado_pago' => 'required|in:pendiente,aprobado,reprobado',
        ]);

        $estudiante->update($data);

        if (
            $estudiante->wasChanged('estado_pago') &&
            in_array($estudiante->estado_pago, ['aprobado', 'reprobado'], true)
        ) {
            $this->notifySolicitudCursoSiHayCurso(
                $estudiante,
                SolicitudCursoProcesada::TIPO_APROBACION_PAGO,
            );
        }

        return response()->json($estudiante->load('user', 'curso'));
    }

    /**
     * Profesor: aprueba o rechaza la finalización del curso (certificado).
     */
    public function updateAprobacionCurso(Request $request, string $id)
    {
        $estudiante = Estudiante::with('user')->findOrFail($id);

        // Sin `curso_id` se asume el curso actual del estudiante.
        $cursoId = $request->filled('curso_id')
            ? (int) $request->curso_id
            : $estudiante->curso_id;

        $curso = $cursoId
            ? $estudiante->cursos()->with('instructor')->whereKey($cursoId)->first()
            : null;
        if (
            ! $curso ||
            ! $curso->instructor ||
            (int) $curso->instructor->user_id !== (int) Auth::id()
        ) {
            return response()->json(
                ['message' => 'No autorizado para este curso.'],
                403,
            );
        }

        $data = $request->validate([
            'estado_aprobacion_curso' => 'required|in:pendiente,aprobado,reprobado',
        ]);

        // Solo se puede aprobar si asistió a todas las sesiones realizadas del curso
        if ($data['estado_aprobacion_curso'] === 'aprobado') {
            $sesionesRealizadas = Sesion::where('curso_id', $curso->id)
                ->where('estado', 'realizada')
                ->pluck('id');

            $presentes = Asistencia::where('estudiante_id', $estudiante->id)
                ->whereIn('sesion_id', $sesionesRealizadas)
                ->where('presente', true)
                ->count();

            $faltas = $sesionesRealizadas->count() - $presentes;

            if ($faltas > 0) {
                return response()->json(
                    [
                        'message' => "No se puede aprobar: el estudiante no cumplió con toda la asistencia (faltó a {$faltas} de {$sesionesRealizadas->count()} ".
                            ($sesionesRealizadas->count() === 1
                                ? 'sesión realizada'
                                : 'sesiones realizadas').
                            ').',
                    ],
                    422,
                );
            }
        }

        $nuevoEstado = $data['estado_aprobacion_curso'];
        $cambio = $curso->pivot->estado_aprobacion_curso !== $nuevoEstado;

        Inscripcion::where('estudiante_id', $estudiante->id)
            ->where('curso_id', $curso->id)
            ->update(['estado_aprobacion_curso' => $nuevoEstado]);

        if ((int) $curso->id === (int) $estudiante->curso_id) {
            $estudiante->update($data);
        }

        if ($cambio && in_array($nuevoEstado, ['aprobado', 'reprobado'], true)) {
            $estudiante->user?->notify(
                new SolicitudCursoProcesada(
                    nombreCurso: $curso->nombre,
                    estado: $nuevoEstado,
                    cursoId: (int) $curso->id,
                    tipo: SolicitudCursoProcesada::TIPO_APROBACION_CURSO,
                ),
            );
        }

        $estudiante->refresh();
        $estudiante->setAttribute('estado_aprobacion_curso', $nuevoEstado);

        return response()->json($estudiante->load('user', 'curso'));
    }

    private function notifySolicitudCursoSiHayCurso(
        Estudiante $estudiante,
        string $tipo,
    ): void {
        $curso = $estudiante->curso;
        if (! $curso || ! $estudiante->user) {
            return;
        }

        $estadoNotif = match ($tipo) {
            SolicitudCursoProcesada::TIPO_APROBACION_PAGO => $estudiante->estado_pago,
            SolicitudCursoProcesada::TIPO_APROBACION_CURSO => $estudiante->estado_aprobacion_curso,
            default => 'pendiente',
        };

        if (! in_array($estadoNotif, ['aprobado', 'reprobado'], true)) {
            return;
        }

        $estudiante->user->notify(
            new SolicitudCursoProcesada(
                nombreCurso: $curso->nombre,
                estado: $estadoNotif,
                cursoId: (int) $curso->id,
                tipo: $tipo,
            ),
        );
    }
}
