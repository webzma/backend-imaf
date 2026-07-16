<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rechaza fechas que caen en sábado, domingo o feriado nacional
 * de Venezuela (fijos y móviles según el domingo de Pascua).
 */
class DiaHabil implements ValidationRule
{
    /** Feriados nacionales de fecha fija, en formato "m-d". */
    private const FERIADOS_FIJOS = [
        '01-01' => 'Año Nuevo',
        '04-19' => 'Declaración de la Independencia',
        '05-01' => 'Día del Trabajador',
        '06-24' => 'Batalla de Carabobo',
        '07-05' => 'Día de la Independencia',
        '07-24' => 'Natalicio de Simón Bolívar',
        '10-12' => 'Día de la Resistencia Indígena',
        '12-24' => 'Nochebuena',
        '12-25' => 'Navidad',
        '12-31' => 'Fin de Año',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $fecha = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return; // el formato inválido lo reporta la regla `date`
        }

        if ($fecha->isSaturday()) {
            $fail('El campo :attribute no puede caer en sábado.');

            return;
        }

        if ($fecha->isSunday()) {
            $fail('El campo :attribute no puede caer en domingo.');

            return;
        }

        if ($feriado = $this->nombreFeriado($fecha)) {
            $fail("El campo :attribute no puede caer en un día feriado ({$feriado}).");
        }
    }

    private function nombreFeriado(Carbon $fecha): ?string
    {
        $clave = $fecha->format('m-d');
        if (isset(self::FERIADOS_FIJOS[$clave])) {
            return self::FERIADOS_FIJOS[$clave];
        }

        $pascua = $this->domingoDePascua($fecha->year);
        $moviles = [
            'Lunes de Carnaval' => $pascua->copy()->subDays(48),
            'Martes de Carnaval' => $pascua->copy()->subDays(47),
            'Jueves Santo' => $pascua->copy()->subDays(3),
            'Viernes Santo' => $pascua->copy()->subDays(2),
        ];

        foreach ($moviles as $nombre => $dia) {
            if ($fecha->isSameDay($dia)) {
                return $nombre;
            }
        }

        return null;
    }

    /** Domingo de Pascua (algoritmo de Meeus/Jones/Butcher, calendario gregoriano). */
    private function domingoDePascua(int $anio): Carbon
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($anio, $mes, $dia)->startOfDay();
    }
}
