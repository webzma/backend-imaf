<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificadoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
    }

    public function test_no_genera_certificado_si_el_estudiante_no_aprobo(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create([
            'curso_id' => $curso->id,
            'estado_aprobacion_curso' => 'pendiente',
        ]);

        $this->getJson("/api/admin/estudiantes/{$estudiante->id}/certificado")
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'El estudiante aún no ha aprobado el curso, por lo que no puede generar el certificado.',
            ]);
    }

    public function test_no_genera_certificado_si_faltan_datos_del_curso(): void
    {
        $estudiante = Estudiante::factory()->create([
            'curso_id' => null,
            'estado_aprobacion_curso' => 'aprobado',
        ]);

        $this->getJson("/api/admin/estudiantes/{$estudiante->id}/certificado")
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Datos del curso incompletos.']);
    }

    public function test_genera_pdf_cuando_el_estudiante_aprobo(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = Estudiante::factory()->create([
            'curso_id' => $curso->id,
            'estado_aprobacion_curso' => 'aprobado',
        ]);

        $response = $this->get("/api/admin/estudiantes/{$estudiante->id}/certificado");

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('content-type'));
    }
}
