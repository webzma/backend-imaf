<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Profesor;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AsistenciaTest extends TestCase
{
    use RefreshDatabase;

    private function crearSesionConCurso(): Sesion
    {
        $curso = Curso::factory()->create();

        return Sesion::create([
            'curso_id' => $curso->id,
            'titulo' => 'Sesión de prueba',
            'fecha' => '2026-07-10',
        ]);
    }

    public function test_admin_puede_ver_la_asistencia_de_una_sesion(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $sesion = $this->crearSesionConCurso();
        Estudiante::factory()->create(['curso_id' => $sesion->curso_id]);

        $this->getJson("/api/sesiones/{$sesion->id}/asistencia")
            ->assertOk()
            ->assertJsonStructure([
                'sesion' => ['id', 'titulo', 'curso_nombre'],
                'asistencia' => [['estudiante_id', 'nombre', 'presente']],
            ]);
    }

    public function test_profesor_dueno_del_curso_puede_guardar_asistencia(): void
    {
        $profesor = Profesor::factory()->create();
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $sesion = Sesion::create([
            'curso_id' => $curso->id,
            'titulo' => 'Sesión del profe',
            'fecha' => '2026-07-11',
        ]);
        $estudiante = Estudiante::factory()->create(['curso_id' => $curso->id]);

        Sanctum::actingAs($profesor->user);

        $this->postJson("/api/sesiones/{$sesion->id}/asistencia", [
            'asistencia' => [
                ['estudiante_id' => $estudiante->id, 'presente' => true, 'observacion' => 'A tiempo'],
            ],
        ])->assertOk()->assertJson(['message' => 'Asistencia guardada.']);

        $this->assertDatabaseHas('asistencias', [
            'sesion_id' => $sesion->id,
            'estudiante_id' => $estudiante->id,
            'presente' => true,
        ]);
    }

    public function test_profesor_no_puede_ver_asistencia_de_curso_ajeno(): void
    {
        $profesorAjeno = Profesor::factory()->create();
        $sesion = $this->crearSesionConCurso(); // curso de OTRO profesor

        Sanctum::actingAs($profesorAjeno->user);

        $this->getJson("/api/sesiones/{$sesion->id}/asistencia")
            ->assertStatus(403)
            ->assertJson(['message' => 'No autorizado.']);
    }

    public function test_estudiante_no_puede_acceder_a_la_asistencia(): void
    {
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        Sanctum::actingAs($estudiante);

        $sesion = $this->crearSesionConCurso();

        $this->getJson("/api/sesiones/{$sesion->id}/asistencia")
            ->assertStatus(403);
    }
}
