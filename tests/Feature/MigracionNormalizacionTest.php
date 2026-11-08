<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/** Las migraciones de normalización, aplicadas sobre datos del esquema anterior. */
class MigracionNormalizacionTest extends TestCase
{
    use DatabaseMigrations;

    private const PASOS = 7;

    private int $cursoA;

    private int $cursoB;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate:rollback', ['--step' => self::PASOS]);

        $profesor = DB::table('profesores')->insertGetId(['user_id' => $this->usuario('profe'), 'cedula' => 'P1']);
        $this->cursoA = $this->curso($profesor, 'A');
        $this->cursoB = $this->curso($profesor, 'B');
    }

    public function test_el_curso_actual_pasa_a_la_inscripcion(): void
    {
        $id = $this->estudiante('ana', cursoId: $this->cursoA);
        $this->inscripcion($id, $this->cursoA, 'aprobado', aprobacion: 'aprobado');
        $this->inscripcion($id, $this->cursoB, 'aprobado');

        $this->migrar();

        $this->assertSame([$this->cursoA], DB::table('inscripciones')->where('es_actual', 1)->pluck('curso_id')->all());
        $this->assertFalse(Schema::hasColumn('estudiantes', 'curso_id'));
        $this->assertFalse(Schema::hasColumn('estudiantes', 'estado_pago'));
        $this->assertTrue(Schema::hasColumn('estudiantes', 'fecha_inscripcion'));
    }

    public function test_un_curso_actual_sin_pagar_no_queda_marcado(): void
    {
        $id = $this->estudiante('ana', cursoId: $this->cursoA);
        $this->inscripcion($id, $this->cursoA, 'pendiente');

        $this->migrar();

        $this->assertSame(0, DB::table('inscripciones')->where('es_actual', 1)->count());
    }

    public function test_reprobado_pasa_a_rechazado_solo_en_el_pago(): void
    {
        $id = $this->estudiante('ana');
        $this->inscripcion($id, $this->cursoA, 'reprobado', aprobacion: 'reprobado');

        $this->migrar();

        $fila = DB::table('inscripciones')->first();
        $this->assertSame('rechazado', $fila->estado_pago);
        $this->assertSame('reprobado', $fila->estado_aprobacion_curso);
    }

    public function test_cada_pago_queda_vinculado_a_su_inscripcion(): void
    {
        $id = $this->estudiante('ana');
        $inscripcion = $this->inscripcion($id, $this->cursoA, 'aprobado');
        $pago = $this->pago('ana', $this->cursoA, 'aprobado');
        $eliminado = $this->pago('ana', $this->cursoA, 'rechazado', borrado: true);

        $this->migrar();

        $this->assertSame($inscripcion, DB::table('pagos')->where('id', $pago)->value('inscripcion_id'));
        $this->assertSame($inscripcion, DB::table('pagos')->where('id', $eliminado)->value('inscripcion_id'));
        $this->assertFalse(Schema::hasColumn('pagos', 'user_id'));
        $this->assertFalse(Schema::hasColumn('pagos', 'curso_id'));
    }

    public function test_un_pago_sin_inscripcion_crea_una_retirada(): void
    {
        $id = $this->estudiante('ana');
        $pago = $this->pago('ana', $this->cursoB, 'rechazado');

        $this->migrar();

        $inscripcion = DB::table('inscripciones')->where('id', DB::table('pagos')->where('id', $pago)->value('inscripcion_id'))->first();
        $this->assertSame($id, $inscripcion->estudiante_id);
        $this->assertSame($this->cursoB, $inscripcion->curso_id);
        $this->assertSame('rechazado', $inscripcion->estado_pago);
        $this->assertNotNull($inscripcion->deleted_at);
    }

    public function test_no_toca_nada_si_un_usuario_tiene_dos_fichas(): void
    {
        $this->estudiante('ana');
        DB::table('estudiantes')->insert([
            'user_id' => DB::table('users')->where('email', 'ana@test.com')->value('id'),
            'nombre' => 'Copia', 'cedula' => 'copia', 'fecha_inscripcion' => '2026-01-01',
        ]);

        try {
            $this->migrar();
            $this->fail('La migración debía detenerse.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('estudiantes', $e->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('estudiantes', 'curso_id'));
        $this->assertTrue(Schema::hasColumn('pagos', 'user_id'));
    }

    public function test_se_puede_revertir_conservando_los_datos(): void
    {
        $id = $this->estudiante('ana', cursoId: $this->cursoA);
        $this->inscripcion($id, $this->cursoA, 'aprobado', aprobacion: 'aprobado');
        $pago = $this->pago('ana', $this->cursoA, 'aprobado');

        $this->migrar();
        Artisan::call('migrate:rollback', ['--step' => self::PASOS]);

        $estudiante = DB::table('estudiantes')->where('id', $id)->first();
        $this->assertSame($this->cursoA, $estudiante->curso_id);
        $this->assertSame('aprobado', $estudiante->estado_aprobacion_curso);
        $this->assertSame($this->cursoA, DB::table('pagos')->where('id', $pago)->value('curso_id'));
        $this->assertTrue(Schema::hasTable('bitacora'));
    }

    private function migrar(): void
    {
        Artisan::call('migrate', ['--force' => true]);
    }

    private function usuario(string $alias): int
    {
        return DB::table('users')->insertGetId(['name' => $alias, 'email' => "{$alias}@test.com", 'password' => 'x', 'role' => 'estudiante']);
    }

    private function curso(int $profesor, string $codigo): int
    {
        return DB::table('cursos')->insertGetId(['profesor_id' => $profesor, 'nombre' => $codigo, 'codigo' => $codigo]);
    }

    private function estudiante(string $alias, ?int $cursoId = null): int
    {
        return DB::table('estudiantes')->insertGetId([
            'user_id' => $this->usuario($alias),
            'curso_id' => $cursoId,
            'nombre' => $alias,
            'cedula' => $alias,
            'fecha_inscripcion' => '2026-01-01',
        ]);
    }

    private function inscripcion(int $estudiante, int $curso, string $pago, string $aprobacion = 'pendiente'): int
    {
        return DB::table('inscripciones')->insertGetId([
            'estudiante_id' => $estudiante,
            'curso_id' => $curso,
            'estado_pago' => $pago,
            'estado_aprobacion_curso' => $aprobacion,
        ]);
    }

    private function pago(string $alias, int $curso, string $estado, bool $borrado = false): int
    {
        return DB::table('pagos')->insertGetId([
            'user_id' => DB::table('users')->where('email', "{$alias}@test.com")->value('id'),
            'curso_id' => $curso,
            'estado' => $estado,
            'created_at' => '2026-02-03 10:00:00',
            'deleted_at' => $borrado ? '2026-02-04 10:00:00' : null,
        ]);
    }
}
