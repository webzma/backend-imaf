<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Estudiante;
use Cloudinary\Api\ApiResponse;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PagoTest extends TestCase
{
    use RefreshDatabase;

    private Estudiante $estudiante;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->estudiante = Estudiante::factory()->create();
        Sanctum::actingAs($this->estudiante->user);
    }

    private function mockCloudinaryUpload(): void
    {
        Cloudinary::shouldReceive('uploadApi->upload')->andReturn(
            new ApiResponse(['public_id' => 'imaf/comprobantes/test'], [])
        );
    }

    public function test_estudiante_puede_reportar_pago_movil_con_comprobante(): void
    {
        $this->mockCloudinaryUpload();
        $curso = Curso::factory()->create();

        $response = $this->post('/api/estudiante/pagos', [
            'curso_id' => $curso->id,
            'metodo_pago' => 'pago_movil',
            'referencia' => '123456789',
            'banco_origen' => 'Banesco',
            'comprobante' => UploadedFile::fake()->image('comprobante.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $this->assertDatabaseHas('pagos', [
            'curso_id' => $curso->id,
            'metodo_pago' => 'pago_movil',
            'referencia' => '123456789',
            'estado' => 'pendiente',
        ]);
    }

    public function test_estudiante_puede_reportar_pago_por_transferencia(): void
    {
        $this->mockCloudinaryUpload();
        $curso = Curso::factory()->create();

        $this->post('/api/estudiante/pagos', [
            'curso_id' => $curso->id,
            'metodo_pago' => 'transferencia',
            'referencia' => '987654321',
            'comprobante' => UploadedFile::fake()->image('transferencia.png'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertDatabaseHas('pagos', [
            'curso_id' => $curso->id,
            'metodo_pago' => 'transferencia',
            'estado' => 'pendiente',
        ]);
    }

    public function test_estudiante_puede_reportar_pago_en_efectivo_sin_comprobante(): void
    {
        $curso = Curso::factory()->create();

        $this->postJson('/api/estudiante/pagos', [
            'curso_id' => $curso->id,
            'metodo_pago' => 'efectivo',
        ])->assertCreated();

        $this->assertDatabaseHas('pagos', [
            'curso_id' => $curso->id,
            'metodo_pago' => 'efectivo',
            'referencia' => null,
            'comprobante' => null,
            'estado' => 'pendiente',
        ]);
    }

    public function test_pago_movil_sin_referencia_ni_comprobante_falla(): void
    {
        $curso = Curso::factory()->create();

        $this->postJson('/api/estudiante/pagos', [
            'curso_id' => $curso->id,
            'metodo_pago' => 'pago_movil',
        ])->assertStatus(422)->assertJsonValidationErrors(['referencia', 'comprobante']);
    }

    public function test_metodo_pago_invalido_falla(): void
    {
        $curso = Curso::factory()->create();

        $this->postJson('/api/estudiante/pagos', [
            'curso_id' => $curso->id,
            'metodo_pago' => 'zelle',
        ])->assertStatus(422)->assertJsonValidationErrors('metodo_pago');
    }

    public function test_reportar_pago_deja_al_estudiante_pendiente_por_pago_sin_inscribirlo(): void
    {
        $curso = Curso::factory()->create();

        $this->postJson('/api/estudiante/pagos', [
            'curso_id' => $curso->id,
            'metodo_pago' => 'efectivo',
        ])->assertCreated();

        $this->estudiante->refresh();
        $this->assertSame('pendiente', $this->estudiante->estado_pago);
        $this->assertNull($this->estudiante->curso_id);
    }

    public function test_no_permite_doble_solicitud_activa_para_el_mismo_curso(): void
    {
        $curso = Curso::factory()->create();

        $this->postJson('/api/estudiante/pagos', [
            'curso_id' => $curso->id,
            'metodo_pago' => 'efectivo',
        ])->assertCreated();

        $this->postJson('/api/estudiante/pagos', [
            'curso_id' => $curso->id,
            'metodo_pago' => 'efectivo',
        ])->assertStatus(422);
    }
}
