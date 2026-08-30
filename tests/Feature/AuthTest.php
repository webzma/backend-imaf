<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_crea_usuario_estudiante_y_devuelve_token(): void
    {
        $response = $this->postJson('/api/register', [
            'primer_nombre' => 'Juan',
            'segundo_nombre' => 'Pablo',
            'primer_apellido' => 'Pérez',
            'segundo_apellido' => 'Gómez',
            'email' => 'juan@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nacionalidad' => 'V',
            'cedula' => '00123456',
            'telefono' => '8090000000',
            'municipio' => 'Santo Domingo',
            'direccion' => 'Calle 5, Ensanche La Paz',
            'fecha_nacimiento' => '2000-01-15',
            'genero' => 'masculino',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['user' => ['id', 'name', 'email', 'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido'], 'token'])
            ->assertJsonFragment(['name' => 'Juan Pablo Pérez Gómez'])
            ->assertJsonFragment(['primer_nombre' => 'Juan', 'segundo_apellido' => 'Gómez'])
            ->assertJsonFragment(['municipio' => 'Santo Domingo', 'direccion' => 'Calle 5, Ensanche La Paz']);

        $this->assertDatabaseHas('users', [
            'email' => 'juan@example.com',
            'role' => 'estudiante',
        ]);
        $this->assertDatabaseHas('estudiantes', [
            'cedula' => '00123456',
            'municipio' => 'Santo Domingo',
            'direccion' => 'Calle 5, Ensanche La Paz',
            'estado' => 'activo',
        ]);
    }

    public function test_register_acepta_cedula_de_7_u_8_digitos(): void
    {
        $response = $this->postJson('/api/register', [
            'primer_nombre' => 'Juan',
            'primer_apellido' => 'Pérez',
            'segundo_apellido' => 'Gómez',
            'email' => 'juan7@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nacionalidad' => 'V',
            'cedula' => '1234567',
            'telefono' => '8090000000',
            'direccion' => 'Avenida Duarte #45',
            'fecha_nacimiento' => '2000-01-15',
            'genero' => 'masculino',
        ]);

        $response->assertCreated();
    }

    public function test_register_rechaza_cedula_con_mas_de_8_digitos(): void
    {
        $response = $this->postJson('/api/register', [
            'primer_nombre' => 'Juan',
            'primer_apellido' => 'Pérez',
            'segundo_apellido' => 'Gómez',
            'email' => 'juan11@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nacionalidad' => 'V',
            'cedula' => '001-1234567-8',
            'telefono' => '8090000000',
            'direccion' => 'Calle 10, Los Prados',
            'fecha_nacimiento' => '2000-01-15',
            'genero' => 'masculino',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('cedula')
            ->assertJsonFragment(['La cédula debe tener 7 u 8 dígitos numéricos.']);
    }

    public function test_register_falla_con_email_duplicado(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $response = $this->postJson('/api/register', [
            'primer_nombre' => 'Otro',
            'primer_apellido' => 'Apellido',
            'segundo_apellido' => 'Apellido',
            'email' => 'dup@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nacionalidad' => 'V',
            'cedula' => '99999999',
            'telefono' => '8091111111',
            'direccion' => 'Calle 20, Bella Vista',
            'fecha_nacimiento' => '1999-05-05',
            'genero' => 'femenino',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_login_con_credenciales_correctas_devuelve_token(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['user' => ['id', 'email'], 'token']);
    }

    public function test_login_con_credenciales_incorrectas_devuelve_422(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_me_requiere_autenticacion(): void
    {
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_me_devuelve_el_usuario_autenticado(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($user);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonFragment(['email' => $user->email]);
    }

    public function test_logout_revoca_el_token_actual(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        Sanctum::actingAs($user);

        $this->postJson('/api/logout')
            ->assertOk()
            ->assertJson(['message' => 'Sesión cerrada correctamente.']);
    }
}
