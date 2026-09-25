<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Pago;
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

    private function pago(Curso $curso, string $estado, string $fecha, string $metodo = 'efectivo'): void
    {
        $pago = Pago::create([
            'user_id' => Estudiante::factory()->create()->user_id,
            'curso_id' => $curso->id,
            'metodo_pago' => $metodo,
            'referencia' => '',
            'banco_origen' => '',
            'comprobante' => '',
            'estado' => $estado,
        ]);
        $pago->created_at = $fecha;
        $pago->save();
    }

    public function test_la_serie_mensual_es_continua_y_rellena_con_ceros(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $curso = Curso::factory()->create(['precio' => 100]);
        $this->pago($curso, 'aprobado', '2026-09-10');
        $this->pago($curso, 'aprobado', '2026-06-02');
        $this->pago($curso, 'rechazado', '2026-06-03');

        $serie = collect($this->getJson('/api/admin/reportes?periodo=mensual')->assertOk()->json('ingresos'));

        $this->assertCount(12, $serie);
        $this->assertSame('2025-10', $serie->first()['label']);
        $this->assertSame('2026-09', $serie->last()['label']);
        $junio = $serie->firstWhere('label', '2026-06');
        $this->assertEquals(100, $junio['total']);
        $this->assertSame(1, $junio['rechazados']);
        // Julio no tuvo pagos pero aparece con cero.
        $this->assertEquals(0, $serie->firstWhere('label', '2026-07')['total']);
    }

    public function test_el_resumen_del_periodo_compara_con_la_ventana_anterior(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $curso = Curso::factory()->create(['precio' => 50]);
        // Ventana semanal actual: 12 semanas hasta la semana del 21-sep.
        $this->pago($curso, 'aprobado', '2026-09-22', 'pago_movil');
        $this->pago($curso, 'aprobado', '2026-09-01', 'transferencia');
        $this->pago($curso, 'pendiente', '2026-09-23');
        // Ventana anterior.
        $this->pago($curso, 'aprobado', '2026-05-20');

        $res = $this->getJson('/api/admin/reportes?periodo=semanal')->assertOk();

        $this->assertEquals(100, $res->json('periodo.actual.ingresos'));
        $this->assertSame(1, $res->json('periodo.actual.pendientes'));
        $this->assertEquals(50, $res->json('periodo.anterior.ingresos'));
        $this->assertEqualsCanonicalizing(
            ['pago_movil', 'transferencia'],
            collect($res->json('periodo.metodos_pago'))->pluck('metodo')->all(),
        );
        $this->assertCount(12, $res->json('ingresos'));
    }

    public function test_un_periodo_desconocido_usa_el_mensual(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $this->getJson('/api/admin/reportes?periodo=diario')
            ->assertOk()
            ->assertJsonCount(12, 'ingresos');
    }
}
