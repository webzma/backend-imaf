<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Estudiante;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\Auditoria;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate(
            [
                'primer_nombre' => [
                    'required',
                    'string',
                    'max:100',
                    self::REGEX_NOMBRES,
                ],
                'segundo_nombre' => [
                    'nullable',
                    'string',
                    'max:100',
                    self::REGEX_NOMBRES,
                ],
                'primer_apellido' => [
                    'required',
                    'string',
                    'max:100',
                    self::REGEX_NOMBRES,
                ],
                'segundo_apellido' => [
                    'required',
                    'string',
                    'max:100',
                    self::REGEX_NOMBRES,
                ],
                'email' => 'required|email|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'nacionalidad' => 'required|in:V,E',
                'cedula' => [
                    'required',
                    'string',
                    'max:15',
                    'unique:estudiantes,cedula',
                    self::REGEX_CEDULA,
                ],
                'telefono' => [
                    'required',
                    'string',
                    'max:20',
                    self::REGEX_NUMERICO,
                ],
                'municipio' => 'nullable|string|max:255',
                'direccion' => [
                    'required',
                    'string',
                    'max:255',
                    self::REGEX_DIRECCION,
                ],
                'fecha_nacimiento' => 'required|date',
                'genero' => 'required|in:masculino,femenino,otro',
            ],
            $this->mensajesTipoDato(),
        );

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'primer_nombre' => $request->primer_nombre,
                'segundo_nombre' => $request->segundo_nombre,
                'primer_apellido' => $request->primer_apellido,
                'segundo_apellido' => $request->segundo_apellido,
                'email' => $request->email,
                'password' => $request->password,
                'role' => 'estudiante',
            ]);

            Estudiante::create([
                'user_id' => $user->id,
                'nombre' => $user->name,
                'nacionalidad' => $request->nacionalidad,
                'cedula' => $request->cedula,
                'telefono' => $request->telefono,
                'municipio' => $request->municipio,
                'direccion' => $request->direccion,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'genero' => $request->genero,
                'fecha_inscripcion' => now()->toDateString(),
                'estado' => 'activo',
            ]);

            return $user;
        });

        $token = $user->createToken('auth_token')->plainTextToken;

        Auditoria::registrar('auth.registro', "Registro de la cuenta {$user->email}", [
            'actor' => $user,
            'entidad' => $user,
        ]);

        return response()->json(
            [
                'user' => $user->load('estudiante'),
                'token' => $token,
            ],
            201,
        );
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->auditarLoginFallido($request, $user);

            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        // Sesión única: al iniciar sesión se revocan los tokens anteriores,
        // por lo que cualquier otra sesión activa del usuario queda invalidada.
        $sesionesCerradas = $user->tokens()->count();
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        Auditoria::registrar('auth.login', "Inicio de sesión de {$user->email}", [
            'actor' => $user,
            'entidad' => $user,
            'metadatos' => ['sesiones_anteriores_cerradas' => $sesionesCerradas],
        ]);

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    /**
     * Deja constancia del intento fallido. Si la misma IP acumula varios en
     * poco tiempo, el evento sube a crítico: es el patrón de un ataque de
     * fuerza bruta o de relleno de credenciales.
     */
    private function auditarLoginFallido(Request $request, ?User $user): void
    {
        $ventana = now()->subMinutes(config('auditoria.ventana_logins_fallidos_minutos', 15));
        $previos = Bitacora::where('evento', 'auth.login_fallido')
            ->where('ip', $request->ip())
            ->where('created_at', '>=', $ventana)
            ->count();
        $intentos = $previos + 1;

        Auditoria::registrar(
            'auth.login_fallido',
            "Intento de inicio de sesión fallido para {$request->email}",
            [
                'actor' => null,
                'nivel' => $intentos >= config('auditoria.umbral_logins_fallidos', 5) ? 'critico' : 'aviso',
                'entidad' => $user,
                'metadatos' => [
                    'email' => $request->email,
                    'motivo' => $user ? 'contrasena_incorrecta' : 'usuario_inexistente',
                    'intentos_recientes_ip' => $intentos,
                ],
            ],
        );
    }

    public function logout(Request $request)
    {
        Auditoria::registrar('auth.logout', "Cierre de sesión de {$request->user()->email}", [
            'entidad' => $request->user(),
        ]);

        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        $profile = match ($user->role) {
            'estudiante' => $user->load('estudiante.curso'),
            'profesor' => $user->load(
                'profesor.tipoContrato',
                'profesor.especialidad',
                'profesor.titulo',
                'profesor.departamento',
                'profesor.cursos',
            ),
            default => $user,
        };

        return response()->json($profile);
    }

    /**
     * Solicita un restablecimiento de contraseña.
     *
     * Si el correo existe, se genera un token y se envía un email.
     * La respuesta es la misma sin importar si el correo existe o no,
     * para evitar la enumeración de usuarios.
     */
    /**
     * Actualiza el perfil de quien está autenticado.
     *
     * Existe porque el administrador era el único rol sin pantalla de perfil:
     * no podía corregir su nombre ni cambiar su contraseña desde la
     * plataforma. Cambiar la contraseña exige la actual aunque haya sesión
     * abierta: un equipo desatendido no debe bastar para tomar la cuenta.
     */
    public function actualizarPerfil(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'primer_nombre' => ['sometimes', 'required', 'string', 'max:100', self::REGEX_NOMBRES],
            'segundo_nombre' => ['nullable', 'string', 'max:100', self::REGEX_NOMBRES],
            'primer_apellido' => ['sometimes', 'required', 'string', 'max:100', self::REGEX_NOMBRES],
            'segundo_apellido' => ['sometimes', 'required', 'string', 'max:100', self::REGEX_NOMBRES],
            'email' => ['sometimes', 'required', 'email', 'unique:users,email,'.$user->id],
            'password_actual' => ['required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], $this->mensajesTipoDato());

        if (! empty($data['password'])) {
            if (! Hash::check($data['password_actual'], $user->password)) {
                throw ValidationException::withMessages([
                    'password_actual' => ['La contraseña actual no es correcta.'],
                ]);
            }

            $user->password = $data['password'];
            $cambioContrasena = true;
        }

        $user->fill(array_intersect_key($data, array_flip([
            'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'email',
        ])));

        $user->save();

        if ($cambioContrasena ?? false) {
            Auditoria::registrar('auth.contrasena_cambiada', "Cambio de contraseña de {$user->email}", [
                'entidad' => $user,
            ]);
        }

        return response()->json($user->fresh());
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                [
                    'token' => Hash::make($token),
                    'created_at' => now(),
                ],
            );

            $user->notify(new ResetPasswordNotification($token));
        }

        // La respuesta no revela si el correo existe; la bitácora sí lo anota.
        Auditoria::registrar('auth.recuperacion_solicitada', "Solicitud de recuperación de contraseña para {$request->email}", [
            'actor' => null,
            'entidad' => $user,
            'metadatos' => ['email' => $request->email, 'usuario_existe' => (bool) $user],
        ]);

        return response()->json([
            'message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.',
        ]);
    }

    /**
     * Minutos de validez del token de restablecimiento (config `auth.passwords`).
     */
    private function minutosExpiracionToken(): int
    {
        return (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60,
        );
    }

    /**
     * Restablece la contraseña del usuario usando el token recibido por email.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        $tokenInvalido =
            ! $record || ! Hash::check($request->token, $record->token);

        // El token caduca a los `auth.passwords.*.expire` minutos de emitirse.
        // Sin esta comprobación un enlace filtrado serviría para siempre, pese a
        // que el correo anuncia una expiración.
        $tokenExpirado =
            ! $tokenInvalido &&
            Carbon::parse($record->created_at)
                ->addMinutes($this->minutosExpiracionToken())
                ->isPast();

        if ($tokenInvalido || $tokenExpirado) {
            Auditoria::registrar('auth.restablecimiento_fallido', "Restablecimiento de contraseña rechazado para {$request->email}", [
                'actor' => null,
                'nivel' => 'aviso',
                'metadatos' => ['email' => $request->email, 'motivo' => $tokenExpirado ? 'token_expirado' : 'token_invalido'],
            ]);

            // Un token caducado se descarta para que no quede en la tabla.
            if ($tokenExpirado) {
                DB::table('password_reset_tokens')
                    ->where('email', $request->email)
                    ->delete();
            }

            throw ValidationException::withMessages([
                'email' => [
                    'El token de restablecimiento es inválido o ha expirado.',
                ],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();

        $user->password = $request->password;
        $user->setRememberToken(Str::random(60));
        $user->save();

        // Cambiar la contraseña cierra cualquier otra sesión abierta: si la cuenta
        // estaba comprometida, el token robado deja de servir. Es la misma política
        // de sesión única que aplica `login()`.
        $user->tokens()->delete();

        event(new PasswordReset($user));

        Auditoria::registrar('auth.contrasena_restablecida', "Contraseña restablecida para {$user->email}", [
            'actor' => $user,
            'entidad' => $user,
        ]);

        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return response()->json([
            'message' => 'Contraseña restablecida correctamente.',
        ]);
    }
}
