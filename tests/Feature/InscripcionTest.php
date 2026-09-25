<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Inscripcion;
use App\Models\Pago;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Un estudiante puede cursar varios cursos: el actual vive en `curso_id` y
 * todos quedan en `inscripciones`.
 */
class InscripcionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function pagoPendiente(Estudiante $estudiante, Curso $curso): Pago
    {
        return Pago::create([
            'user_id' => $estudiante->user_id,
            'curso_id' => $curso->id,
            'metodo_pago' => 'efectivo',
            'referencia' => '',
            'banco_origen' => '',
            'comprobante' => '',
            'estado' => 'pendiente',
        ]);
    }

    private function resolverPago(Pago $pago, string $estado, ?string $nota = null)
    {
        Sanctum::actingAs($this->admin);

        return $this->putJson("/api/admin/pagos/{$pago->id}", array_filter([
            'estado' => $estado,
            'nota_admin' => $nota,
        ]));
    }

    public function test_aprobar_pago_notifica_al_estudiante(): void
    {
        $estudiante = Estudiante::factory()->create();
        $curso = Curso::factory()->create(['nombre' => 'Dibujo']);

        $this->resolverPago($this->pagoPendiente($estudiante, $curso), 'aprobado')->assertOk();

        Notification::assertSentTo(
            $estudiante->user,
            GenericNotification::class,
            fn ($n) => $n->titulo === '¡Pago Aprobado!' && str_contains($n->mensaje, 'Dibujo'),
        );
    }

    public function test_rechazar_pago_notifica_al_estudiante_con_el_motivo(): void
    {
        $estudiante = Estudiante::factory()->create();
        $curso = Curso::factory()->create();

        $this->resolverPago($this->pagoPendiente($estudiante, $curso), 'rechazado', 'Referencia inválida')
            ->assertOk();

        Notification::assertSentTo(
            $estudiante->user,
            GenericNotification::class,
            fn ($n) => $n->titulo === 'Pago Rechazado' && str_contains($n->mensaje, 'Referencia inválida'),
        );
    }

    public function test_rechazar_un_pago_aprobado_retira_al_estudiante_del_curso(): void
    {
        $anterior = Curso::factory()->create();
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create(['curso_id' => $anterior->id]);
        $pago = $this->pagoPendiente($estudiante, $curso);
        $this->resolverPago($pago, 'aprobado')->assertOk();

        $this->resolverPago($pago, 'rechazado')->assertOk();

        $estudiante->refresh();
        // Vuelve a su curso anterior y ya no figura en el rechazado.
        $this->assertSame($anterior->id, $estudiante->curso_id);
        $this->assertSame('aprobado', $estudiante->estado_pago);
        $this->assertFalse($estudiante->cursos()->whereKey($curso->id)->exists());
        $this->assertSame(0, $curso->estudiantes()->count());
    }

    public function test_rechazar_el_pago_de_un_curso_asignado_a_mano_lo_deja_sin_curso(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create(['curso_id' => $curso->id]);

        $this->resolverPago($this->pagoPendiente($estudiante, $curso), 'rechazado')->assertOk();

        $estudiante->refresh();
        $this->assertNull($estudiante->curso_id);
        $this->assertSame('reprobado', $estudiante->estado_pago);
        $this->assertSame(0, $estudiante->cursos()->count());

        Sanctum::actingAs($estudiante->user);
        $this->getJson('/api/estudiante/curso')->assertNotFound();
    }

    public function test_rechazar_un_pago_repetido_no_retira_si_hay_otro_aprobado(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $this->resolverPago($this->pagoPendiente($estudiante, $curso), 'aprobado')->assertOk();

        $duplicado = $this->pagoPendiente($estudiante, $curso);
        $this->resolverPago($duplicado, 'rechazado')->assertOk();

        $this->assertSame($curso->id, $estudiante->fresh()->curso_id);
    }

    /** Listado interno del curso visto por el admin, indexado por estudiante. */
    private function listadoInterno(Curso $curso)
    {
        Sanctum::actingAs($this->admin);

        return collect($this->getJson("/api/admin/cursos/{$curso->id}")->assertOk()->json('estudiantes'))
            ->keyBy('id');
    }

    public function test_el_listado_interno_incluye_a_quien_no_ha_pagado(): void
    {
        $curso = Curso::factory()->create(['limite_cupo' => 5]);
        $pagado = Estudiante::factory()->create(['curso_id' => $curso->id]);
        $pendiente = Estudiante::factory()->create();
        $rechazado = Estudiante::factory()->create();

        Sanctum::actingAs($pendiente->user);
        $this->postJson('/api/estudiante/pagos', ['curso_id' => $curso->id, 'metodo_pago' => 'efectivo'])
            ->assertCreated();
        $this->resolverPago($this->pagoPendiente($rechazado, $curso), 'rechazado')->assertOk();

        $listado = $this->listadoInterno($curso);

        $this->assertSame('aprobado', $listado[$pagado->id]['estado_pago']);
        $this->assertSame('pendiente', $listado[$pendiente->id]['estado_pago']);
        $this->assertSame('reprobado', $listado[$rechazado->id]['estado_pago']);
        // Solo el que pagó ocupa cupo.
        $this->assertSame(4, $curso->fresh()->cupos_restantes);
    }

    public function test_el_listado_interno_incluye_inactivos_y_archivados(): void
    {
        $curso = Curso::factory()->create();
        $inactivo = Estudiante::factory()->create(['curso_id' => $curso->id, 'estado' => 'inactivo']);
        $archivado = Estudiante::factory()->create(['curso_id' => $curso->id]);
        $archivado->delete();

        $listado = $this->listadoInterno($curso);

        $this->assertSame('inactivo', $listado[$inactivo->id]['estado']);
        $this->assertNotNull($listado[$archivado->id]['deleted_at']);
    }

    public function test_el_instructor_ve_el_listado_completo_y_el_estudiante_no(): void
    {
        $curso = Curso::factory()->create();
        $pagado = Estudiante::factory()->create(['curso_id' => $curso->id]);
        $rechazado = Estudiante::factory()->create();
        $this->resolverPago($this->pagoPendiente($rechazado, $curso), 'rechazado')->assertOk();

        Sanctum::actingAs($curso->instructor->user);
        $ids = collect($this->getJson("/api/cursos/{$curso->id}")->json('estudiantes'))->pluck('id');
        $this->assertEqualsCanonicalizing([$pagado->id, $rechazado->id], $ids->all());
        $this->getJson("/api/estudiantes/{$rechazado->id}")->assertOk();

        Sanctum::actingAs($pagado->user);
        $ids = collect($this->getJson("/api/estudiante/cursos/{$curso->id}")->json('estudiantes'))->pluck('id');
        $this->assertSame([$pagado->id], $ids->all());
    }

    public function test_admin_inscribe_y_quita_estudiantes_desde_el_curso(): void
    {
        $anterior = Curso::factory()->create();
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create(['curso_id' => $anterior->id]);

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/cursos/{$curso->id}/estudiantes", ['estudiante_id' => $estudiante->id])
            ->assertCreated()
            ->assertJsonPath('estado_pago', 'aprobado');
        $this->assertSame($curso->id, $estudiante->fresh()->curso_id);

        $this->deleteJson("/api/admin/cursos/{$curso->id}/estudiantes/{$estudiante->id}")->assertOk();

        $this->assertSame($anterior->id, $estudiante->fresh()->curso_id);
        $this->assertFalse($this->listadoInterno($curso)->has($estudiante->id));
    }

    public function test_admin_puede_dar_por_pagada_una_inscripcion_rechazada(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create();
        $this->resolverPago($this->pagoPendiente($estudiante, $curso), 'rechazado')->assertOk();

        $this->postJson("/api/admin/cursos/{$curso->id}/estudiantes", ['estudiante_id' => $estudiante->id])
            ->assertCreated();

        $this->assertSame('aprobado', $this->listadoInterno($curso)[$estudiante->id]['estado_pago']);
        $this->assertSame($curso->id, $estudiante->fresh()->curso_id);
    }

    public function test_inscribir_respeta_el_cupo(): void
    {
        $curso = Curso::factory()->create(['limite_cupo' => 1]);
        Estudiante::factory()->create(['curso_id' => $curso->id]);
        $otro = Estudiante::factory()->create();

        Sanctum::actingAs($this->admin);
        $this->postJson("/api/admin/cursos/{$curso->id}/estudiantes", ['estudiante_id' => $otro->id])
            ->assertStatus(422);
    }

    public function test_la_api_declara_la_modalidad_presencial(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create(['curso_id' => $curso->id]);

        Sanctum::actingAs($estudiante->user);
        $this->getJson('/api/estudiante/cursos')->assertJsonPath('data.0.modalidad', 'presencial');
        $this->getJson("/api/estudiante/cursos/{$curso->id}")
            ->assertJsonPath('modalidad', 'presencial')
            ->assertJsonPath('sede', Curso::SEDE);
        $this->getJson('/api/estudiante/curso')->assertJsonPath('curso.modalidad', 'presencial');
        $this->getJson('/api/estudiante/mis-cursos')->assertJsonPath('cursos.0.modalidad', 'presencial');
    }

    public function test_con_la_solicitud_en_revision_el_estudiante_no_esta_en_el_curso(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create();

        Sanctum::actingAs($estudiante->user);
        $this->postJson('/api/estudiante/pagos', ['curso_id' => $curso->id, 'metodo_pago' => 'efectivo'])
            ->assertCreated();

        $this->assertNull($estudiante->fresh()->curso_id);
        $this->assertNull($this->getJson('/api/estudiante/perfil')->json('curso'));
        $this->getJson('/api/estudiante/curso')->assertNotFound();
        $this->getJson("/api/estudiante/curso?curso_id={$curso->id}")->assertNotFound();
        $this->assertSame(
            [$curso->id],
            collect($this->getJson('/api/estudiante/mis-cursos')->json('solicitudes_pendientes'))->pluck('curso_id')->all(),
        );
    }

    public function test_marcar_el_pago_como_no_aprobado_saca_al_estudiante_del_curso(): void
    {
        $anterior = Curso::factory()->create();
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create(['curso_id' => $anterior->id]);
        $estudiante->update(['curso_id' => $curso->id]);

        Sanctum::actingAs($this->admin);
        $this->patchJson("/api/admin/estudiantes/{$estudiante->id}/estado-pago", ['estado_pago' => 'reprobado'])
            ->assertOk();

        $estudiante->refresh();
        $this->assertSame($anterior->id, $estudiante->curso_id);
        $this->assertSame('aprobado', $estudiante->estado_pago);
        $this->assertSame('reprobado', $this->listadoInterno($curso)[$estudiante->id]['estado_pago']);

        Sanctum::actingAs($estudiante->user);
        $this->getJson('/api/estudiante/curso')
            ->assertOk()
            ->assertJsonPath('curso.id', $anterior->id)
            ->assertJsonPath('estado_pago', 'aprobado');
    }

    public function test_asignar_un_curso_deja_el_pago_al_dia(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create(['estado_pago' => 'pendiente']);

        $estudiante->update(['curso_id' => $curso->id]);

        $this->assertSame('aprobado', $estudiante->fresh()->estado_pago);
    }

    public function test_el_perfil_no_muestra_un_curso_sin_pago_aprobado(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create(['curso_id' => $curso->id]);
        // Datos antiguos: curso actual con la inscripción sin pagar.
        Inscripcion::where('estudiante_id', $estudiante->id)->update(['estado_pago' => 'reprobado']);

        Sanctum::actingAs($estudiante->user);
        $this->assertNull($this->getJson('/api/estudiante/perfil')->json('curso'));
        $this->getJson('/api/estudiante/curso')->assertNotFound();
    }

    public function test_la_migracion_alinea_el_curso_actual_con_el_pago(): void
    {
        $pagado = Curso::factory()->create();
        $rechazado = Curso::factory()->create();
        $enRevision = Curso::factory()->create();
        $manual = Curso::factory()->create();

        $conRechazo = Estudiante::factory()->create(['curso_id' => $pagado->id]);
        $conPendiente = Estudiante::factory()->create();
        $sinPagos = Estudiante::factory()->create();

        // Estado roto, como lo dejaba el código anterior.
        DB::table('estudiantes')->where('id', $conRechazo->id)
            ->update(['curso_id' => $rechazado->id, 'estado_pago' => 'reprobado']);
        DB::table('inscripciones')->insert(['estudiante_id' => $conRechazo->id, 'curso_id' => $rechazado->id, 'estado_pago' => 'aprobado', 'estado_aprobacion_curso' => 'pendiente']);
        $this->pagoPendiente($conRechazo, $rechazado)->update(['estado' => 'rechazado']);

        DB::table('estudiantes')->where('id', $conPendiente->id)
            ->update(['curso_id' => $enRevision->id, 'estado_pago' => 'pendiente']);
        $this->pagoPendiente($conPendiente, $enRevision);

        DB::table('estudiantes')->where('id', $sinPagos->id)
            ->update(['curso_id' => $manual->id, 'estado_pago' => 'pendiente']);

        (require database_path('migrations/2026_09_25_000000_alinear_curso_actual_con_pago_aprobado.php'))->up();

        $conRechazo->refresh();
        $this->assertSame($pagado->id, $conRechazo->curso_id);
        $this->assertSame('aprobado', $conRechazo->estado_pago);
        $this->assertSame('reprobado', Inscripcion::where('estudiante_id', $conRechazo->id)->where('curso_id', $rechazado->id)->value('estado_pago'));

        $conPendiente->refresh();
        $this->assertNull($conPendiente->curso_id);
        $this->assertSame('pendiente', $conPendiente->estado_pago);

        $sinPagos->refresh();
        $this->assertSame($manual->id, $sinPagos->curso_id);
        $this->assertSame('aprobado', $sinPagos->estado_pago);
    }

    public function test_aprobar_un_segundo_curso_conserva_el_anterior(): void
    {
        $primero = Curso::factory()->create();
        $segundo = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create([
            'curso_id' => $primero->id,
            'estado_aprobacion_curso' => 'aprobado',
        ]);

        $this->resolverPago($this->pagoPendiente($estudiante, $segundo), 'aprobado')->assertOk();

        $estudiante->refresh();
        $this->assertSame($segundo->id, $estudiante->curso_id);
        // La aprobación del curso anterior no pasa al nuevo.
        $this->assertSame('pendiente', $estudiante->estado_aprobacion_curso);
        $this->assertEqualsCanonicalizing(
            [$primero->id, $segundo->id],
            $estudiante->cursos()->pluck('cursos.id')->all(),
        );
        $this->assertSame('aprobado', Inscripcion::where('curso_id', $primero->id)->value('estado_aprobacion_curso'));
    }

    public function test_el_cupo_cuenta_a_quien_paso_a_otro_curso(): void
    {
        $lleno = Curso::factory()->create(['limite_cupo' => 1]);
        $otro = Curso::factory()->create();
        $antiguo = Estudiante::factory()->create(['curso_id' => $lleno->id]);
        $antiguo->update(['curso_id' => $otro->id]);

        $nuevo = Estudiante::factory()->create();

        $this->resolverPago($this->pagoPendiente($nuevo, $lleno), 'aprobado')->assertStatus(422);
    }

    public function test_solicitar_otro_curso_no_quita_el_acceso_al_actual(): void
    {
        $actual = Curso::factory()->create();
        $otro = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create([
            'curso_id' => $actual->id,
            'estado_pago' => 'aprobado',
        ]);

        Sanctum::actingAs($estudiante->user);
        $this->postJson('/api/estudiante/pagos', [
            'curso_id' => $otro->id,
            'metodo_pago' => 'efectivo',
        ])->assertCreated();

        $this->assertSame('aprobado', $estudiante->fresh()->estado_pago);

        $this->resolverPago(Pago::latest('id')->first(), 'rechazado')->assertOk();

        $this->assertSame('aprobado', $estudiante->fresh()->estado_pago);
        $this->assertSame($actual->id, $estudiante->fresh()->curso_id);
    }

    public function test_mis_cursos_lista_el_historial_y_las_solicitudes_pendientes(): void
    {
        $primero = Curso::factory()->create();
        $segundo = Curso::factory()->create();
        $solicitado = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create(['curso_id' => $primero->id]);
        $estudiante->update(['curso_id' => $segundo->id]);
        $this->pagoPendiente($estudiante, $solicitado);

        Sanctum::actingAs($estudiante->user);
        $response = $this->getJson('/api/estudiante/mis-cursos')->assertOk();

        $cursos = collect($response->json('cursos'));
        $this->assertEqualsCanonicalizing([$primero->id, $segundo->id], $cursos->pluck('id')->all());
        $this->assertTrue($cursos->firstWhere('id', $segundo->id)['es_actual']);
        $this->assertFalse($cursos->firstWhere('id', $primero->id)['es_actual']);
        $this->assertSame([$solicitado->id], collect($response->json('solicitudes_pendientes'))->pluck('curso_id')->all());
    }

    public function test_estudiante_puede_ver_un_curso_anterior_pero_no_uno_ajeno(): void
    {
        $anterior = Curso::factory()->create();
        $actual = Curso::factory()->create();
        $ajeno = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create(['curso_id' => $anterior->id]);
        $estudiante->update(['curso_id' => $actual->id]);

        Sanctum::actingAs($estudiante->user);

        $this->getJson("/api/estudiante/curso?curso_id={$anterior->id}")
            ->assertOk()
            ->assertJsonPath('curso.id', $anterior->id)
            ->assertJsonPath('es_actual', false);
        $this->getJson("/api/estudiante/curso?curso_id={$ajeno->id}")->assertNotFound();
    }

    public function test_profesor_aprueba_un_curso_anterior_y_el_certificado_es_de_ese_curso(): void
    {
        $anterior = Curso::factory()->create();
        $actual = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create(['curso_id' => $anterior->id]);
        $estudiante->update(['curso_id' => $actual->id]);

        Sanctum::actingAs($anterior->instructor->user);
        $this->patchJson("/api/profesor/estudiantes/{$estudiante->id}/aprobacion-curso", [
            'estado_aprobacion_curso' => 'aprobado',
            'curso_id' => $anterior->id,
        ])->assertOk();

        // El curso actual sigue pendiente.
        $this->assertSame('pendiente', $estudiante->fresh()->estado_aprobacion_curso);

        Sanctum::actingAs($estudiante->user);
        $this->get('/api/estudiante/certificado')->assertStatus(422);
        $this->get("/api/estudiante/certificado?curso_id={$anterior->id}")->assertOk();
    }

    public function test_profesor_no_aprueba_en_un_curso_que_no_dicta(): void
    {
        $suyo = Curso::factory()->create();
        $ajeno = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create(['curso_id' => $ajeno->id]);

        Sanctum::actingAs($suyo->instructor->user);
        $this->patchJson("/api/profesor/estudiantes/{$estudiante->id}/aprobacion-curso", [
            'estado_aprobacion_curso' => 'aprobado',
            'curso_id' => $ajeno->id,
        ])->assertForbidden();
    }
}
