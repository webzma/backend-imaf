<?php

namespace Tests\Feature;

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EstudianteTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_puede_listar_estudiantes_paginados(): void
    {
        $this->actingAsAdmin();
        Estudiante::factory()->count(5)->create();

        $this->getJson('/api/admin/estudiantes')
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page', 'total', 'per_page']);
    }

    public function test_admin_puede_crear_estudiante_con_su_usuario(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/estudiantes', [
            'name' => 'María López',
            'email' => 'maria@example.com',
            'password' => 'password123',
            'cedula' => '001-2222222-2',
            'fecha_inscripcion' => '2026-06-01',
            'estado' => 'activo',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['nombre' => 'María López']);

        $this->assertDatabaseHas('users', [
            'email' => 'maria@example.com',
            'role' => 'estudiante',
        ]);
        $this->assertDatabaseHas('estudiantes', [
            'cedula' => '001-2222222-2',
            'estado' => 'activo',
        ]);
    }

    public function test_crear_estudiante_falla_con_cedula_duplicada(): void
    {
        $this->actingAsAdmin();
        Estudiante::factory()->create(['cedula' => '001-3333333-3']);

        $this->postJson('/api/admin/estudiantes', [
            'name' => 'Pedro',
            'email' => 'pedro@example.com',
            'password' => 'password123',
            'cedula' => '001-3333333-3',
            'fecha_inscripcion' => '2026-06-01',
        ])->assertStatus(422)->assertJsonValidationErrors('cedula');
    }

    public function test_filtro_por_estado_devuelve_solo_coincidencias(): void
    {
        $this->actingAsAdmin();
        Estudiante::factory()->count(2)->create(['estado' => 'activo']);
        Estudiante::factory()->create(['estado' => 'inactivo']);

        $response = $this->getJson('/api/admin/estudiantes?estado=inactivo')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_un_profesor_no_puede_acceder_a_estudiantes_admin(): void
    {
        $profesor = User::factory()->create(['role' => 'profesor']);
        Sanctum::actingAs($profesor);

        $this->getJson('/api/admin/estudiantes')
            ->assertStatus(403);
    }
}
