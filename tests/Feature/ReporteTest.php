<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_totales_del_reporte_cubren_toda_la_base(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $curso = Curso::factory()->create(['estado' => 'activo']);
        Curso::factory()->create(['estado' => 'inactivo']);
        Profesor::factory()->count(2)->create();

        // Más de una página: la pantalla calculaba estos totales sobre los 10
        // primeros registros que le llegaban.
        Estudiante::factory()->count(14)->create([
            'curso_id' => $curso->id,
            'estado' => 'activo',
        ]);
        Estudiante::factory()->count(2)->create(['estado' => 'graduado']);

        $res = $this->getJson('/api/admin/reportes')->assertOk();

        $this->assertSame(16, $res->json('totales.estudiantes'));
        $this->assertSame(2, $res->json('totales.cursos'));
        $this->assertSame(14, $res->json('estado_estudiantes.activo'));
        $this->assertSame(2, $res->json('estado_estudiantes.graduado'));
        $this->assertSame(1, $res->json('estado_cursos.activo'));
        $this->assertSame(1, $res->json('estado_cursos.inactivo'));
        $this->assertSame(14, $res->json('cursos.0.estudiantes'));
    }

    public function test_los_instructores_se_cuentan_aparte_de_los_usuarios(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        Profesor::factory()->count(3)->create();

        $this->getJson('/api/admin/reportes')
            ->assertOk()
            ->assertJsonPath('totales.instructores', 3);
    }
}
