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

/**
 * Búsqueda, filtros, orden y resúmenes de las listas del panel.
 *
 * Estas tres cosas vivían en el cliente, aplicadas sobre los 10 registros de
 * la página cargada: buscar a alguien de la página 4 devolvía "sin resultados"
 * y las tarjetas de resumen decían "10" con 300 registros en la base.
 */
class ListadoAdminTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    /* ── Estudiantes ── */

    public function test_busqueda_de_estudiantes_alcanza_registros_fuera_de_la_primera_pagina(): void
    {
        $this->actingAsAdmin();

        Estudiante::factory()->count(15)->create(['nombre' => 'Persona Genérica']);
        $buscada = Estudiante::factory()->create(['nombre' => 'Yolimar Escalona']);

        $res = $this->getJson('/api/admin/estudiantes?search=Yolimar')->assertOk();

        $this->assertSame(1, $res->json('total'));
        $this->assertSame($buscada->id, $res->json('data.0.id'));
    }

    public function test_busqueda_de_estudiantes_cubre_el_correo_del_usuario(): void
    {
        $this->actingAsAdmin();

        Estudiante::factory()->count(3)->create();
        $user = User::factory()->create(['role' => 'estudiante', 'email' => 'unica@imaf.test']);
        $buscada = Estudiante::factory()->create(['user_id' => $user->id]);

        $res = $this->getJson('/api/admin/estudiantes?search=unica@imaf.test')->assertOk();

        $this->assertSame(1, $res->json('total'));
        $this->assertSame($buscada->id, $res->json('data.0.id'));
    }

    public function test_los_comodines_de_like_no_devuelven_la_tabla_entera(): void
    {
        $this->actingAsAdmin();
        Estudiante::factory()->count(5)->create();

        $res = $this->getJson('/api/admin/estudiantes?search=%')->assertOk();

        $this->assertSame(0, $res->json('total'));
    }

    public function test_filtro_sin_curso_lista_a_quien_no_tiene_curso_asignado(): void
    {
        $this->actingAsAdmin();
        $curso = Curso::factory()->create();

        Estudiante::factory()->count(2)->create(['curso_id' => $curso->id]);
        Estudiante::factory()->count(3)->create(['curso_id' => null]);

        $res = $this->getJson('/api/admin/estudiantes?curso_id=sin_curso')->assertOk();

        $this->assertSame(3, $res->json('total'));
    }

    public function test_orden_por_columna_permitida(): void
    {
        $this->actingAsAdmin();

        Estudiante::factory()->create(['nombre' => 'Zulay Mora']);
        Estudiante::factory()->create(['nombre' => 'Ana Belén']);

        $asc = $this->getJson('/api/admin/estudiantes?sort=nombre&direction=asc')->assertOk();
        $this->assertSame('Ana Belén', $asc->json('data.0.nombre'));

        $desc = $this->getJson('/api/admin/estudiantes?sort=nombre&direction=desc')->assertOk();
        $this->assertSame('Zulay Mora', $desc->json('data.0.nombre'));
    }

    public function test_una_columna_de_orden_no_permitida_cae_al_orden_por_defecto(): void
    {
        $this->actingAsAdmin();

        Estudiante::factory()->create(['nombre' => 'Zulay Mora']);
        Estudiante::factory()->create(['nombre' => 'Ana Belén']);

        // `sort` viaja hasta un ORDER BY: si no estuviera en lista blanca,
        // esto sería inyección de SQL.
        $res = $this->getJson('/api/admin/estudiantes?sort=(select+1)&direction=asc')->assertOk();

        $this->assertSame('Ana Belén', $res->json('data.0.nombre'));
    }

    public function test_resumen_de_estudiantes_cuenta_la_tabla_completa(): void
    {
        $this->actingAsAdmin();

        Estudiante::factory()->count(12)->create(['estado' => 'activo']);
        Estudiante::factory()->count(3)->create(['estado' => 'graduado']);

        $this->getJson('/api/admin/estudiantes/resumen')
            ->assertOk()
            ->assertJson(['total' => 15, 'activos' => 12, 'graduados' => 3]);
    }

    /* ── Instructores ── */

    public function test_busqueda_de_instructores_por_nombre_del_usuario(): void
    {
        $this->actingAsAdmin();

        Profesor::factory()->count(12)->create();
        $user = User::factory()->create([
            'role' => 'profesor',
            'primer_nombre' => 'Rosangela',
            'primer_apellido' => 'Pineda',
            'segundo_apellido' => 'Rojas',
        ]);
        $buscado = Profesor::factory()->create(['user_id' => $user->id]);

        $res = $this->getJson('/api/admin/profesores?search=Rosangela')->assertOk();

        $this->assertSame(1, $res->json('total'));
        $this->assertSame($buscado->id, $res->json('data.0.id'));
    }

    public function test_resumen_de_instructores(): void
    {
        $this->actingAsAdmin();
        Profesor::factory()->count(4)->create();

        $this->getJson('/api/admin/profesores/resumen')
            ->assertOk()
            ->assertJson(['total' => 4]);
    }

    /* ── Cursos ── */

    public function test_busqueda_y_filtro_de_cursos(): void
    {
        $this->actingAsAdmin();

        Curso::factory()->count(11)->create(['estado' => 'activo']);
        Curso::factory()->create(['nombre' => 'Repostería Avanzada', 'estado' => 'inactivo']);

        $porNombre = $this->getJson('/api/admin/cursos?search=Repostería')->assertOk();
        $this->assertSame(1, $porNombre->json('total'));

        $porEstado = $this->getJson('/api/admin/cursos?estado=inactivo')->assertOk();
        $this->assertSame(1, $porEstado->json('total'));
    }

    public function test_resumen_de_cursos(): void
    {
        $this->actingAsAdmin();

        $curso = Curso::factory()->create(['estado' => 'activo']);
        Curso::factory()->create(['estado' => 'inactivo']);
        Estudiante::factory()->count(2)->create(['curso_id' => $curso->id]);

        $this->getJson('/api/admin/cursos/resumen')
            ->assertOk()
            ->assertJson([
                'total' => 2,
                'activos' => 1,
                'estudiantes' => 2,
                'con_estudiantes' => 1,
            ]);
    }

    /* ── Pagos ── */

    public function test_pagos_respeta_per_page_y_pagina_de_verdad(): void
    {
        $this->actingAsAdmin();

        $curso = Curso::factory()->create();
        $user = User::factory()->create(['role' => 'estudiante']);
        Estudiante::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);

        for ($i = 0; $i < 25; $i++) {
            Pago::create([
                'user_id' => $user->id,
                'curso_id' => $curso->id,
                'metodo_pago' => 'pago_movil',
                'referencia' => (string) (100000 + $i),
                'estado' => 'aprobado',
            ]);
        }

        $res = $this->getJson('/api/admin/pagos?per_page=10&page=3')->assertOk();

        $this->assertSame(25, $res->json('total'));
        $this->assertSame(3, $res->json('last_page'));
        $this->assertCount(5, $res->json('data'));
    }

    public function test_pagos_ordena_los_pendientes_primero(): void
    {
        $this->actingAsAdmin();

        $curso = Curso::factory()->create();
        $user = User::factory()->create(['role' => 'estudiante']);
        Estudiante::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);

        $base = ['user_id' => $user->id, 'curso_id' => $curso->id, 'metodo_pago' => 'pago_movil'];

        Pago::create($base + ['referencia' => '111', 'estado' => 'aprobado']);
        $pendiente = Pago::create($base + ['referencia' => '222', 'estado' => 'pendiente']);
        Pago::create($base + ['referencia' => '333', 'estado' => 'rechazado']);

        $res = $this->getJson('/api/admin/pagos')->assertOk();

        $this->assertSame($pendiente->id, $res->json('data.0.id'));
    }

    public function test_resumen_de_pagos(): void
    {
        $this->actingAsAdmin();

        $curso = Curso::factory()->create();
        $user = User::factory()->create(['role' => 'estudiante']);
        Estudiante::factory()->create(['user_id' => $user->id, 'curso_id' => $curso->id]);
        $base = ['user_id' => $user->id, 'curso_id' => $curso->id, 'metodo_pago' => 'pago_movil'];

        Pago::create($base + ['referencia' => '1', 'estado' => 'pendiente']);
        Pago::create($base + ['referencia' => '2', 'estado' => 'pendiente']);
        Pago::create($base + ['referencia' => '3', 'estado' => 'aprobado']);

        $this->getJson('/api/admin/pagos/resumen')
            ->assertOk()
            ->assertJson(['total' => 3, 'pendiente' => 2, 'aprobado' => 1, 'rechazado' => 0]);
    }
}
