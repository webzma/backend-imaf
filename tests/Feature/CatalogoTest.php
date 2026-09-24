<?php

namespace Tests\Feature;

use App\Models\Especialidad;
use App\Models\Profesor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_acepta_numeros_puntos_y_parentesis(): void
    {
        $this->postJson('/api/admin/titulos', ['nombre' => 'T.S.U. en Informática (2026)'])
            ->assertCreated()
            ->assertJsonPath('nombre', 'T.S.U. en Informática (2026)');
    }

    public function test_rechaza_simbolos_con_un_mensaje_que_los_nombra(): void
    {
        $this->postJson('/api/admin/titulos', ['nombre' => 'Título <script>'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('nombre')
            ->assertJsonFragment(['nombre' => ["El nombre solo puede contener letras, números, espacios y los signos . - ' ( )."]]);
    }

    public function test_recorta_espacios_y_detecta_duplicados(): void
    {
        $this->postJson('/api/admin/departamentos', ['nombre' => '  Formación  '])
            ->assertCreated()
            ->assertJsonPath('nombre', 'Formación');

        $this->postJson('/api/admin/departamentos', ['nombre' => 'Formación '])
            ->assertStatus(422)
            ->assertJsonFragment(['nombre' => ['Ya existe un registro con ese nombre.']]);
    }

    public function test_el_listado_indica_cuantos_instructores_lo_usan(): void
    {
        $profesor = Profesor::factory()->create();
        $especialidad = Especialidad::findOrFail($profesor->especialidad_id);

        $item = collect($this->getJson('/api/admin/especialidades')->assertOk()->json())
            ->firstWhere('id', $especialidad->id);

        $this->assertSame(1, $item['profesores_count']);
    }
}
