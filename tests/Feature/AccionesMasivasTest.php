<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Pago;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Acciones sobre varias filas seleccionadas.
 *
 * Revisar doce pagos pendientes eran doce recorridos completos de abrir ficha,
 * confirmar y cerrar.
 */
class AccionesMasivasTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    private function crearPago(Curso $curso, string $estado = 'pendiente'): Pago
    {
        $user = User::factory()->create(['role' => 'estudiante']);
        Estudiante::factory()->create(['user_id' => $user->id]);

        return Pago::create([
            'user_id' => $user->id,
            'curso_id' => $curso->id,
            'metodo_pago' => 'pago_movil',
            'referencia' => (string) fake()->unique()->numerify('########'),
            'estado' => $estado,
        ]);
    }

    public function test_aprobar_varios_pagos_de_una_vez(): void
    {
        $this->actingAsAdmin();
        $curso = Curso::factory()->create(['limite_cupo' => 30]);

        $pagos = collect(range(1, 3))->map(fn () => $this->crearPago($curso));

        $this->patchJson('/api/admin/pagos/masivo', [
            'ids' => $pagos->pluck('id')->all(),
            'estado' => 'aprobado',
        ])->assertOk()->assertJson(['procesados' => 3, 'fallidos' => []]);

        foreach ($pagos as $pago) {
            $this->assertSame('aprobado', $pago->fresh()->estado);
        }
    }

    public function test_un_pago_sin_cupo_no_arrastra_a_los_demas(): void
    {
        $this->actingAsAdmin();

        $conCupo = Curso::factory()->create(['limite_cupo' => 30]);
        $lleno = Curso::factory()->create(['limite_cupo' => 1]);
        Estudiante::factory()->create(['curso_id' => $lleno->id]);

        $ok = $this->crearPago($conCupo);
        $sinCupo = $this->crearPago($lleno);

        $res = $this->patchJson('/api/admin/pagos/masivo', [
            'ids' => [$ok->id, $sinCupo->id],
            'estado' => 'aprobado',
        ])->assertOk();

        $this->assertSame(1, $res->json('procesados'));
        $this->assertSame($sinCupo->id, $res->json('fallidos.0.id'));
        $this->assertSame('aprobado', $ok->fresh()->estado);
        $this->assertSame('pendiente', $sinCupo->fresh()->estado);
    }

    public function test_aprobar_un_solo_pago_sigue_funcionando(): void
    {
        $this->actingAsAdmin();
        $curso = Curso::factory()->create(['limite_cupo' => 10]);
        $pago = $this->crearPago($curso);

        $this->putJson("/api/admin/pagos/{$pago->id}", ['estado' => 'aprobado'])
            ->assertOk()
            ->assertJsonPath('pago.estado', 'aprobado');
    }

    public function test_cambiar_el_estado_de_varios_estudiantes(): void
    {
        $this->actingAsAdmin();
        $estudiantes = Estudiante::factory()->count(3)->create(['estado' => 'activo']);

        $this->patchJson('/api/admin/estudiantes/estado-masivo', [
            'ids' => $estudiantes->pluck('id')->all(),
            'estado' => 'graduado',
        ])->assertOk()->assertJson(['actualizados' => 3]);

        foreach ($estudiantes as $estudiante) {
            $this->assertSame('graduado', $estudiante->fresh()->estado);
        }
    }

    public function test_un_estado_invalido_se_rechaza(): void
    {
        $this->actingAsAdmin();
        $estudiante = Estudiante::factory()->create();

        $this->patchJson('/api/admin/estudiantes/estado-masivo', [
            'ids' => [$estudiante->id],
            'estado' => 'expulsado',
        ])->assertStatus(422)->assertJsonValidationErrors('estado');
    }
}
