<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rechazar un pago no retiraba al estudiante del curso: seguía figurando como
 * inscrito si antes se había aprobado o si el admin se lo asignó a mano. Aquí
 * se retiran las inscripciones cuyo curso tiene un pago rechazado y ninguno
 * aprobado, igual que hace ahora PagoController al rechazar.
 */
return new class() extends Migration
{
    public function up(): void
    {
        $afectadas = DB::table('inscripciones')
            ->join('estudiantes', 'estudiantes.id', '=', 'inscripciones.estudiante_id')
            ->whereExists(fn ($q) => $q->from('pagos')
                ->whereColumn('pagos.user_id', 'estudiantes.user_id')
                ->whereColumn('pagos.curso_id', 'inscripciones.curso_id')
                ->where('pagos.estado', 'rechazado')
                ->whereNull('pagos.deleted_at'))
            ->whereNotExists(fn ($q) => $q->from('pagos')
                ->whereColumn('pagos.user_id', 'estudiantes.user_id')
                ->whereColumn('pagos.curso_id', 'inscripciones.curso_id')
                ->where('pagos.estado', 'aprobado')
                ->whereNull('pagos.deleted_at'))
            ->get(['inscripciones.id', 'inscripciones.estudiante_id', 'inscripciones.curso_id', 'estudiantes.curso_id as curso_actual']);

        foreach ($afectadas as $fila) {
            DB::table('inscripciones')->where('id', $fila->id)->delete();

            if ((int) $fila->curso_actual !== (int) $fila->curso_id) {
                continue;
            }

            $anterior = DB::table('inscripciones')
                ->where('estudiante_id', $fila->estudiante_id)
                ->orderByDesc('fecha_inscripcion')
                ->orderByDesc('id')
                ->first();

            DB::table('estudiantes')->where('id', $fila->estudiante_id)->update([
                'curso_id' => $anterior?->curso_id,
                'estado_pago' => $anterior ? 'aprobado' : 'reprobado',
                'estado_aprobacion_curso' => $anterior?->estado_aprobacion_curso ?? 'pendiente',
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Corrección de datos: no se puede deshacer.
    }
};
