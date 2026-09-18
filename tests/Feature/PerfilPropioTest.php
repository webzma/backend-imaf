<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerfilPropioTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_admin_puede_corregir_su_nombre(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->putJson('/api/me', [
            'primer_nombre' => 'Marielena',
            // Se envía en blanco a propósito: quitar el segundo nombre debe
            // recalcular `name`, no dejar el anterior colgando.
            'segundo_nombre' => null,
            'primer_apellido' => 'Suárez',
            'segundo_apellido' => 'Gil',
        ])->assertOk();

        $admin->refresh();
        $this->assertSame('Marielena', $admin->primer_nombre);
        $this->assertSame('Marielena Suárez Gil', $admin->name);
    }

    public function test_cambiar_la_contrasena_exige_la_actual(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('contrasena-vieja'),
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/me', [
            'password_actual' => 'no-es-esta',
            'password' => 'contrasena-nueva',
            'password_confirmation' => 'contrasena-nueva',
        ])->assertStatus(422)->assertJsonValidationErrors('password_actual');

        $this->putJson('/api/me', [
            'password_actual' => 'contrasena-vieja',
            'password' => 'contrasena-nueva',
            'password_confirmation' => 'contrasena-nueva',
        ])->assertOk();

        $this->assertTrue(Hash::check('contrasena-nueva', $admin->fresh()->password));
    }

    public function test_no_se_puede_tomar_el_correo_de_otra_persona(): void
    {
        User::factory()->create(['email' => 'ocupado@imaf.test']);
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->putJson('/api/me', ['email' => 'ocupado@imaf.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
