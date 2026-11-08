<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Profesor;
use App\Models\Sesion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Reglas de cardinalidad que garantiza la base de datos o la API. */
class IntegridadEsquemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_no_puede_tener_dos_fichas_de_estudiante(): void
    {
        $estudiante = Estudiante::factory()->create();

        $this->expectException(QueryException::class);
        Estudiante::factory()->create(['user_id' => $estudiante->user_id]);
    }

    public function test_un_usuario_no_puede_tener_dos_fichas_de_profesor(): void
    {
        $profesor = Profesor::factory()->create();

        $this->expectException(QueryException::class);
        Profesor::factory()->create(['user_id' => $profesor->user_id]);
    }

    public function test_la_base_impide_dos_cursos_actuales(): void
    {
        $estudiante = Estudiante::factory()->enCurso(Curso::factory()->create()->id)->create();

        $this->expectException(QueryException::class);
        Inscripcion::create([
            'estudiante_id' => $estudiante->id,
            'curso_id' => Curso::factory()->create()->id,
            'estado_pago' => 'aprobado',
            'es_actual' => true,
        ]);
    }

    public function test_cambiar_de_curso_conserva_el_anterior_sin_marcarlo_actual(): void
    {
        $anterior = Curso::factory()->create();
        $nuevo = Curso::factory()->create();
        $estudiante = Estudiante::factory()->enCurso($anterior->id)->create();

        $estudiante->asignarCursoActual($nuevo->id);

        $this->assertSame($nuevo->id, $estudiante->fresh()->curso_id);
        $this->assertSame(2, $estudiante->cursos()->count());
        $this->assertSame(1, $estudiante->inscripciones()->where('es_actual', true)->count());
    }

    public function test_no_se_registra_asistencia_de_quien_no_esta_inscrito(): void
    {
        $sesion = $this->sesion();
        $ajeno = Estudiante::factory()->enCurso(Curso::factory()->create()->id)->create();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson("/api/admin/sesiones/{$sesion->id}/asistencia", [
            'asistencia' => [['estudiante_id' => $ajeno->id, 'presente' => true]],
        ])->assertUnprocessable()->assertJsonValidationErrors('asistencia.0.estudiante_id');

        $this->assertSame(0, Asistencia::count());
    }

    public function test_si_se_registra_asistencia_de_un_inscrito(): void
    {
        $sesion = $this->sesion();
        $inscrito = Estudiante::factory()->enCurso($sesion->curso_id)->create();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $this->postJson("/api/admin/sesiones/{$sesion->id}/asistencia", [
            'asistencia' => [['estudiante_id' => $inscrito->id, 'presente' => true]],
        ])->assertOk();

        $this->assertSame(1, Asistencia::count());
    }

    public function test_borrar_un_profesor_deja_el_curso_sin_instructor(): void
    {
        $curso = Curso::factory()->create();

        Profesor::find($curso->profesor_id)->forceDelete();

        $this->assertNull($curso->fresh()->profesor_id);
    }

    public function test_el_filtro_sin_instructor_encuentra_cursos(): void
    {
        $sinInstructor = Curso::factory()->create();
        Profesor::find($sinInstructor->profesor_id)->forceDelete();
        Curso::factory()->create();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $data = $this->getJson('/api/admin/cursos?profesor_id=sin_instructor')->assertOk()->json('data');

        $this->assertSame([$sinInstructor->id], array_column($data, 'id'));
    }

    public function test_quitar_del_curso_conserva_los_pagos(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->enCurso($curso->id)->create();
        $pago = $this->registrarPago(['user_id' => $estudiante->user_id, 'curso_id' => $curso->id, 'metodo_pago' => 'efectivo', 'estado' => 'aprobado']);

        $estudiante->quitarDeCurso($curso->id);

        $this->assertNull($estudiante->fresh()->curso_id);
        $this->assertSame($curso->id, $pago->fresh()->curso_id);
        $this->assertSame(0, $curso->matriculas()->count());
    }

    public function test_quitar_del_curso_sin_pagos_borra_la_inscripcion(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->enCurso($curso->id)->create();

        $estudiante->quitarDeCurso($curso->id);

        $this->assertSame(0, Inscripcion::withTrashed()->count());
    }

    public function test_volver_a_un_curso_del_que_lo_quitaron_no_hereda_el_pago(): void
    {
        Notification::fake();
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->enCurso($curso->id)->create();
        $this->registrarPago(['user_id' => $estudiante->user_id, 'curso_id' => $curso->id, 'metodo_pago' => 'efectivo', 'estado' => 'rechazado']);
        $estudiante->quitarDeCurso($curso->id);

        Sanctum::actingAs($estudiante->user);
        $this->postJson('/api/estudiante/pagos', ['curso_id' => $curso->id, 'metodo_pago' => 'efectivo'])->assertCreated();

        $this->assertSame('pendiente', Inscripcion::where('curso_id', $curso->id)->value('estado_pago'));
        $this->assertSame(0, $curso->estudiantes()->count());
    }

    public function test_la_base_impide_borrar_una_inscripcion_con_pagos(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->enCurso($curso->id)->create();
        $pago = $this->registrarPago(['user_id' => $estudiante->user_id, 'curso_id' => $curso->id, 'metodo_pago' => 'efectivo', 'estado' => 'aprobado']);

        $this->expectException(QueryException::class);
        Inscripcion::find($pago->inscripcion_id)->forceDelete();
    }

    public function test_el_filtro_de_estado_de_pago_coincide_con_el_dato_expuesto(): void
    {
        $curso = Curso::factory()->create();
        $aprobado = Estudiante::factory()->enCurso($curso->id)->create();
        $rechazado = Estudiante::factory()->create();
        $rechazado->retirarDeCurso($curso->id, 'rechazado');
        $sinSolicitud = Estudiante::factory()->create();

        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        foreach (['aprobado' => $aprobado, 'rechazado' => $rechazado, 'pendiente' => $sinSolicitud] as $estado => $esperado) {
            $data = $this->getJson("/api/admin/estudiantes?estado_pago={$estado}")->assertOk()->json('data');

            $this->assertSame([$esperado->id], array_column($data, 'id'));
            $this->assertSame($estado, $data[0]['estado_pago']);
        }
    }

    private function sesion(): Sesion
    {
        return Sesion::create([
            'curso_id' => Curso::factory()->create()->id,
            'titulo' => 'Clase',
            'fecha' => today(),
        ]);
    }
}
