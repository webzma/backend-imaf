<?php

namespace Tests;

use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Pago;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Crea un pago a partir del usuario y el curso. Si no hay inscripción, la
     * crea retirada: así el estudiante no aparece en el curso solo por el pago.
     *
     * @param  array{user_id: int, curso_id: int}&array<string, mixed>  $datos
     */
    protected function registrarPago(array $datos): Pago
    {
        $estudiante = Estudiante::where('user_id', $datos['user_id'])->first()
            ?? Estudiante::factory()->create(['user_id' => $datos['user_id']]);

        $inscripcion = Inscripcion::withTrashed()->firstOrCreate(
            ['estudiante_id' => $estudiante->id, 'curso_id' => $datos['curso_id']],
            ['estado_pago' => 'pendiente', 'fecha_inscripcion' => now()->toDateString(), 'deleted_at' => now()],
        );

        unset($datos['user_id'], $datos['curso_id']);

        return Pago::create(['inscripcion_id' => $inscripcion->id] + $datos);
    }
}
