<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Estudiante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_cursos_abiertos_sin_sesion_y_sin_datos_internos(): void
    {
        $this->travelTo('2026-09-25 10:00:00');
        $abierto = Curso::factory()->create(['estado' => 'activo', 'fecha_inicio' => '2026-10-05', 'fecha_fin' => '2026-12-11', 'limite_cupo' => 10]);
        $sinFechas = Curso::factory()->create(['estado' => 'activo']);
        Curso::factory()->create(['estado' => 'inactivo']);
        Curso::factory()->create(['estado' => 'activo', 'fecha_fin' => '2026-09-01']);
        Estudiante::factory()->create(['curso_id' => $abierto->id]);

        $res = $this->getJson('/api/publico/cursos')->assertOk();

        // Primero los que tienen fecha de inicio; los terminados e inactivos no salen.
        $this->assertSame([$abierto->id, $sinFechas->id], array_column($res->json('data'), 'id'));
        $this->assertSame(9, $res->json('data.0.cupos_restantes'));
        $this->assertSame('presencial', $res->json('data.0.modalidad'));
        $this->assertArrayNotHasKey('estudiantes', $res->json('data.0'));
        $this->assertArrayNotHasKey('instructor', $res->json('data.0'));
        $this->assertArrayNotHasKey('profesor_id', $res->json('data.0'));
    }

    public function test_muestra_como_mucho_seis(): void
    {
        Curso::factory()->count(8)->create(['estado' => 'activo']);

        $this->getJson('/api/publico/cursos')->assertOk()->assertJsonCount(6, 'data');
    }
}
