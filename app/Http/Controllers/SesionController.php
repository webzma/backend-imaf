<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Profesor;
use App\Models\Sesion;
use App\Notifications\GenericNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SesionController extends Controller
{
    /**
     * Lanza un error 422 si el instructor del curso ya tiene otra clase
     * (no cancelada) que se solape en fecha y hora.
     */
    private function validarConflictoInstructor(
        int|string $cursoId,
        string $fecha,
        ?string $horaInicio,
        ?string $horaFin,
        ?int $ignorarSesionId = null,
    ): void {
        $curso = Curso::find($cursoId);
        if (! $curso || ! $curso->profesor_id) {
            return;
        }

        $candidatas = Sesion::whereDate('fecha', $fecha)
            ->where('estado', '!=', 'cancelada')
            ->whereHas('curso', fn ($q) => $q->where('profesor_id', $curso->profesor_id))
            ->when($ignorarSesionId, fn ($q) => $q->where('id', '!=', $ignorarSesionId))
            ->get();

        [$inicio, $fin] = $this->rangoEnMinutos($horaInicio, $horaFin);

        foreach ($candidatas as $sesion) {
            [$sInicio, $sFin] = $this->rangoEnMinutos($sesion->hora_inicio, $sesion->hora_fin);

            if ($inicio < $sFin && $sInicio < $fin) {
                $horario = $sesion->hora_inicio
                    ? ' ('.substr($sesion->hora_inicio, 0, 5).($sesion->hora_fin ? '–'.substr($sesion->hora_fin, 0, 5) : '').')'
                    : '';

                throw ValidationException::withMessages([
                    'hora_inicio' => "El instructor ya tiene una clase ese día en ese horario: \"{$sesion->titulo}\"{$horario}.",
                ]);
            }
        }
    }

    /**
     * Convierte el rango horario a minutos del día. Sin hora de inicio la
     * sesión ocupa todo el día; sin hora de fin se asume una hora de duración.
     */
    private function rangoEnMinutos(?string $horaInicio, ?string $horaFin): array
    {
        if (! $horaInicio) {
            return [0, 1440];
        }

        $aMinutos = fn (string $h) => ((int) substr($h, 0, 2)) * 60 + (int) substr($h, 3, 2);

        $inicio = $aMinutos($horaInicio);
        $fin = $horaFin ? $aMinutos($horaFin) : $inicio + 60;

        return [$inicio, $fin];
    }

    private function notifyInstructor(string $cursoId, string $accion)
    {
        $curso = Curso::with('instructor.user')->find($cursoId);
        if ($curso && $curso->instructor && $curso->instructor->user) {
            $curso->instructor->user->notify(new GenericNotification(
                'Horario Actualizado',
                "El administrador ha {$accion} una sesión en el horario del curso: {$curso->nombre}.",
                "/profesor/cursos/{$curso->id}"
            ));
        }
    }

    public function index(string $cursoId)
    {
        Curso::findOrFail($cursoId);

        return response()->json(
            Sesion::where('curso_id', $cursoId)->orderBy('fecha')->orderBy('hora_inicio')->get()
        );
    }

    public function horario(Request $request)
    {
        $query = Sesion::with(['curso.instructor.user'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->input('hasta'));
        }
        if ($request->filled('curso_id')) {
            $query->where('curso_id', $request->input('curso_id'));
        }
        if ($request->filled('instructor_id')) {
            $query->whereHas('curso', function ($q) use ($request) {
                $q->where('profesor_id', $request->input('instructor_id'));
            });
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        return response()->json($query->get());
    }

    public function horarioProfesor(Request $request)
    {
        $profesor = Profesor::where('user_id', Auth::id())->firstOrFail();

        $query = Sesion::with(['curso.instructor.user'])
            ->whereHas('curso', function ($q) use ($profesor) {
                $q->where('profesor_id', $profesor->id);
            })
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->input('hasta'));
        }
        if ($request->filled('curso_id')) {
            $query->where('curso_id', $request->input('curso_id'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        return response()->json($query->get());
    }

    public function storeGlobal(Request $request)
    {
        $data = $request->validate([
            'curso_id' => 'required|exists:cursos,id',
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'required|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'estado' => 'in:programada,realizada,cancelada',
        ]);

        if (($data['estado'] ?? 'programada') !== 'cancelada') {
            $this->validarConflictoInstructor(
                $data['curso_id'],
                $data['fecha'],
                $data['hora_inicio'] ?? null,
                $data['hora_fin'] ?? null,
            );
        }

        $sesion = Sesion::create($data);
        $sesion->load(['curso.instructor.user']);

        $this->notifyInstructor($data['curso_id'], 'agregado');

        return response()->json($sesion, 201);
    }

    public function updateGlobal(Request $request, string $id)
    {
        $sesion = Sesion::findOrFail($id);

        $data = $request->validate([
            'curso_id' => 'sometimes|exists:cursos,id',
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'sometimes|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'estado' => 'in:programada,realizada,cancelada',
        ]);

        $estadoFinal = $data['estado'] ?? $sesion->estado;
        if ($estadoFinal !== 'cancelada') {
            $this->validarConflictoInstructor(
                $data['curso_id'] ?? $sesion->curso_id,
                $data['fecha'] ?? $sesion->fecha->format('Y-m-d'),
                array_key_exists('hora_inicio', $data) ? $data['hora_inicio'] : $sesion->hora_inicio,
                array_key_exists('hora_fin', $data) ? $data['hora_fin'] : $sesion->hora_fin,
                $sesion->id,
            );
        }

        $sesion->update($data);
        $sesion->load(['curso.instructor.user']);

        $this->notifyInstructor($sesion->curso_id, 'actualizado');

        return response()->json($sesion);
    }

    public function destroyGlobal(string $id)
    {
        $sesion = Sesion::findOrFail($id);
        $cursoId = $sesion->curso_id;
        $sesion->delete();

        $this->notifyInstructor($cursoId, 'eliminado');

        return response()->json(['message' => 'Sesión eliminada.']);
    }

    public function store(Request $request, string $cursoId)
    {
        Curso::findOrFail($cursoId);

        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'required|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'estado' => 'in:programada,realizada,cancelada',
        ]);

        if (($data['estado'] ?? 'programada') !== 'cancelada') {
            $this->validarConflictoInstructor(
                $cursoId,
                $data['fecha'],
                $data['hora_inicio'] ?? null,
                $data['hora_fin'] ?? null,
            );
        }

        $sesion = Sesion::create(['curso_id' => $cursoId, ...$data]);

        $this->notifyInstructor($cursoId, 'agregado');

        return response()->json($sesion, 201);
    }

    public function update(Request $request, string $cursoId, string $id)
    {
        $sesion = Sesion::where('curso_id', $cursoId)->findOrFail($id);

        $data = $request->validate([
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'sometimes|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'estado' => 'in:programada,realizada,cancelada',
        ]);

        $estadoFinal = $data['estado'] ?? $sesion->estado;
        if ($estadoFinal !== 'cancelada') {
            $this->validarConflictoInstructor(
                $cursoId,
                $data['fecha'] ?? $sesion->fecha->format('Y-m-d'),
                array_key_exists('hora_inicio', $data) ? $data['hora_inicio'] : $sesion->hora_inicio,
                array_key_exists('hora_fin', $data) ? $data['hora_fin'] : $sesion->hora_fin,
                $sesion->id,
            );
        }

        $sesion->update($data);

        $this->notifyInstructor($cursoId, 'actualizado');

        return response()->json($sesion);
    }

    public function destroy(string $cursoId, string $id)
    {
        $sesion = Sesion::where('curso_id', $cursoId)->findOrFail($id);
        $sesion->delete();

        $this->notifyInstructor($cursoId, 'eliminado');

        return response()->json(['message' => 'Sesión eliminada.']);
    }
}
