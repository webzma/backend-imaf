<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\Profesor;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\CursoEstadoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CursoEstadoServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function cursoConInscritos(int $inscritos, array $atributos = []): Curso
    {
        $profesor = Profesor::factory()->create();
        $curso = Curso::factory()->create(array_merge([
            'profesor_id' => $profesor->id,
            'estado' => 'activo',
            'fecha_inicio' => today()->subDay()->toDateString(),
            'fecha_fin' => today()->addMonth()->toDateString(),
            'minimo_estudiantes' => 5,
        ], $atributos));

        Estudiante::factory()->count($inscritos)->create(['curso_id' => $curso->id]);

        return $curso;
    }

    public function test_no_inactiva_un_curso_activo_que_no_alcanza_el_minimo(): void
    {
        Notification::fake();
        $curso = $this->cursoConInscritos(1);

        CursoEstadoService::sincronizar();

        $this->assertSame('activo', $curso->fresh()->estado);
        $this->assertNotNull($curso->fresh()->minimo_alertado_en);
        $this->assertTrue(Bitacora::where('evento', 'curso.no_aperturado')
            ->where('entidad_id', (string) $curso->id)
            ->exists());
        Notification::assertSentTo($this->admin, GenericNotification::class);
    }

    public function test_la_alerta_de_minimo_solo_se_emite_una_vez(): void
    {
        Notification::fake();
        $curso = $this->cursoConInscritos(1);

        CursoEstadoService::sincronizar();
        CursoEstadoService::sincronizar();

        $this->assertSame(1, Bitacora::where('evento', 'curso.no_aperturado')
            ->where('entidad_id', (string) $curso->id)
            ->count());
        Notification::assertSentToTimes($this->admin, GenericNotification::class, 1);
    }

    public function test_sigue_inactivando_un_curso_vencido(): void
    {
        Notification::fake();
        $curso = $this->cursoConInscritos(5, [
            'fecha_inicio' => today()->subMonth()->toDateString(),
            'fecha_fin' => today()->subDay()->toDateString(),
        ]);

        CursoEstadoService::sincronizar();

        $this->assertSame('inactivo', $curso->fresh()->estado);
        $this->assertNull($curso->fresh()->minimo_alertado_en);
    }

    public function test_no_alerta_si_el_curso_alcanza_el_minimo(): void
    {
        Notification::fake();
        $curso = $this->cursoConInscritos(5);

        CursoEstadoService::sincronizar();

        $this->assertSame('activo', $curso->fresh()->estado);
        $this->assertNull($curso->fresh()->minimo_alertado_en);
        $this->assertFalse(Bitacora::where('evento', 'curso.no_aperturado')
            ->where('entidad_id', (string) $curso->id)
            ->exists());
    }

    public function test_cambiar_la_fecha_de_inicio_permite_una_nueva_alerta(): void
    {
        Notification::fake();
        $curso = $this->cursoConInscritos(1);
        CursoEstadoService::sincronizar();
        $this->assertNotNull($curso->fresh()->minimo_alertado_en);

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/cursos/{$curso->id}", [
            'fecha_inicio' => '2026-09-07',
        ])->assertOk();

        $this->assertNull($curso->fresh()->minimo_alertado_en);
    }
}
