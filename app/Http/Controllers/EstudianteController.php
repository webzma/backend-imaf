<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\User;
use App\Notifications\SolicitudCursoProcesada;
use App\Notifications\SolicitudPagoRecibida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EstudianteController extends Controller
{
    public function index()
    {
        return response()->json(Estudiante::with('user', 'curso')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'curso_id' => 'nullable|exists:cursos,id',
            'cedula' => 'required|string|unique:estudiantes,cedula',
            'telefono' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'genero' => 'nullable|in:masculino,femenino,otro',
            'fecha_inscripcion' => 'required|date',
            'estado' => 'in:activo,inactivo,graduado',
        ]);

        $estudiante = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'estudiante',
            ]);

            return Estudiante::create([
                'user_id' => $user->id,
                'curso_id' => $request->curso_id,
                'nombre' => $request->name,
                'cedula' => $request->cedula,
                'telefono' => $request->telefono,
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
        $estudiante = Estudiante::with('user', 'curso')
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json($estudiante);
    }

    public function updateMe(Request $request)
    {
        $estudiante = Estudiante::where('user_id', Auth::id())->firstOrFail();
        $id = $estudiante->id;

        $data = $request->validate([
            'telefono' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'genero' => 'nullable|in:masculino,femenino,otro',
        ]);

        $estudiante->update($data);

        return response()->json($estudiante->load('user', 'curso'));
    }

    /**
     * Estudiante: envía solicitud de revisión de pago; notifica a todos los administradores.
     */
    public function solicitarPagoCurso(string $cursoId)
    {
        $estudiante = Estudiante::with(['user', 'curso'])
            ->where('user_id', Auth::id())
            ->firstOrFail();

        if ((int) $estudiante->curso_id !== (int) $cursoId) {
            return response()->json([
                'message' => 'No estás inscrito en este curso o el curso no coincide.',
            ], 422);
        }

        if ($estudiante->estado_pago === 'aprobado') {
            return response()->json([
                'message' => 'Tu pago para este curso ya está aprobado.',
            ], 422);
        }

        $curso = $estudiante->curso;
        if (! $curso || ! $estudiante->user) {
            return response()->json(['message' => 'Datos del curso incompletos.'], 422);
        }

        $notification = new SolicitudPagoRecibida(
            nombreEstudiante: $estudiante->user->name,
            nombreCurso: $curso->nombre,
            cursoId: (int) $curso->id,
            estudianteId: (int) $estudiante->id,
        );

        User::where('role', 'admin')->get()->each(fn (User $admin) => $admin->notify($notification));

        return response()->json([
            'message' => 'Solicitud enviada. Un administrador revisará tu pago.',
        ], 202);
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
            'nombre' => 'sometimes|string|max:255',
            'cedula' => 'sometimes|string|unique:estudiantes,cedula,'.$id,
            'telefono' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'genero' => 'nullable|in:masculino,femenino,otro',
            'fecha_inscripcion' => 'sometimes|date',
            'estado' => 'in:activo,inactivo,graduado',
        ]);

        $estudiante->update($data);

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
        $estudiante = Estudiante::with('user', 'curso')->findOrFail($id);

        $curso = $estudiante->curso;
        if (! $curso || (int) $curso->profesor_id !== (int) Auth::id()) {
            return response()->json(['message' => 'No autorizado para este curso.'], 403);
        }

        $data = $request->validate([
            'estado_aprobacion_curso' => 'required|in:pendiente,aprobado,reprobado',
        ]);

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
