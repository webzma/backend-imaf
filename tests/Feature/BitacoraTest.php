<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Pago;
use App\Models\Profesor;
use App\Models\User;
use App\Services\Auditoria;
use App\Services\CursoEstadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\TestCase;

class BitacoraTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function ultimo(string $evento): ?Bitacora
    {
        return Bitacora::where('evento', $evento)->orderByDesc('created_at')->first();
    }

    /* ── Captura automática de cambios ── */

    public function test_registra_alta_cambio_y_archivado_de_un_curso_con_su_actor(): void
    {
        Sanctum::actingAs($this->admin);
        $profesor = Profesor::factory()->create();

        $id = $this->postJson('/api/admin/cursos', [
            'profesor_id' => $profesor->id,
            'nombre' => 'Cerámica',
            'limite_cupo' => 20,
            'precio' => 100,
            'estado' => 'activo',
        ])->assertCreated()->json('id');

        $alta = $this->ultimo('curso.creado');
        $this->assertSame($this->admin->id, $alta->actor_id);
        $this->assertSame('admin', $alta->actor_rol);
        $this->assertSame('datos', $alta->categoria);
        $this->assertSame((string) $id, $alta->entidad_id);
        $this->assertSame('Cerámica', $alta->cambios['despues']['nombre']);
        $this->assertSame('Alta de curso «Cerámica» (#'.$id.')', $alta->descripcion);

        $this->putJson("/api/admin/cursos/{$id}", ['limite_cupo' => 25])->assertOk();

        $cambio = $this->ultimo('curso.actualizado');
        // Solo el campo que cambió, con su valor anterior y el nuevo.
        $this->assertEquals(['limite_cupo' => 20], $cambio->cambios['antes']);
        $this->assertEquals(['limite_cupo' => 25], $cambio->cambios['despues']);

        $this->deleteJson("/api/admin/cursos/{$id}")->assertOk();

        $baja = $this->ultimo('curso.archivado');
        $this->assertSame('aviso', $baja->nivel);
        $this->assertSame('Cerámica', $baja->cambios['antes']['nombre']);
    }

    public function test_un_guardado_sin_cambios_reales_no_genera_registro(): void
    {
        $curso = Curso::factory()->create();
        $antes = Bitacora::count();

        $curso->touch();

        $this->assertSame($antes, Bitacora::count());
    }

    public function test_nunca_guarda_contrasenas(): void
    {
        Sanctum::actingAs($this->admin);

        $this->putJson('/api/me', [
            'password_actual' => 'password',
            'password' => 'nueva-clave-123',
            'password_confirmation' => 'nueva-clave-123',
        ])->assertOk();

        $cambio = $this->ultimo('usuario.actualizado');
        $this->assertSame('[oculto]', $cambio->cambios['antes']['password']);
        $this->assertSame('[oculto]', $cambio->cambios['despues']['password']);
        $this->assertNotNull($this->ultimo('auth.contrasena_cambiada'));
        $this->assertStringNotContainsString('nueva-clave-123', Bitacora::all()->toJson());
    }

    /* ── Autenticación y seguridad ── */

    public function test_registra_inicio_de_sesion_y_fallos_sin_la_contrasena(): void
    {
        $this->postJson('/api/login', ['email' => $this->admin->email, 'password' => 'password'])->assertOk();

        $login = $this->ultimo('auth.login');
        $this->assertSame($this->admin->id, $login->actor_id);
        $this->assertSame('autenticacion', $login->categoria);

        $this->postJson('/api/login', ['email' => $this->admin->email, 'password' => 'mala-clave'])->assertStatus(422);

        $fallo = $this->ultimo('auth.login_fallido');
        $this->assertNull($fallo->actor_id);
        $this->assertSame('aviso', $fallo->nivel);
        $this->assertSame('contrasena_incorrecta', $fallo->metadatos['motivo']);
        $this->assertStringNotContainsString('mala-clave', json_encode($fallo->toArray()));
    }

    public function test_varios_fallos_desde_la_misma_ip_suben_a_critico(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', ['email' => 'nadie@example.com', 'password' => 'x'])->assertStatus(422);
        }

        $ultimo = $this->ultimo('auth.login_fallido');
        $this->assertSame('critico', $ultimo->nivel);
        $this->assertSame(5, $ultimo->metadatos['intentos_recientes_ip']);
        $this->assertSame('usuario_inexistente', $ultimo->metadatos['motivo']);
    }

    public function test_registra_accesos_denegados(): void
    {
        $estudiante = Estudiante::factory()->create();
        Sanctum::actingAs($estudiante->user);

        $this->getJson('/api/admin/bitacora')->assertForbidden();

        $denegado = $this->ultimo('seguridad.acceso_denegado');
        $this->assertSame($estudiante->user_id, $denegado->actor_id);
        $this->assertSame('seguridad', $denegado->categoria);
        $this->assertSame('/api/admin/bitacora', $denegado->ruta);
        $this->assertSame(['admin'], $denegado->metadatos['roles_requeridos']);
    }

    /* ── Eventos de negocio y contexto de la petición ── */

    public function test_los_eventos_de_una_peticion_comparten_id_y_se_devuelve_en_la_cabecera(): void
    {
        $estudiante = Estudiante::factory()->create();
        $curso = Curso::factory()->create(['precio' => 80]);
        $pago = Pago::create([
            'user_id' => $estudiante->user_id,
            'curso_id' => $curso->id,
            'metodo_pago' => 'efectivo',
            'referencia' => '',
            'banco_origen' => '',
            'comprobante' => '',
            'estado' => 'pendiente',
        ]);

        Sanctum::actingAs($this->admin);
        $respuesta = $this->putJson("/api/admin/pagos/{$pago->id}", ['estado' => 'aprobado'])->assertOk();

        $solicitud = $respuesta->headers->get('X-Request-Id');
        $aprobado = $this->ultimo('pago.aprobado');

        $this->assertSame($solicitud, $aprobado->solicitud_id);
        $this->assertEquals(80, $aprobado->metadatos['monto']);
        $this->assertSame('negocio', $aprobado->categoria);
        // El mismo id agrupa el cambio del pago y el del estudiante.
        $this->assertTrue(Bitacora::where('solicitud_id', $solicitud)->where('evento', 'pago.actualizado')->exists());
        $this->assertTrue(Bitacora::where('solicitud_id', $solicitud)->where('evento', 'estudiante.actualizado')->exists());
    }

    public function test_respeta_un_x_request_id_valido_del_cliente(): void
    {
        Sanctum::actingAs($this->admin);
        $id = '3f1c2a52-8f3e-4b8e-9a51-2f7f0c1d9e11';

        $this->withHeader('X-Request-Id', $id)
            ->getJson('/api/admin/bitacora')
            ->assertHeader('X-Request-Id', $id);
    }

    public function test_los_cambios_masivos_se_registran_con_la_lista_de_afectados(): void
    {
        $ids = Estudiante::factory()->count(3)->create()->pluck('id')->all();
        Sanctum::actingAs($this->admin);

        $this->patchJson('/api/admin/estudiantes/estado-masivo', ['ids' => $ids, 'estado' => 'graduado'])->assertOk();

        $evento = $this->ultimo('estudiante.estado_masivo');
        $this->assertEqualsCanonicalizing($ids, $evento->metadatos['ids']);
        $this->assertSame(3, $evento->metadatos['actualizados']);
    }

    public function test_los_procesos_automaticos_se_atribuyen_al_sistema(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        $curso = Curso::factory()->create(['estado' => 'activo', 'fecha_fin' => '2026-09-01']);
        Sanctum::actingAs($this->admin);

        // Se ejecuta dentro de la petición del admin, pero no es obra suya.
        CursoEstadoService::sincronizar();

        $finalizado = $this->ultimo('curso.finalizado_automaticamente');
        $this->assertNull($finalizado->actor_id);
        $this->assertSame('Sistema', $finalizado->actor_nombre);
        $this->assertSame('Sistema', $this->ultimo('curso.actualizado')->actor_nombre);
        $this->assertSame((string) $curso->id, $finalizado->entidad_id);
    }

    /* ── Inmutabilidad e integridad ── */

    public function test_los_registros_no_se_pueden_modificar_ni_borrar(): void
    {
        $registro = Auditoria::registrar('sistema.prueba', 'Prueba');

        $this->expectException(LogicException::class);
        $registro->update(['descripcion' => 'otra']);
    }

    public function test_los_registros_no_se_pueden_borrar_por_el_modelo(): void
    {
        $registro = Auditoria::registrar('sistema.prueba', 'Prueba');

        $this->expectException(LogicException::class);
        $registro->delete();
    }

    public function test_la_huella_detecta_una_alteracion_directa_en_la_base(): void
    {
        $registro = Auditoria::registrar('sistema.prueba', 'Original', ['metadatos' => ['b' => 1, 'a' => [2.5, 'x']]]);
        $this->assertTrue($registro->fresh()->huellaValida());

        DB::table('bitacora')->where('id', $registro->id)->update(['descripcion' => 'Manipulada']);

        $this->assertFalse($registro->fresh()->huellaValida());
        $this->artisan('bitacora:verificar')->assertFailed();
        $this->assertSame('critico', $this->ultimo('bitacora.alteracion_detectada')->nivel);
    }

    public function test_un_fallo_de_la_bitacora_no_interrumpe_la_operacion(): void
    {
        Schema::drop('bitacora');

        $this->assertNull(Auditoria::registrar('sistema.prueba', 'Sin tabla'));
        $curso = Curso::factory()->create();
        $this->assertModelExists($curso);
    }

    /* ── API de consulta ── */

    public function test_solo_el_admin_consulta_la_bitacora(): void
    {
        $profesor = Profesor::factory()->create();
        Sanctum::actingAs($profesor->user);
        $this->getJson('/api/admin/bitacora')->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/bitacora')->assertOk()->assertJsonStructure([
            'data' => [['id', 'fecha', 'evento', 'categoria', 'nivel', 'descripcion', 'actor', 'entidad', 'ip']],
            'total',
            'current_page',
        ]);
    }

    public function test_filtra_por_categoria_prefijo_de_evento_y_fechas(): void
    {
        $this->travelTo('2026-09-20 10:00:00');
        Auditoria::registrar('pago.aprobado', 'Viejo');
        $this->travelTo('2026-09-24 10:00:00');
        Auditoria::registrar('pago.rechazado', 'Nuevo');
        Auditoria::registrar('auth.login', 'Login', ['actor' => $this->admin]);

        Sanctum::actingAs($this->admin);

        $pagos = collect($this->getJson('/api/admin/bitacora?evento=pago.*')->json('data'))->pluck('evento');
        $this->assertEqualsCanonicalizing(['pago.aprobado', 'pago.rechazado'], $pagos->all());

        $hoy = collect($this->getJson('/api/admin/bitacora?evento=pago.*&desde=2026-09-24&hasta=2026-09-24')->json('data'));
        $this->assertSame(['pago.rechazado'], $hoy->pluck('evento')->all());

        $auth = collect($this->getJson('/api/admin/bitacora?categoria=autenticacion')->json('data'));
        $this->assertSame(['auth.login'], $auth->pluck('evento')->unique()->values()->all());

        $this->getJson('/api/admin/bitacora?nivel=grave')->assertStatus(422);
    }

    public function test_el_detalle_muestra_integridad_y_eventos_de_la_misma_peticion(): void
    {
        Sanctum::actingAs($this->admin);
        $id = $this->postJson('/api/admin/cursos', [
            'profesor_id' => Profesor::factory()->create()->id,
            'nombre' => 'Tejido',
            'limite_cupo' => 10,
            'precio' => 0,
            'estado' => 'activo',
        ])->assertCreated()->json('id');

        $alta = $this->ultimo('curso.creado');

        $this->getJson("/api/admin/bitacora/{$alta->id}")
            ->assertOk()
            ->assertJsonPath('integridad', 'valida')
            ->assertJsonPath('cambios.despues.nombre', 'Tejido')
            ->assertJsonPath('entidad.id', (string) $id);

        $this->getJson("/api/admin/bitacora/entidades/curso/{$id}")
            ->assertOk()
            ->assertJsonPath('data.0.evento', 'curso.creado');
    }

    public function test_resumen_y_opciones(): void
    {
        $this->postJson('/api/login', ['email' => 'x@example.com', 'password' => 'x']);
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/bitacora/resumen')
            ->assertOk()
            ->assertJsonPath('logins_fallidos_24h', 1)
            ->assertJsonCount(14, 'serie_14d')
            ->assertJsonStructure(['total', 'por_categoria_7d', 'por_nivel_7d', 'ips_con_mas_fallos_24h', 'actores_mas_activos_7d', 'criticos_recientes']);

        $this->getJson('/api/admin/bitacora/opciones')
            ->assertOk()
            ->assertJsonFragment(['auth.login_fallido']);
    }

    public function test_exportar_genera_csv_y_queda_registrado(): void
    {
        Auditoria::registrar('pago.aprobado', 'Pago de prueba «ñandú»');
        Sanctum::actingAs($this->admin);

        $respuesta = $this->get('/api/admin/bitacora/exportar?evento=pago.*')->assertOk();
        $csv = $respuesta->streamedContent();

        $this->assertStringContainsString('Pago de prueba «ñandú»', $csv);
        $this->assertStringContainsString('text/csv', $respuesta->headers->get('Content-Type'));
        $this->assertNotNull($this->ultimo('bitacora.exportada'));
    }

    /* ── Retención ── */

    public function test_depurar_borra_solo_lo_antiguo_y_deja_constancia(): void
    {
        $this->travelTo('2024-01-01 10:00:00');
        Auditoria::registrar('sistema.prueba', 'Antiguo');
        $this->travelBack();
        Auditoria::registrar('sistema.prueba', 'Reciente');

        $this->artisan('bitacora:depurar', ['--dias' => 365])->assertSuccessful();

        $descripciones = Bitacora::where('evento', 'sistema.prueba')->pluck('descripcion')->all();
        $this->assertSame(['Reciente'], $descripciones);
        $this->assertSame(1, $this->ultimo('bitacora.depurada')->metadatos['borrados']);
    }
}
