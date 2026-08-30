<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Estudiante;
use App\Models\Sesion;
use App\Models\User;
use App\Notifications\SolicitudCursoProcesada;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EstudianteController extends Controller
{
    public function index(Request $request)
    {
        $query = Estudiante::with('user', 'curso');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('cedula', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('curso_id')) {
            $query->where('curso_id', $request->curso_id);
        }

        if ($request->filled('estado_pago')) {
            $query->where('estado_pago', $request->estado_pago);
        }

        return response()->json($query->paginate($this->registrosPorPagina($request)));
    }

    public function store(Request $request)
    {
        $request->validate([
            'primer_nombre' => ['required', 'string', 'max:100', self::REGEX_NOMBRES],
            'segundo_nombre' => ['nullable', 'string', 'max:100', self::REGEX_NOMBRES],
            'primer_apellido' => ['required', 'string', 'max:100', self::REGEX_NOMBRES],
            'segundo_apellido' => ['required', 'string', 'max:100', self::REGEX_NOMBRES],
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'curso_id' => 'nullable|exists:cursos,id',
            'nacionalidad' => 'required|in:V,E',
            'cedula' => ['required', 'string', 'max:15', 'unique:estudiantes,cedula', self::REGEX_CEDULA],
            'telefono' => ['nullable', 'string', 'max:20', self::REGEX_NUMERICO],
            'municipio' => ['required', 'string', 'max:255'],
            'direccion' => ['required', 'string', 'max:255', self::REGEX_DIRECCION],
            'fecha_nacimiento' => 'nullable|date',
            'genero' => 'nullable|in:masculino,femenino,otro',
            'fecha_inscripcion' => 'required|date',
            'estado' => 'in:activo,inactivo,graduado',
        ], $this->mensajesTipoDato());

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

    public function showMe()
    {
        $estudiante = Estudiante::with('user', 'curso.instructor.user')
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json($estudiante);
    }

    public function miCurso()
    {
        $estudiante = Estudiante::with('curso.instructor.user', 'curso.temario', 'curso.sesiones')
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if (! $estudiante->curso_id || ! $estudiante->curso) {
            return response()->json([
                'message' => 'No estás inscrito en ningún curso.',
            ], 404);
        }

        $curso = $estudiante->curso;
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
                'limite_cupo' => $curso->limite_cupo,
                'cupos_restantes' => $curso->cupos_restantes,
                'whatsapp_url' => $curso->whatsapp_url,
                'instructor' => $profesor ? [
                    'id' => $profesor->id,
                    'nombre' => $profesor->user?->name,
                    'foto' => $profesor->foto,
                    'especialidad' => $profesor->especialidad,
                    'titulo' => $profesor->titulo,
                    'departamento' => $profesor->departamento,
                ] : null,
                'temario' => $curso->temario->map(fn ($t) => [
                    'id' => $t->id,
                    'titulo' => $t->titulo,
                    'descripcion' => $t->descripcion,
                    'orden' => $t->orden,
                ]),
                'sesiones' => $curso->sesiones->map(fn ($s) => [
                    'id' => $s->id,
                    'titulo' => $s->titulo,
                    'descripcion' => $s->descripcion,
                    'fecha' => $s->fecha,
                    'hora_inicio' => $s->hora_inicio,
                    'hora_fin' => $s->hora_fin,
                    'estado' => $s->estado,
                ]),
            ],
            'estado_pago' => $estudiante->estado_pago,
            'estado_aprobacion_curso' => $estudiante->estado_aprobacion_curso,
        ]);
    }

    public function updateMe(Request $request)
    {
        $estudiante = Estudiante::where('user_id', Auth::id())->firstOrFail();
        $id = $estudiante->id;

        $data = $request->validate([
            'nacionalidad' => ['sometimes', 'in:V,E'],
            'telefono' => ['nullable', 'string', 'max:20', self::REGEX_NUMERICO],
            'municipio' => 'nullable|string|max:255',
            'fecha_nacimiento' => 'nullable|date',
            'genero' => 'nullable|in:masculino,femenino,otro',
        ], $this->mensajesTipoDato());

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
                ]
            );
        } catch (\Throwable $e) {
            \Log::error('Error subiendo foto de estudiante: '.$e->getMessage());

            return response()->json([
                'message' => 'No se pudo subir la imagen. Inténtalo de nuevo.',
            ], 500);
        }

        $estudiante->update(['foto' => $upload['secure_url']]);
        $estudiante->refresh();

        return response()->json($estudiante->load('user', 'curso'));
    }

    public function show(string $id)
    {
        $estudiante = Estudiante::with('user', 'curso')->findOrFail($id);

        return response()->json($estudiante);
    }

    public function update(Request $request, string $id)
    {
        $estudiante = Estudiante::findOrFail($id);

        $data = $request->validate([
            'curso_id' => 'nullable|exists:cursos,id',
            'nombre' => ['sometimes', 'string', 'max:255', self::REGEX_ALFABETICO],
            'primer_nombre' => ['sometimes', 'string', 'max:100', self::REGEX_NOMBRES],
            'segundo_nombre' => ['nullable', 'string', 'max:100', self::REGEX_NOMBRES],
            'primer_apellido' => ['sometimes', 'string', 'max:100', self::REGEX_NOMBRES],
            'segundo_apellido' => ['sometimes', 'string', 'max:100', self::REGEX_NOMBRES],
            'nacionalidad' => ['sometimes', 'in:V,E'],
            'cedula' => ['sometimes', 'string', 'max:15', 'unique:estudiantes,cedula,'.$id, self::REGEX_CEDULA],
            'telefono' => ['nullable', 'string', 'max:20', self::REGEX_NUMERICO],
            'municipio' => ['required', 'string', 'max:255'],
            'direccion' => ['required', 'string', 'max:255', self::REGEX_DIRECCION],
            'fecha_nacimiento' => 'nullable|date',
            'genero' => 'nullable|in:masculino,femenino,otro',
            'fecha_inscripcion' => 'sometimes|date',
            'estado' => 'in:activo,inactivo,graduado',
        ], $this->mensajesTipoDato());

        $estudiante->load('user');

        $camposUsuario = array_intersect_key($data, array_flip([
            'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido',
        ]));

        if (! empty($camposUsuario) && $estudiante->user) {
            $estudiante->user->update($camposUsuario);
            $estudiante->user->refresh();
            $data['nombre'] = $estudiante->user->name;
        }

        $estudiante->update(array_diff_key($data, array_flip([
            'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido',
        ])));

        return response()->json($estudiante->load('user', 'curso'));
    }

    public function destroy(string $id)
    {
        Estudiante::findOrFail($id)->delete();

        return response()->json(['message' => 'Estudiante eliminado correctamente.']);
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

        if ($estudiante->wasChanged('estado_pago') && in_array($estudiante->estado_pago, ['aprobado', 'reprobado'], true)) {
            $this->notifySolicitudCursoSiHayCurso($estudiante, SolicitudCursoProcesada::TIPO_APROBACION_PAGO);
        }

        return response()->json($estudiante->load('user', 'curso'));
    }

    /**
     * Profesor: aprueba o rechaza la finalización del curso (certificado).
     */
    public function updateAprobacionCurso(Request $request, string $id)
    {
        $estudiante = Estudiante::with('user', 'curso.instructor')->findOrFail($id);

        $curso = $estudiante->curso;
        if (! $curso || ! $curso->instructor || (int) $curso->instructor->user_id !== (int) Auth::id()) {
            return response()->json(['message' => 'No autorizado para este curso.'], 403);
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
                return response()->json([
                    'message' => "No se puede aprobar: el estudiante no cumplió con toda la asistencia (faltó a {$faltas} de {$sesionesRealizadas->count()} ".
                        ($sesionesRealizadas->count() === 1 ? 'sesión realizada' : 'sesiones realizadas').').',
                ], 422);
            }
        }

        $estudiante->update($data);

        if ($estudiante->wasChanged('estado_aprobacion_curso') && in_array($estudiante->estado_aprobacion_curso, ['aprobado', 'reprobado'], true)) {
            $this->notifySolicitudCursoSiHayCurso($estudiante, SolicitudCursoProcesada::TIPO_APROBACION_CURSO);
        }

        return response()->json($estudiante->load('user', 'curso'));
    }

    private function notifySolicitudCursoSiHayCurso(Estudiante $estudiante, string $tipo): void
    {
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

        $estudiante->user->notify(new SolicitudCursoProcesada(
            nombreCurso: $curso->nombre,
            estado: $estadoNotif,
            cursoId: (int) $curso->id,
            tipo: $tipo,
        ));
    }
}
