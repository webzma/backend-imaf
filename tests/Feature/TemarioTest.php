<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Temario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TemarioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
    }

    public function test_admin_puede_agregar_tema_al_temario(): void
    {
        $curso = Curso::factory()->create();

        $this->postJson("/api/admin/cursos/{$curso->id}/temario", [
            'titulo' => 'Introducción',
            'descripcion' => 'Conceptos básicos',
        ])->assertCreated()->assertJsonFragment(['titulo' => 'Introducción']);

        $this->assertDatabaseHas('temarios', [
            'curso_id' => $curso->id,
            'titulo' => 'Introducción',
        ]);
    }

    public function test_orden_se_autoincrementa_cuando_no_se_envia(): void
    {
        $curso = Curso::factory()->create();
        Temario::create(['curso_id' => $curso->id, 'titulo' => 'Tema A', 'orden' => 5]);

        $response = $this->postJson("/api/admin/cursos/{$curso->id}/temario", [
            'titulo' => 'Tema B',
        ])->assertCreated();

        $this->assertEquals(6, $response->json('orden'));
    }

    public function test_admin_puede_eliminar_un_tema(): void
    {
        $curso = Curso::factory()->create();
        $tema = Temario::create(['curso_id' => $curso->id, 'titulo' => 'Borrar', 'orden' => 1]);

        $this->deleteJson("/api/admin/cursos/{$curso->id}/temario/{$tema->id}")
            ->assertOk();

        $this->assertDatabaseMissing('temarios', ['id' => $tema->id]);
    }
}
