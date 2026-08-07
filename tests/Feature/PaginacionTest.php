<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaginacionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_estudiantes_pagina_con_per_page_10_y_devuelve_metadatos(): void
    {
        $this->actingAsAdmin();
        Estudiante::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/estudiantes?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('per_page', 10);
    }

    public function test_estudiantes_pagina_2_devuelve_los_siguientes_10(): void
    {
        $this->actingAsAdmin();
        Estudiante::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/estudiantes?page=2&per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 3);
    }

    public function test_estudiantes_con_per_page_1000_devuelve_todos(): void
    {
        $this->actingAsAdmin();
        Estudiante::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/estudiantes?per_page=1000');

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('per_page', 1000)
            ->assertJsonPath('total', 25);
    }

    public function test_cursos_pagina_con_per_page_10(): void
    {
        $this->actingAsAdmin();
        Curso::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/cursos?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('per_page', 10);
    }

    public function test_cursos_con_per_page_1000_devuelve_todos(): void
    {
        $this->actingAsAdmin();
        Curso::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/cursos?per_page=1000');

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('per_page', 1000);
    }

    public function test_profesores_pagina_con_per_page_10(): void
    {
        $this->actingAsAdmin();
        Profesor::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/profesores?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('per_page', 10);
    }

    public function test_per_page_invalido_se_acota_a_valores_validos(): void
    {
        $this->actingAsAdmin();
        Estudiante::factory()->count(5)->create();

        $this->getJson('/api/admin/estudiantes?per_page=abc')
            ->assertOk()
            ->assertJsonPath('per_page', 1);

        $this->getJson('/api/admin/estudiantes?per_page=5000')
            ->assertOk()
            ->assertJsonPath('per_page', 1000);
    }
}
