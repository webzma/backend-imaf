<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Se ejecuta antes que las migraciones que normalizan el esquema.
 *
 * MySQL no revierte los cambios de estructura, así que un fallo a mitad de
 * camino dejaría la base a medio migrar. Aquí se detectan, sin modificar nada,
 * los datos que impedirían terminar.
 */
return new class() extends Migration
{
    public function up(): void
    {
        $problemas = [];

        foreach (['estudiantes', 'profesores'] as $tabla) {
            $repetidos = DB::table($tabla)
                ->select('user_id')
                ->groupBy('user_id')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('user_id');

            if ($repetidos->isNotEmpty()) {
                $problemas[] = "{$tabla}: varios registros para los user_id ".$repetidos->implode(', ');
            }
        }

        $pagosSinEstudiante = DB::table('pagos')
            ->whereNotExists(fn ($q) => $q->from('estudiantes')
                ->whereColumn('estudiantes.user_id', 'pagos.user_id'))
            ->pluck('id');

        if ($pagosSinEstudiante->isNotEmpty()) {
            $problemas[] = 'pagos cuyo usuario no tiene ficha de estudiante: '.$pagosSinEstudiante->implode(', ');
        }

        if ($problemas !== []) {
            throw new RuntimeException(
                "No se puede normalizar el esquema; corrige estos datos y vuelve a migrar:\n- ".implode("\n- ", $problemas),
            );
        }
    }

    public function down(): void
    {
        // Solo verifica: no hay nada que revertir.
    }
};
