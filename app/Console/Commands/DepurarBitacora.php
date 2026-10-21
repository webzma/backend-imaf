<?php

namespace App\Console\Commands;

use App\Services\Auditoria;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Borra los registros de la bitácora más antiguos que la retención
 * configurada. Es la única forma de borrar registros, y deja constancia.
 */
class DepurarBitacora extends Command
{
    protected $signature = 'bitacora:depurar {--dias= : Días a conservar (por defecto, auditoria.retencion_dias)}';

    protected $description = 'Elimina los registros de la bitácora más antiguos que el período de retención';

    public function handle(): int
    {
        $dias = (int) ($this->option('dias') ?? config('auditoria.retencion_dias'));

        if ($dias <= 0) {
            $this->info('Retención ilimitada: no se borra nada.');

            return self::SUCCESS;
        }

        $limite = now()->subDays($dias);
        $borrados = 0;

        // Por lotes, para no bloquear la tabla con un único DELETE enorme.
        do {
            $ids = DB::table('bitacora')->where('created_at', '<', $limite)->limit(1000)->pluck('id');
            $borrados += $ids->isEmpty() ? 0 : DB::table('bitacora')->whereIn('id', $ids)->delete();
        } while ($ids->isNotEmpty());

        Auditoria::registrar('bitacora.depurada', "Depuración de la bitácora: {$borrados} registros anteriores a {$limite->toDateString()}", [
            'actor' => false,
            'nivel' => $borrados > 0 ? 'aviso' : 'info',
            'metadatos' => ['dias' => $dias, 'limite' => $limite->toIso8601String(), 'borrados' => $borrados],
        ]);

        $this->info("Registros eliminados: {$borrados}.");

        return self::SUCCESS;
    }
}
