<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Profesor;
use App\Models\Sesion;
use App\Models\Temario;
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

    public function test_horario_global_devuelve_sesiones_con_curso_e_instructor(): void
    {
        $curso = Curso::factory()->create();
        Sesion::create([
            'curso_id' => $curso->id,
            'titulo' => 'Clase 1',
            'fecha' => '2026-05-01',
            'estado' => 'programada',
        ]);
        Sesion::create([
            'curso_id' => $curso->id,
            'titulo' => 'Clase 2',
            'fecha' => '2026-05-08',
            'estado' => 'cancelada',
        ]);

        $this->getJson('/api/admin/horario')
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonStructure([
                ['id', 'titulo', 'fecha', 'estado', 'curso' => ['id', 'nombre', 'instructor']],
            ]);
    }

    public function test_horario_global_filtra_por_estado(): void
    {
        $curso = Curso::factory()->create();
        Sesion::create([
            'curso_id' => $curso->id,
            'titulo' => 'Programada',
            'fecha' => '2026-05-01',
            'estado' => 'programada',
        ]);
        Sesion::create([
            'curso_id' => $curso->id,
            'titulo' => 'Cancelada',
            'fecha' => '2026-05-08',
            'estado' => 'cancelada',
        ]);

        $this->getJson('/api/admin/horario?estado=cancelada')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['titulo' => 'Cancelada']);
    }

    public function test_no_permite_clase_solapada_del_mismo_instructor(): void
    {
        $profesor = Profesor::factory()->create();
        $cursoA = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $cursoB = Curso::factory()->create(['profesor_id' => $profesor->id]);

        Sesion::create([
            'curso_id' => $cursoA->id,
            'titulo' => 'Clase existente',
            'fecha' => '2026-07-20',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ]);

        $this->postJson('/api/admin/sesiones', [
            'curso_id' => $cursoB->id,
            'titulo' => 'Clase en conflicto',
            'fecha' => '2026-07-20',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
        ])->assertStatus(422)->assertJsonValidationErrors('hora_inicio');

        $this->assertDatabaseMissing('sesiones', ['titulo' => 'Clase en conflicto']);
    }

    public function test_permite_misma_hora_con_instructores_distintos(): void
    {
        $cursoA = Curso::factory()->create();
        $cursoB = Curso::factory()->create();

        Sesion::create([
            'curso_id' => $cursoA->id,
            'titulo' => 'Clase A',
            'fecha' => '2026-07-20',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ]);

        $this->postJson('/api/admin/sesiones', [
            'curso_id' => $cursoB->id,
            'titulo' => 'Clase B',
            'fecha' => '2026-07-20',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ])->assertCreated();
    }

    public function test_permite_mismo_dia_sin_solapamiento_de_horario(): void
    {
        $profesor = Profesor::factory()->create();
        $cursoA = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $cursoB = Curso::factory()->create(['profesor_id' => $profesor->id]);

        Sesion::create([
            'curso_id' => $cursoA->id,
            'titulo' => 'Clase mañana',
            'fecha' => '2026-07-20',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ]);

        $this->postJson('/api/admin/sesiones', [
            'curso_id' => $cursoB->id,
            'titulo' => 'Clase tarde',
            'fecha' => '2026-07-20',
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
        ])->assertCreated();
    }

    public function test_una_clase_cancelada_no_bloquea_el_horario(): void
    {
        $profesor = Profesor::factory()->create();
        $cursoA = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $cursoB = Curso::factory()->create(['profesor_id' => $profesor->id]);

        Sesion::create([
            'curso_id' => $cursoA->id,
            'titulo' => 'Clase cancelada',
            'fecha' => '2026-07-20',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
            'estado' => 'cancelada',
        ]);

        $this->postJson('/api/admin/sesiones', [
            'curso_id' => $cursoB->id,
            'titulo' => 'Clase nueva',
            'fecha' => '2026-07-20',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ])->assertCreated();
    }

    public function test_mover_sesion_a_fecha_con_conflicto_falla(): void
    {
        $profesor = Profesor::factory()->create();
        $cursoA = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $cursoB = Curso::factory()->create(['profesor_id' => $profesor->id]);

        Sesion::create([
            'curso_id' => $cursoA->id,
            'titulo' => 'Clase fija',
            'fecha' => '2026-07-20',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ]);
        $movible = Sesion::create([
            'curso_id' => $cursoB->id,
            'titulo' => 'Clase movible',
            'fecha' => '2026-07-21',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ]);

        // Drag & drop del calendario: solo envía la nueva fecha
        $this->putJson("/api/admin/sesiones/{$movible->id}", [
            'fecha' => '2026-07-20',
        ])->assertStatus(422)->assertJsonValidationErrors('hora_inicio');
    }

    public function test_editar_sesion_no_choca_consigo_misma(): void
    {
        $curso = Curso::factory()->create();
        $sesion = Sesion::create([
            'curso_id' => $curso->id,
            'titulo' => 'Clase única',
            'fecha' => '2026-07-20',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ]);

        $this->putJson("/api/admin/sesiones/{$sesion->id}", [
            'titulo' => 'Clase única (editada)',
        ])->assertOk()->assertJsonFragment(['titulo' => 'Clase única (editada)']);
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

    public function test_la_sesion_toma_el_titulo_del_tema_elegido(): void
    {
        $curso = Curso::factory()->create();
        $tema = Temario::create(['curso_id' => $curso->id, 'titulo' => 'Introducción al dibujo', 'orden' => 1]);

        $this->postJson("/api/admin/cursos/{$curso->id}/sesiones", [
            'temario_id' => $tema->id,
            'fecha' => '2026-10-21',
            'estado' => 'programada',
        ])->assertCreated()
            ->assertJsonPath('temario_id', $tema->id)
            ->assertJsonPath('titulo', 'Introducción al dibujo');
    }

    public function test_rechaza_un_tema_de_otro_curso(): void
    {
        $curso = Curso::factory()->create();
        $ajeno = Temario::create(['curso_id' => Curso::factory()->create()->id, 'titulo' => 'Ajeno', 'orden' => 1]);

        $this->postJson('/api/admin/sesiones', [
            'curso_id' => $curso->id,
            'temario_id' => $ajeno->id,
            'fecha' => '2026-10-21',
        ])->assertStatus(422)->assertJsonValidationErrors('temario_id');
    }

    public function test_sin_tema_exige_un_titulo(): void
    {
        $curso = Curso::factory()->create();

        $this->postJson("/api/admin/cursos/{$curso->id}/sesiones", ['fecha' => '2026-10-21'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('titulo');

        $this->postJson("/api/admin/cursos/{$curso->id}/sesiones", ['titulo' => 'Evaluación final', 'fecha' => '2026-10-21'])
            ->assertCreated()
            ->assertJsonPath('temario_id', null);
    }

    public function test_renombrar_el_tema_actualiza_sus_sesiones(): void
    {
        $curso = Curso::factory()->create();
        $tema = Temario::create(['curso_id' => $curso->id, 'titulo' => 'Tema viejo', 'orden' => 1]);
        $sesion = $this->postJson("/api/admin/cursos/{$curso->id}/sesiones", [
            'temario_id' => $tema->id,
            'fecha' => '2026-10-21',
        ])->json('id');

        $this->putJson("/api/admin/cursos/{$curso->id}/temario/{$tema->id}", ['titulo' => 'Tema nuevo'])->assertOk();

        $this->assertDatabaseHas('sesiones', ['id' => $sesion, 'titulo' => 'Tema nuevo', 'temario_id' => $tema->id]);
    }

    public function test_editar_sin_cambiar_el_titulo_lo_conserva(): void
    {
        $curso = Curso::factory()->create();
        $tema = Temario::create(['curso_id' => $curso->id, 'titulo' => 'Color', 'orden' => 1]);
        $sesion = $this->postJson("/api/admin/cursos/{$curso->id}/sesiones", [
            'temario_id' => $tema->id,
            'fecha' => '2026-10-21',
        ])->json('id');

        $this->putJson("/api/admin/cursos/{$curso->id}/sesiones/{$sesion}", ['estado' => 'realizada'])
            ->assertOk()
            ->assertJsonPath('titulo', 'Color')
            ->assertJsonPath('temario_id', $tema->id);
    }
}
