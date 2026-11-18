<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Estudiante;
use App\Models\Sesion;
use App\Models\User;
use App\Services\Auditoria;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AsistenciaController extends Controller
{
    /** Devuelve el detalle de asistencia de una sesión.
     *
     * @return JsonResponse
     */
    public function show(Request $request, string $sesionId)
    {
        $sesion = Sesion::with('curso:id,nombre,profesor_id')->findOrFail($sesionId);

        if ($this->accesoProfesorDenegado($request->user(), $sesion)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $estudiantes = Estudiante::query()
            ->whereHas('cursos', fn (Builder $c) => $c->whereKey($sesion->curso_id))
            ->select('id', 'nombre', 'cedula', 'foto')
            ->get();

        $asistencias = Asistencia::query()
            ->where('sesion_id', $sesionId)
            ->get()
            ->keyBy('estudiante_id');

        $lista = $estudiantes->map(function ($estudiante) use ($asistencias) {
            $registro = $asistencias->get($estudiante->id);

            return [
                'estudiante_id' => $estudiante->id,
                'nombre' => $estudiante->nombre,
                'cedula' => $estudiante->cedula,
                'foto' => $estudiante->foto,
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

    /** Guarda la asistencia de una sesión.
     *
     * @return JsonResponse
     */
    public function store(Request $request, string $sesionId)
    {
        $sesion = Sesion::with('curso:id,profesor_id')->findOrFail($sesionId);

        if ($this->accesoProfesorDenegado($request->user(), $sesion)) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $data = $request->validate([
            'asistencia' => 'required|array',
            // Solo quienes figuran en la lista del curso, con el pago aprobado.
            'asistencia.*.estudiante_id' => [
                'required',
                'integer',
                Rule::exists('inscripciones', 'estudiante_id')
                    ->where('curso_id', $sesion->curso_id)
                    ->where('estado_pago', 'aprobado')
                    ->whereNull('deleted_at'),
            ],
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

        Asistencia::query()->upsert(
            $registros,
            ['sesion_id', 'estudiante_id'],
            ['presente', 'observacion', 'updated_at']
        );

        $presentes = collect($data['asistencia'])->where('presente', true)->count();
        Auditoria::registrar('asistencia.registrada', "Asistencia de la sesión «{$sesion->titulo}»: {$presentes} de ".count($registros).' presentes', [
            'entidad' => ['sesion', $sesion->id, $sesion->titulo],
            'metadatos' => [
                'curso_id' => $sesion->curso_id,
                'presentes' => $presentes,
                'total' => count($registros),
                'ausentes_ids' => collect($data['asistencia'])->where('presente', false)->pluck('estudiante_id')->values()->all(),
            ],
        ]);

        return response()->json(['message' => 'Asistencia guardada.']);
    }

    private function accesoProfesorDenegado(?Authenticatable $authUser, Sesion $sesion): bool
    {
        if (! $authUser instanceof User || ! $authUser->isProfesor()) {
            return false;
        }

        $profesorId = $authUser->profesor?->id;
        $cursoProfesorId = $sesion->curso?->profesor_id;

        if ($profesorId !== null && (int) $cursoProfesorId === (int) $profesorId) {
            return false;
        }

        Auditoria::registrar('seguridad.acceso_denegado', "Intento de acceder a la asistencia de una sesión de un curso ajeno (#{$sesion->id})", [
            'nivel' => 'aviso',
            'metadatos' => ['sesion_id' => $sesion->id, 'curso_id' => $sesion->curso_id],
        ]);

        return true;
    }
}
