<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('cursos:actualizar-estados')->dailyAt('00:10')->withoutOverlapping(10);

// Bitácora: retención y comprobación de integridad.
Schedule::command('bitacora:depurar')->dailyAt('02:00')->withoutOverlapping(10);
Schedule::command('bitacora:verificar --dias=8')->weeklyOn(1, '03:00')->withoutOverlapping(10);

Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('03:30')->withoutOverlapping(10);
