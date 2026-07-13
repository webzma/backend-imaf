<?php

namespace App\Services;

use App\Models\Curso;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Support\Facades\Cache;

class CursoEstadoService
{
    /**
     * Ejecuta la sincronización como máximo una vez cada 10 minutos
     * (para poder invocarla desde los listados sin costo por request).
     */
    public static function sincronizarConCache(): void
    {
        if (Cache::add('cursos-estado-sincronizado', true, now()->addMinutes(10))) {
            self::sincronizar();
        }
    }

    /**
     * Sincroniza el estado de los cursos según sus fechas:
     *
     * - Un curso activo cuya fecha de culminación ya llegó pasa a "inactivo" (MY-102).
     * - Un curso activo que alcanza su fecha de inicio sin el mínimo de
     *   estudiantes inscritos pasa a "inactivo" y se alerta al admin (MY-103).
     */
    public static function sincronizar(): void
    {
        $hoy = now()->toDateString();

        $vencidos = Curso::with('instructor.user')
            ->where('estado', 'activo')
            ->whereNotNull('fecha_fin')
            ->whereDate('fecha_fin', '<=', $hoy)
            ->get();

        foreach ($vencidos as $curso) {
            $curso->update(['estado' => 'inactivo']);
            self::notificar(
                $curso,
                'Curso Finalizado',
                "El curso '{$curso->nombre}' alcanzó su fecha límite y pasó automáticamente a estado Inactivo.",
            );
        }

        $sinMinimo = Curso::with('instructor.user')
            ->where('estado', 'activo')
            ->whereNotNull('fecha_inicio')
            ->whereDate('fecha_inicio', '<=', $hoy)
            ->whereNotNull('minimo_estudiantes')
            ->get()
            ->filter(fn (Curso $c) => $c->estudiantes()->count() < $c->minimo_estudiantes);

        foreach ($sinMinimo as $curso) {
            $inscritos = $curso->estudiantes()->count();
            $curso->update(['estado' => 'inactivo']);
            self::notificar(
                $curso,
                'Curso sin mínimo de estudiantes',
                "El curso '{$curso->nombre}' llegó a su fecha de inicio con {$inscritos} inscritos "
                ."(mínimo requerido: {$curso->minimo_estudiantes}) y no fue aperturado.",
            );
        }
    }

    private static function notificar(Curso $curso, string $titulo, string $mensaje): void
    {
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->notify(new GenericNotification($titulo, $mensaje, "/admin/cursos/{$curso->id}"));
        }

        $profesorUser = $curso->instructor?->user;
        if ($profesorUser) {
            $profesorUser->notify(new GenericNotification($titulo, $mensaje, "/profesor/cursos/{$curso->id}"));
        }
    }
}
