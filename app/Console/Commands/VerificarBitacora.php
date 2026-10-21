<?php

namespace App\Console\Commands;

use App\Models\Bitacora;
use App\Services\Auditoria;
use Illuminate\Console\Command;

/**
 * Recalcula la huella de cada registro y lista los que no coinciden: un
 * registro modificado directamente en la base de datos queda en evidencia.
 * (Un registro borrado no deja huella; la depuración legítima sí queda
 * registrada como `bitacora.depurada`.)
 */
class VerificarBitacora extends Command
{
    protected $signature = 'bitacora:verificar
        {--desde= : Fecha desde la que verificar (YYYY-MM-DD)}
        {--dias= : Verificar solo los últimos N días}';

    protected $description = 'Comprueba la integridad de los registros de la bitácora';

    public function handle(): int
    {
        $query = Bitacora::query()->orderBy('created_at');
        if ($desde = $this->option('desde')) {
            $query->where('created_at', '>=', $desde);
        } elseif ($dias = $this->option('dias')) {
            $query->where('created_at', '>=', now()->subDays((int) $dias));
        }

        $revisados = 0;
        $alterados = [];

        $query->lazy(1000)->each(function (Bitacora $registro) use (&$revisados, &$alterados) {
            $revisados++;
            if (! $registro->huellaValida()) {
                $alterados[] = $registro->id;
            }
        });

        if ($alterados === []) {
            $this->info("Bitácora íntegra: {$revisados} registros verificados.");

            return self::SUCCESS;
        }

        Auditoria::registrar('bitacora.alteracion_detectada', count($alterados).' registros de la bitácora no superan la verificación de integridad', [
            'actor' => false,
            'nivel' => 'critico',
            'metadatos' => ['ids' => array_slice($alterados, 0, 500), 'revisados' => $revisados],
        ]);

        $this->error(count($alterados)." de {$revisados} registros están alterados:");
        foreach (array_slice($alterados, 0, 50) as $id) {
            $this->line("  - {$id}");
        }

        return self::FAILURE;
    }
}
