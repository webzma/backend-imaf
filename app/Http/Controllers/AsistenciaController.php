<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Estudiante;
use App\Models\Sesion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AsistenciaController extends Controller
{
    public function show(string $sesionId)
    {
        $sesion = Sesion::with('curso:id,nombre,profesor_id')->findOrFail($sesionId);

        $authUser = Auth::user();
        if ($authUser->isProfesor()) {
            if ((int) $sesion->curso->profesor_id !== (int) $authUser->profesor->id) {
                return response()->json(['message' => 'No autorizado.'], 403);
            }
        }

        $estudiantes = Estudiante::where('curso_id', $sesion->curso_id)
            ->select('id', 'nombre', 'cedula')
            ->get();

        $asistencias = Asistencia::where('sesion_id', $sesionId)
            ->get()
            ->keyBy('estudiante_id');

        $lista = $estudiantes->map(function ($estudiante) use ($asistencias) {
            $registro = $asistencias->get($estudiante->id);

            return [
                'estudiante_id' => $estudiante->id,
                'nombre' => $estudiante->nombre,
                'cedula' => $estudiante->cedula,
                'presente' => $registro?->presente ?? false,
                'observacion' => $registro?->observacion,
            ];
        });

        return response()->json([
            'sesion' => [
                'id' => $sesion->id,
                'titulo' => $sesion->titulo,
                'fecha' => $sesion->fecha,
                'hora_inicio' => $sesion->hora_inicio,
                'hora_fin' => $sesion->hora_fin,
                'estado' => $sesion->estado,
                'curso_id' => $sesion->curso_id,
                'curso_nombre' => $sesion->curso->nombre,
            ],
            'asistencia' => $lista,
        ]);
    }

    public function store(Request $request, string $sesionId)
    {
        $sesion = Sesion::with('curso:id,profesor_id')->findOrFail($sesionId);

        $authUser = Auth::user();
        if ($authUser->isProfesor()) {
            if ((int) $sesion->curso->profesor_id !== (int) $authUser->profesor->id) {
                return response()->json(['message' => 'No autorizado.'], 403);
            }
        }

        $data = $request->validate([
            'asistencia' => 'required|array',
            'asistencia.*.estudiante_id' => 'required|integer|exists:estudiantes,id',
            'asistencia.*.presente' => 'required|boolean',
            'asistencia.*.observacion' => 'nullable|string|max:255',
        ]);

        $registros = collect($data['asistencia'])->map(fn ($item) => [
            'sesion_id' => (int) $sesionId,
            'estudiante_id' => $item['estudiante_id'],
            'presente' => $item['presente'],
            'observacion' => $item['observacion'] ?? null,
            'updated_at' => now(),
            'created_at' => now(),
        ])->all();

        Asistencia::upsert(
            $registros,
            ['sesion_id', 'estudiante_id'],
            ['presente', 'observacion', 'updated_at']
        );

        return response()->json(['message' => 'Asistencia guardada.']);
    }
}
