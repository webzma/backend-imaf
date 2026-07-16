<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Profesor;
use App\Models\Sesion;
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

    /**
     * Prepara un curso con su instructor autenticado, un estudiante inscrito
     * y una sesión realizada. Devuelve [estudiante, sesión].
     */
    private function prepararCursoConSesionRealizada(): array
    {
        $profesor = Profesor::factory()->create();
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $estudiante = Estudiante::factory()->create(['curso_id' => $curso->id]);
        $sesion = Sesion::create([
            'curso_id' => $curso->id,
            'titulo' => 'Clase 1',
            'fecha' => '2026-07-01',
            'estado' => 'realizada',
        ]);

        Sanctum::actingAs($profesor->user);

        return [$estudiante, $sesion];
    }

    public function test_no_se_puede_aprobar_estudiante_con_inasistencias(): void
    {
        [$estudiante] = $this->prepararCursoConSesionRealizada();
        // Sin registro de asistencia para la sesión realizada = inasistencia

        $this->patchJson("/api/profesor/estudiantes/{$estudiante->id}/aprobacion-curso", [
            'estado_aprobacion_curso' => 'aprobado',
        ])->assertStatus(422);

        $this->assertSame('pendiente', $estudiante->fresh()->estado_aprobacion_curso);
    }

    public function test_no_se_puede_aprobar_estudiante_marcado_ausente(): void
    {
        [$estudiante, $sesion] = $this->prepararCursoConSesionRealizada();
        Asistencia::create([
            'sesion_id' => $sesion->id,
            'estudiante_id' => $estudiante->id,
            'presente' => false,
        ]);

        $this->patchJson("/api/profesor/estudiantes/{$estudiante->id}/aprobacion-curso", [
            'estado_aprobacion_curso' => 'aprobado',
        ])->assertStatus(422);
    }

    public function test_se_puede_aprobar_estudiante_con_asistencia_completa(): void
    {
        [$estudiante, $sesion] = $this->prepararCursoConSesionRealizada();
        Asistencia::create([
            'sesion_id' => $sesion->id,
            'estudiante_id' => $estudiante->id,
            'presente' => true,
        ]);

        $this->patchJson("/api/profesor/estudiantes/{$estudiante->id}/aprobacion-curso", [
            'estado_aprobacion_curso' => 'aprobado',
        ])->assertOk();

        $this->assertSame('aprobado', $estudiante->fresh()->estado_aprobacion_curso);
    }

    public function test_se_puede_reprobar_estudiante_sin_importar_asistencia(): void
    {
        [$estudiante] = $this->prepararCursoConSesionRealizada();

        $this->patchJson("/api/profesor/estudiantes/{$estudiante->id}/aprobacion-curso", [
            'estado_aprobacion_curso' => 'reprobado',
        ])->assertOk();

        $this->assertSame('reprobado', $estudiante->fresh()->estado_aprobacion_curso);
    }

    public function test_sesiones_programadas_o_canceladas_no_bloquean_la_aprobacion(): void
    {
        [$estudiante, $sesion] = $this->prepararCursoConSesionRealizada();
        Asistencia::create([
            'sesion_id' => $sesion->id,
            'estudiante_id' => $estudiante->id,
            'presente' => true,
        ]);
        // Sesiones futuras o canceladas sin asistencia registrada no cuentan
        Sesion::create([
            'curso_id' => $estudiante->curso_id,
            'titulo' => 'Clase futura',
            'fecha' => '2026-12-01',
            'estado' => 'programada',
        ]);
        Sesion::create([
            'curso_id' => $estudiante->curso_id,
            'titulo' => 'Clase cancelada',
            'fecha' => '2026-07-05',
            'estado' => 'cancelada',
        ]);

        $this->patchJson("/api/profesor/estudiantes/{$estudiante->id}/aprobacion-curso", [
            'estado_aprobacion_curso' => 'aprobado',
        ])->assertOk();
    }
}
