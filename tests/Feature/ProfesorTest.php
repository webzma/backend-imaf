<?php

namespace Tests\Feature;

use App\Models\Profesor;
use App\Models\TipoContrato;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfesorTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_puede_listar_profesores(): void
    {
        $this->actingAsAdmin();
        Profesor::factory()->count(2)->create();

        $this->getJson('/api/admin/profesores')
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_admin_puede_crear_profesor_con_usuario(): void
    {
        $this->actingAsAdmin();
        $tipo = TipoContrato::create(['nombre' => 'Tiempo completo']);

        $response = $this->postJson('/api/admin/profesores', [
            'name' => 'Carlos Ruiz',
            'email' => 'carlos@example.com',
            'password' => 'password123',
            'cedula' => '001-4444444-4',
            'tipo_contrato_id' => $tipo->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'carlos@example.com', 'role' => 'profesor']);
        $this->assertDatabaseHas('profesores', ['cedula' => '001-4444444-4']);
    }

    public function test_crear_profesor_requiere_tipo_contrato_valido(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/profesores', [
            'name' => 'Sin contrato',
            'email' => 'sincontrato@example.com',
            'password' => 'password123',
            'cedula' => '001-5555555-5',
            'tipo_contrato_id' => 9999,
        ])->assertStatus(422)->assertJsonValidationErrors('tipo_contrato_id');
    }

    public function test_profesor_puede_actualizar_su_propio_perfil(): void
    {
        $profesor = Profesor::factory()->create();
        Sanctum::actingAs($profesor->user);

        $this->putJson("/api/profesor/perfil/{$profesor->id}", [
            'telefono' => '8095550000',
            'especialidad' => 'Matemáticas',
        ])->assertOk()->assertJsonFragment(['especialidad' => 'Matemáticas']);

        $this->assertDatabaseHas('profesores', [
            'id' => $profesor->id,
            'especialidad' => 'Matemáticas',
        ]);
    }

    public function test_profesor_no_puede_actualizar_el_perfil_de_otro(): void
    {
        $profesor = Profesor::factory()->create();
        $otro = Profesor::factory()->create();
        Sanctum::actingAs($profesor->user);

        $this->putJson("/api/profesor/perfil/{$otro->id}", [
            'especialidad' => 'Hacking',
        ])->assertStatus(403);
    }

    public function test_admin_puede_eliminar_profesor(): void
    {
        $this->actingAsAdmin();
        $profesor = Profesor::factory()->create();

        $this->deleteJson("/api/admin/profesores/{$profesor->id}")
            ->assertOk();

        $this->assertSoftDeleted('profesores', ['id' => $profesor->id]);
    }
}
