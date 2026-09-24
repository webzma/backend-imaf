<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaginacionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_estudiantes_pagina_con_per_page_10_y_devuelve_metadatos(): void
    {
        $this->actingAsAdmin();
        Estudiante::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/estudiantes?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('per_page', 10);
    }

    public function test_estudiantes_pagina_2_devuelve_los_siguientes_10(): void
    {
        $this->actingAsAdmin();
        Estudiante::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/estudiantes?page=2&per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('current_page', 2)
            ->assertJsonPath('total', 25)
            ->assertJsonPath('last_page', 3);
    }

    public function test_estudiantes_con_per_page_1000_devuelve_todos(): void
    {
        $this->actingAsAdmin();
        Estudiante::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/estudiantes?per_page=1000');

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('per_page', 1000)
            ->assertJsonPath('total', 25);
    }

    public function test_cursos_pagina_con_per_page_10(): void
    {
        $this->actingAsAdmin();
        Curso::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/cursos?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('per_page', 10);
    }

    public function test_cursos_con_per_page_1000_devuelve_todos(): void
    {
        $this->actingAsAdmin();
        Curso::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/cursos?per_page=1000');

        $response->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('per_page', 1000);
    }

    public function test_profesores_pagina_con_per_page_10(): void
    {
        $this->actingAsAdmin();
        Profesor::factory()->count(25)->create();

        $response = $this->getJson('/api/admin/profesores?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('per_page', 10);
    }

    public function test_per_page_invalido_cae_al_default(): void
    {
        $this->actingAsAdmin();
        Estudiante::factory()->count(5)->create();

        $this->getJson('/api/admin/estudiantes?per_page=abc')
            ->assertOk()
            ->assertJsonPath('per_page', 10);

        $this->getJson('/api/admin/estudiantes?per_page=0')
            ->assertOk()
            ->assertJsonPath('per_page', 10);

        $this->getJson('/api/admin/estudiantes?per_page=-5')
            ->assertOk()
            ->assertJsonPath('per_page', 10);

        $this->getJson('/api/admin/estudiantes?per_page=5000')
            ->assertOk()
            ->assertJsonPath('per_page', 1000);
    }

    private function actingAsEstudiante(): User
    {
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        Sanctum::actingAs($estudiante);

        return $estudiante;
    }

    public function test_estudiante_lista_cursos_activos_paginados(): void
    {
        $this->actingAsEstudiante();
        Curso::factory()->count(25)->create();

        $response = $this->getJson('/api/estudiante/cursos?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('per_page', 10)
            ->assertJsonStructure(['data', 'total', 'current_page', 'last_page', 'per_page']);
    }

    public function test_estudiante_pagina_2_devuelve_los_siguientes_cursos_sin_repetir(): void
    {
        $this->actingAsEstudiante();
        $cursos = Curso::factory()->count(25)->create();
        $idsEsperados = $cursos->sortByDesc('id')->pluck('id');

        $pagina1 = $this->getJson('/api/estudiante/cursos?page=1&per_page=10')
            ->assertOk()
            ->json('data');
        $pagina2 = $this->getJson('/api/estudiante/cursos?page=2&per_page=10')
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonCount(10, 'data')
            ->json('data');

        $idsPagina1 = collect($pagina1)->pluck('id');
        $idsPagina2 = collect($pagina2)->pluck('id');

        $this->assertEquals($idsEsperados->take(10)->values()->all(), $idsPagina1->values()->all());
        $this->assertEquals($idsEsperados->skip(10)->take(10)->values()->all(), $idsPagina2->values()->all());
        $this->assertSame(0, $idsPagina1->intersect($idsPagina2)->count());
    }

    public function test_estudiante_cursos_solo_incluye_activos(): void
    {
        $this->actingAsEstudiante();
        Curso::factory()->count(3)->create(['estado' => 'activo']);
        Curso::factory()->count(2)->create(['estado' => 'inactivo']);

        $response = $this->getJson('/api/estudiante/cursos?per_page=10')
            ->assertOk()
            ->assertJsonCount(3, 'data');
        $this->assertSame(3, $response->json('total'));
        foreach ($response->json('data') as $curso) {
            $this->assertSame('activo', $curso['estado']);
        }
    }

    public function test_estudiante_per_page_invalido_cae_al_default(): void
    {
        $this->actingAsEstudiante();
        Curso::factory()->count(3)->create();

        $this->getJson('/api/estudiante/cursos?per_page=abc')
            ->assertOk()
            ->assertJsonPath('per_page', 10);

        $this->getJson('/api/estudiante/cursos?per_page=0')
            ->assertOk()
            ->assertJsonPath('per_page', 10);

        $this->getJson('/api/estudiante/cursos?per_page=-5')
            ->assertOk()
            ->assertJsonPath('per_page', 10);
    }

    public function test_estudiante_per_page_excesivo_se_acota_al_tope(): void
    {
        $this->actingAsEstudiante();
        Curso::factory()->count(5)->create();

        $this->getJson('/api/estudiante/cursos?per_page=5000')
            ->assertOk()
            ->assertJsonPath('per_page', 100);
    }

    public function test_estudiante_cursos_requiere_autenticacion(): void
    {
        $this->getJson('/api/estudiante/cursos')->assertUnauthorized();
    }

    private function actingAsProfesor(): Profesor
    {
        $profesor = Profesor::factory()->create();
        Sanctum::actingAs($profesor->user);

        return $profesor;
    }

    public function test_profesor_lista_sus_cursos_paginados_con_formato_esperado(): void
    {
        $profesor = $this->actingAsProfesor();
        Curso::factory()->count(25)->create([
            'profesor_id' => $profesor->id,
            'fecha_inicio' => '2026-09-07',
            'fecha_fin' => '2026-12-11',
        ]);

        $response = $this->getJson('/api/profesor/cursos?page=1&per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('total', 25)
            ->assertJsonPath('current_page', 1)
            ->assertJsonPath('last_page', 3)
            ->assertJsonPath('per_page', 10);

        $item = $response->json('data.0');
        $this->assertSame(
            ['id', 'nombre', 'codigo', 'descripcion', 'estado', 'modalidad', 'limite_cupo', 'cupos_restantes', 'fecha_inicio', 'fecha_fin'],
            array_keys($item)
        );
        $this->assertSame('2026-09-07', $item['fecha_inicio']);
        $this->assertSame('2026-12-11', $item['fecha_fin']);
    }

    public function test_profesor_pagina_2_devuelve_los_siguientes_sin_repetir(): void
    {
        $profesor = $this->actingAsProfesor();
        $cursos = Curso::factory()->count(25)->create(['profesor_id' => $profesor->id]);
        $idsEsperados = $cursos->sortByDesc('id')->pluck('id');

        $pagina1 = $this->getJson('/api/profesor/cursos?page=1&per_page=10')
            ->assertOk()
            ->json('data');
        $pagina2 = $this->getJson('/api/profesor/cursos?page=2&per_page=10')
            ->assertOk()
            ->assertJsonPath('current_page', 2)
            ->assertJsonCount(10, 'data')
            ->json('data');

        $idsPagina1 = collect($pagina1)->pluck('id');
        $idsPagina2 = collect($pagina2)->pluck('id');

        $this->assertEquals($idsEsperados->take(10)->values()->all(), $idsPagina1->values()->all());
        $this->assertEquals($idsEsperados->skip(10)->take(10)->values()->all(), $idsPagina2->values()->all());
        $this->assertSame(0, $idsPagina1->intersect($idsPagina2)->count());
    }

    public function test_profesor_solo_ve_sus_propios_cursos(): void
    {
        $profesor = $this->actingAsProfesor();
        Curso::factory()->count(2)->create(['profesor_id' => $profesor->id]);

        $otroProfesor = Profesor::factory()->create();
        Curso::factory()->count(3)->create(['profesor_id' => $otroProfesor->id]);

        $response = $this->getJson('/api/profesor/cursos?per_page=10')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('total', 2);

        $idsEsperados = Curso::where('profesor_id', $profesor->id)->pluck('id')->sortDesc()->values()->all();
        $idsObtenidos = collect($response->json('data'))->pluck('id')->all();

        $this->assertEquals($idsEsperados, $idsObtenidos);
    }

    public function test_profesor_per_page_invalido_cae_al_default(): void
    {
        $this->actingAsProfesor();
        Curso::factory()->count(3)->create();

        $this->getJson('/api/profesor/cursos?per_page=abc')
            ->assertOk()
            ->assertJsonPath('per_page', 10);

        $this->getJson('/api/profesor/cursos?per_page=0')
            ->assertOk()
            ->assertJsonPath('per_page', 10);

        $this->getJson('/api/profesor/cursos?per_page=-5')
            ->assertOk()
            ->assertJsonPath('per_page', 10);
    }

    public function test_profesor_per_page_excesivo_se_acota_al_tope(): void
    {
        $this->actingAsProfesor();
        Curso::factory()->count(5)->create();

        $this->getJson('/api/profesor/cursos?per_page=5000')
            ->assertOk()
            ->assertJsonPath('per_page', 100);
    }

    public function test_profesor_cursos_requiere_autenticacion(): void
    {
        $this->getJson('/api/profesor/cursos')->assertUnauthorized();
    }

    public function test_profesor_cursos_rechaza_otros_roles(): void
    {
        $estudiante = User::factory()->create(['role' => 'estudiante']);
        Sanctum::actingAs($estudiante);
        $this->getJson('/api/profesor/cursos')->assertStatus(403);

        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $this->getJson('/api/profesor/cursos')->assertStatus(403);
    }

    public function test_me_sigue_devolviendo_profesor_cursos(): void
    {
        $profesor = $this->actingAsProfesor();
        Curso::factory()->count(3)->create(['profesor_id' => $profesor->id]);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonCount(3, 'profesor.cursos');
    }
}
