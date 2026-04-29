<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'cedula' => 'required|string|unique:estudiantes,cedula',
            'telefono' => 'required|string|max:20',
            'municipio' => 'nullable|string|max:255',
            'fecha_nacimiento' => 'required|date',
            'genero' => 'required|in:masculino,femenino,otro',
        ]);

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => $request->password,
                'role'     => 'estudiante',
            ]);

            Estudiante::create([
                'user_id'          => $user->id,
                'nombre'           => $request->name,
                'cedula'           => $request->cedula,
                'telefono'         => $request->telefono,
                'municipio'        => $request->municipio,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'genero'           => $request->genero,
                'fecha_inscripcion'=> now()->toDateString(),
                'estado'           => 'activo',
            ]);

            return $user;
        });

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $user->load('estudiante'),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada correctamente.']);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        $profile = match ($user->role) {
            'estudiante' => $user->load('estudiante.curso'),
            'profesor'   => $user->load('profesor.cursos'),
            default      => $user,
        };

        return response()->json($profile);
    }
}
