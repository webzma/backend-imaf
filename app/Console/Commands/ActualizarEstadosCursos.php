<?php

namespace App\Console\Commands;

use App\Services\CursoEstadoService;
use Illuminate\Console\Command;

class ActualizarEstadosCursos extends Command
{
    protected $signature = 'cursos:actualizar-estados';

    protected $description = 'Inactiva cursos vencidos y cursos que llegan a su fecha de inicio sin el mínimo de estudiantes';

    public function handle(): int
    {
        CursoEstadoService::sincronizar();

        $this->info('Estados de cursos sincronizados.');

        return self::SUCCESS;
    }
}
