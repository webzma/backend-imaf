<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CursoTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_admin_puede_listar_cursos(): void
    {
        $this->actingAsAdmin();
        Curso::factory()->count(3)->create();

        $this->getJson('/api/admin/cursos')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'total']);
    }

    public function test_admin_puede_crear_un_curso(): void
    {
        Notification::fake();
        $this->actingAsAdmin();
        $profesor = Profesor::factory()->create();

        $response = $this->postJson('/api/admin/cursos', [
            'profesor_id' => $profesor->id,
            'nombre' => 'Programación I',
            'limite_cupo' => 25,
            'precio' => 1500,
            'estado' => 'activo',
        ]);

        $response->assertCreated()
            ->assertJsonFragment(['nombre' => 'Programación I']);

        $this->assertDatabaseHas('cursos', [
            'nombre' => 'Programación I',
            'profesor_id' => $profesor->id,
            'limite_cupo' => 25,
        ]);
    }

    public function test_crear_curso_falla_sin_campos_requeridos(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/cursos', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['profesor_id', 'nombre', 'limite_cupo', 'precio']);
    }

    public function test_crear_curso_falla_con_profesor_inexistente(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/admin/cursos', [
            'profesor_id' => 9999,
            'nombre' => 'Curso fantasma',
            'limite_cupo' => 10,
            'precio' => 100,
        ])->assertStatus(422)->assertJsonValidationErrors('profesor_id');
    }

    private function payloadCurso(array $overrides = []): array
    {
        $profesor = Profesor::factory()->create();

        return array_merge([
            'profesor_id' => $profesor->id,
            'nombre' => 'Curso de prueba',
            'limite_cupo' => 20,
            'precio' => 100,
            'estado' => 'activo',
        ], $overrides);
    }

    public function test_crear_curso_falla_si_fecha_inicio_es_sabado(): void
    {
        $this->actingAsAdmin();

        // 2026-09-05 es sábado
        $this->postJson('/api/admin/cursos', $this->payloadCurso([
            'fecha_inicio' => '2026-09-05',
        ]))->assertStatus(422)->assertJsonValidationErrors('fecha_inicio');
    }

    public function test_crear_curso_falla_si_fecha_fin_es_domingo(): void
    {
        $this->actingAsAdmin();

        // 2026-09-06 es domingo
        $this->postJson('/api/admin/cursos', $this->payloadCurso([
            'fecha_inicio' => '2026-09-01',
            'fecha_fin' => '2026-09-06',
        ]))->assertStatus(422)->assertJsonValidationErrors('fecha_fin');
    }

    public function test_crear_curso_falla_si_fecha_inicio_es_feriado_fijo(): void
    {
        $this->actingAsAdmin();

        // 2026-07-24 (viernes) es el Natalicio de Simón Bolívar
        $this->postJson('/api/admin/cursos', $this->payloadCurso([
            'fecha_inicio' => '2026-07-24',
        ]))->assertStatus(422)->assertJsonValidationErrors('fecha_inicio');
    }

    public function test_crear_curso_falla_si_fecha_inicio_es_feriado_movil(): void
    {
        $this->actingAsAdmin();

        // 2026-04-03 es Viernes Santo (Pascua 2026 = 5 de abril)
        $this->postJson('/api/admin/cursos', $this->payloadCurso([
            'fecha_inicio' => '2026-04-03',
        ]))->assertStatus(422)->assertJsonValidationErrors('fecha_inicio');
    }

    public function test_crear_curso_acepta_dias_habiles(): void
    {
        Notification::fake();
        $this->actingAsAdmin();

        // Lunes 2026-09-07 y viernes 2026-09-11, ninguno feriado
        $this->postJson('/api/admin/cursos', $this->payloadCurso([
            'fecha_inicio' => '2026-09-07',
            'fecha_fin' => '2026-09-11',
        ]))->assertCreated();
    }

    public function test_admin_puede_ver_un_curso(): void
    {
        $this->actingAsAdmin();
        $curso = Curso::factory()->create();

        $this->getJson("/api/admin/cursos/{$curso->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $curso->id]);
    }

    public function test_admin_puede_eliminar_un_curso(): void
    {
        $this->actingAsAdmin();
        $curso = Curso::factory()->create();

        $this->deleteJson("/api/admin/cursos/{$curso->id}")
            ->assertOk()
            ->assertJson(['message' => 'Curso eliminado correctamente.']);

        $this->assertSoftDeleted('cursos', ['id' => $curso->id]);
    }

    public function test_un_estudiante_no_puede_acceder_a_cursos_admin(): void
    {
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        Sanctum::actingAs($estudiante);

        $this->getJson('/api/admin/cursos')
            ->assertStatus(403)
            ->assertJson(['message' => 'No autorizado.']);
    }

    public function test_usuario_no_autenticado_no_puede_acceder_a_cursos(): void
    {
        $this->getJson('/api/admin/cursos')->assertUnauthorized();
    }
}
