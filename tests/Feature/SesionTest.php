<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SesionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
    }

    public function test_admin_puede_crear_sesion_global(): void
    {
        $curso = Curso::factory()->create();

        $response = $this->postJson('/api/admin/sesiones', [
            'curso_id' => $curso->id,
            'titulo' => 'Clase inaugural',
            'fecha' => '2026-07-01',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ]);

        $response->assertCreated()->assertJsonFragment(['titulo' => 'Clase inaugural']);
        $this->assertDatabaseHas('sesiones', [
            'curso_id' => $curso->id,
            'titulo' => 'Clase inaugural',
        ]);
    }

    public function test_crear_sesion_falla_si_hora_fin_es_antes_de_inicio(): void
    {
        $curso = Curso::factory()->create();

        $this->postJson('/api/admin/sesiones', [
            'curso_id' => $curso->id,
            'titulo' => 'Hora inválida',
            'fecha' => '2026-07-01',
            'hora_inicio' => '10:00',
            'hora_fin' => '08:00',
        ])->assertStatus(422)->assertJsonValidationErrors('hora_fin');
    }

    public function test_listar_sesiones_de_un_curso(): void
    {
        $curso = Curso::factory()->create();
        Sesion::create([
            'curso_id' => $curso->id,
            'titulo' => 'Sesión 1',
            'fecha' => '2026-07-02',
        ]);

        $this->getJson("/api/admin/cursos/{$curso->id}/sesiones")
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_admin_puede_eliminar_sesion(): void
    {
        $curso = Curso::factory()->create();
        $sesion = Sesion::create([
            'curso_id' => $curso->id,
            'titulo' => 'A borrar',
            'fecha' => '2026-07-03',
        ]);

        $this->deleteJson("/api/admin/sesiones/{$sesion->id}")
            ->assertOk();

        $this->assertDatabaseMissing('sesiones', ['id' => $sesion->id]);
    }
}
