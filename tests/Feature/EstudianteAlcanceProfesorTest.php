<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * `GET /api/estudiantes` y `GET /api/estudiantes/{id}` los comparten admin y
 * profesor. El profesor solo debe ver a los inscritos en los cursos que dicta:
 * esos registros llevan cédula, dirección y teléfono.
 */
class EstudianteAlcanceProfesorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Crea un profesor con un curso y un estudiante inscrito en él.
     *
     * @return array{0: Profesor, 1: Curso, 2: Estudiante}
     */
    private function profesorConAlumno(): array
    {
        $profesor = Profesor::factory()->create();
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $estudiante = Estudiante::factory()->create(['curso_id' => $curso->id]);

        return [$profesor, $curso, $estudiante];
    }

    public function test_profesor_solo_lista_estudiantes_de_sus_cursos(): void
    {
        [$profesor, , $propio] = $this->profesorConAlumno();
        [, , $ajeno] = $this->profesorConAlumno();

        Sanctum::actingAs($profesor->user);

        $response = $this->getJson('/api/estudiantes')->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($propio->id, $ids);
        $this->assertNotContains($ajeno->id, $ids);
        $this->assertSame(1, $response->json('total'));
    }

    public function test_profesor_no_lista_estudiantes_sin_curso(): void
    {
        $profesor = Profesor::factory()->create();
        Curso::factory()->create(['profesor_id' => $profesor->id]);
        $sinCurso = Estudiante::factory()->create(['curso_id' => null]);

        Sanctum::actingAs($profesor->user);

        $ids = array_column($this->getJson('/api/estudiantes')->assertOk()->json('data'), 'id');

        $this->assertNotContains($sinCurso->id, $ids);
    }

    public function test_profesor_puede_ver_el_detalle_de_su_estudiante(): void
    {
        [$profesor, , $propio] = $this->profesorConAlumno();

        Sanctum::actingAs($profesor->user);

        $this->getJson("/api/estudiantes/{$propio->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $propio->id]);
    }

    public function test_profesor_no_puede_ver_el_detalle_de_un_estudiante_ajeno(): void
    {
        [$profesor] = $this->profesorConAlumno();
        [, , $ajeno] = $this->profesorConAlumno();

        Sanctum::actingAs($profesor->user);

        $this->getJson("/api/estudiantes/{$ajeno->id}")->assertNotFound();
    }

    public function test_admin_sigue_viendo_a_todos_los_estudiantes(): void
    {
        [, , $unoA] = $this->profesorConAlumno();
        [, , $unoB] = $this->profesorConAlumno();
        $sinCurso = Estudiante::factory()->create(['curso_id' => null]);

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $ids = array_column($this->getJson('/api/estudiantes')->assertOk()->json('data'), 'id');

        $this->assertContains($unoA->id, $ids);
        $this->assertContains($unoB->id, $ids);
        $this->assertContains($sinCurso->id, $ids);
    }

    public function test_admin_ve_el_detalle_de_cualquier_estudiante(): void
    {
        [, , $estudiante] = $this->profesorConAlumno();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson("/api/admin/estudiantes/{$estudiante->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $estudiante->id]);
    }
}
