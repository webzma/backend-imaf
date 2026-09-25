<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regla: el curso actual de un estudiante es siempre uno con el pago
 * aprobado. Datos anteriores la rompían (un estudiante veía "Mi curso" con el
 * pago rechazado o en revisión). Para cada estudiante con curso actual:
 *
 * - con un pago aprobado de ese curso: se queda, con el pago al día;
 * - con pagos de ese curso pero ninguno aprobado: sale del curso (la
 *   inscripción queda pendiente o rechazada) y vuelve a su último curso
 *   pagado, o se queda sin curso;
 * - sin ningún pago de ese curso: lo inscribió la administración a mano; se
 *   queda y su pago se da por aprobado.
 */
return new class() extends Migration
{
    public function up(): void
    {
        $ahora = now();

        $estudiantes = DB::table('estudiantes')
            ->whereNotNull('curso_id')
            ->whereNull('deleted_at')
            ->get(['id', 'user_id', 'curso_id']);

        foreach ($estudiantes as $e) {
            $pagos = DB::table('pagos')
                ->where('user_id', $e->user_id)
                ->where('curso_id', $e->curso_id)
                ->whereNull('deleted_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->pluck('estado');

            $inscripcion = ['estudiante_id' => $e->id, 'curso_id' => $e->curso_id];

            if ($pagos->isEmpty() || $pagos->contains('aprobado')) {
                DB::table('inscripciones')->updateOrInsert($inscripcion, [
                    'estado_pago' => 'aprobado',
                    'updated_at' => $ahora,
                ]);
                DB::table('estudiantes')->where('id', $e->id)->update([
                    'estado_pago' => 'aprobado',
                    'updated_at' => $ahora,
                ]);

                continue;
            }

            // El último pago manda: pendiente si sigue en revisión.
            $estado = $pagos->first() === 'pendiente' ? 'pendiente' : 'reprobado';

            DB::table('inscripciones')->updateOrInsert($inscripcion, [
                'estado_pago' => $estado,
                'updated_at' => $ahora,
            ]);

            $anterior = DB::table('inscripciones')
                ->where('estudiante_id', $e->id)
                ->where('estado_pago', 'aprobado')
                ->orderByDesc('fecha_inscripcion')
                ->orderByDesc('id')
                ->first();

            DB::table('estudiantes')->where('id', $e->id)->update([
                'curso_id' => $anterior?->curso_id,
                'estado_pago' => $anterior ? 'aprobado' : $estado,
                'estado_aprobacion_curso' => $anterior?->estado_aprobacion_curso ?? 'pendiente',
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        // Corrección de datos: no se puede deshacer.
    }
};
