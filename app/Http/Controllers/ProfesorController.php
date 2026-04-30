<?php

namespace App\Http\Controllers;

use App\Models\Profesor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ProfesorController extends Controller
{
    public function index()
    {
        return response()->json(Profesor::with('user')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'cedula' => 'required|string|unique:profesores,cedula',
            'telefono' => 'nullable|string|max:20',
            'municipio' => 'nullable|string|max:255',
            'especialidad' => 'nullable|string|max:255',
            'titulo' => 'nullable|in:licenciatura,maestria,doctorado',
            'departamento' => 'nullable|string|max:255',
            'fecha_nacimiento' => 'nullable|date',
            'genero' => 'nullable|in:masculino,femenino,otro',
        ]);

        $profesor = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'profesor',
            ]);

            return Profesor::create([
                'user_id' => $user->id,
                'cedula' => $request->cedula,
                'telefono' => $request->telefono,
                'municipio' => $request->municipio,
                'especialidad' => $request->especialidad,
                'titulo' => $request->titulo,
                'departamento' => $request->departamento,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'genero' => $request->genero,
            ]);
        });

        return response()->json($profesor->load('user'), 201);
    }

    public function show(string $id)
    {
        $profesor = Profesor::with('user', 'cursos')->findOrFail($id);

        return response()->json($profesor);
    }

    public function update(Request $request, string $id)
    {
        $profesor = Profesor::with('user')->findOrFail($id);
        $authUser = Auth::user();

        $isAdmin = $authUser->role === 'admin';
        $isSelf = (int) $profesor->user_id === (int) $authUser->id;

        if (! $isAdmin && ! $isSelf) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $rules = [
            'cedula' => 'sometimes|string|unique:profesores,cedula,'.$id,
            'telefono' => 'nullable|string|max:20',
            'municipio' => 'nullable|string|max:255',
            'especialidad' => 'nullable|string|max:255',
            'titulo' => 'nullable|in:licenciatura,maestria,doctorado',
            'departamento' => 'nullable|string|max:255',
            'fecha_nacimiento' => 'nullable|date',
            'genero' => 'nullable|in:masculino,femenino,otro',
        ];

        if ($isAdmin) {
            $rules['name'] = 'sometimes|string|max:255';
            $rules['email'] = 'sometimes|email|unique:users,email,'.$profesor->user_id;
        }

        $data = $request->validate($rules);

        if ($isAdmin) {
            $userFields = array_filter(
                array_intersect_key($data, array_flip(['name', 'email'])),
                fn ($v) => $v !== null,
            );
            if (! empty($userFields)) {
                $profesor->user->update($userFields);
            }
        }

        $profesor->update(array_diff_key($data, array_flip(['name', 'email'])));

        return response()->json($profesor->load('user'));
    }

    public function destroy(string $id)
    {
        Profesor::findOrFail($id)->delete();

        return response()->json(['message' => 'Profesor eliminado correctamente.']);
    }
}
