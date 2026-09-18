<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Inserta un token de restablecimiento emitido hace `$minutos` minutos.
     */
    private function emitirToken(User $user, int $minutos = 0): string
    {
        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => Hash::make($token),
                'created_at' => now()->subMinutes($minutos),
            ],
        );

        return $token;
    }

    public function test_reset_password_acepta_un_token_vigente(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $token = $this->emitirToken($user, minutos: 5);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ])->assertOk();

        $this->assertTrue(Hash::check('nuevaClave123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_reset_password_rechaza_un_token_caducado(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $expira = (int) config('auth.passwords.users.expire', 60);
        $token = $this->emitirToken($user, minutos: $expira + 1);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertFalse(Hash::check('nuevaClave123', $user->fresh()->password));
    }

    public function test_un_token_caducado_se_borra_de_la_tabla(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $expira = (int) config('auth.passwords.users.expire', 60);
        $token = $this->emitirToken($user, minutos: $expira + 1);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ])->assertStatus(422);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_reset_password_revoca_las_sesiones_abiertas(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $tokenRobado = $user->createToken('auth_token')->plainTextToken;
        $user->createToken('otra_sesion');

        $this->assertSame(2, $user->tokens()->count());

        $token = $this->emitirToken($user, minutos: 5);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ])->assertOk();

        $this->assertSame(0, $user->tokens()->count());

        // El token que tenía el atacante ya no autentica.
        $this->withHeader('Authorization', 'Bearer '.$tokenRobado)
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_un_token_caducado_no_invalida_las_sesiones(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $user->createToken('auth_token');

        $expira = (int) config('auth.passwords.users.expire', 60);
        $token = $this->emitirToken($user, minutos: $expira + 1);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'nuevaClave123',
            'password_confirmation' => 'nuevaClave123',
        ])->assertStatus(422);

        $this->assertSame(1, $user->tokens()->count());
    }
}
