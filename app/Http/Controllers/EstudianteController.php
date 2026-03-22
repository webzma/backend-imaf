<?php

namespace App\Http\Controllers;

use App\Models\Estudiante;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EstudianteController extends Controller
{
    public function index()
    {
        return response()->json(Estudiante::with('user', 'curso')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => 'required|email|unique:users',
            'password'         => 'required|string|min:8',
            'curso_id'         => 'nullable|exists:cursos,id',
            'cedula'           => 'required|string|unique:estudiantes,cedula',
            'telefono'         => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'genero'           => 'nullable|in:masculino,femenino,otro',
            'fecha_inscripcion'=> 'required|date',
            'estado'           => 'in:activo,inactivo,graduado',
        ]);

        $estudiante = DB::transaction(function () use ($request) {
            $user = User::create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
                'role'     => 'estudiante',
            ]);

            return Estudiante::create([
                'user_id'          => $user->id,
                'curso_id'         => $request->curso_id,
                'nombre'           => $request->name,
                'cedula'           => $request->cedula,
                'telefono'         => $request->telefono,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'genero'           => $request->genero,
                'fecha_inscripcion'=> $request->fecha_inscripcion,
                'estado'           => $request->estado ?? 'activo',
            ]);
        });

        return response()->json($estudiante->load('user', 'curso'), 201);
    }

    public function showMe()
    {
        $estudiante = Estudiante::with('user', 'curso')
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return response()->json($estudiante);
    }

    public function updateMe(Request $request)
    {
        $estudiante = Estudiante::where('user_id', Auth::id())->firstOrFail();
        $id = $estudiante->id;

        $data = $request->validate([
            'telefono'         => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'genero'           => 'nullable|in:masculino,femenino,otro',
        ]);

        $estudiante->update($data);

        return response()->json($estudiante->load('user', 'curso'));
    }

    public function show(string $id)
    {
        $estudiante = Estudiante::with('user', 'curso')->findOrFail($id);

        return response()->json($estudiante);
    }

    public function update(Request $request, string $id)
    {
        $estudiante = Estudiante::findOrFail($id);

        $data = $request->validate([
            'curso_id'         => 'nullable|exists:cursos,id',
            'nombre'           => 'sometimes|string|max:255',
            'cedula'           => 'sometimes|string|unique:estudiantes,cedula,' . $id,
            'telefono'         => 'nullable|string|max:20',
            'fecha_nacimiento' => 'nullable|date',
            'genero'           => 'nullable|in:masculino,femenino,otro',
            'fecha_inscripcion'=> 'sometimes|date',
            'estado'           => 'in:activo,inactivo,graduado',
        ]);

        $estudiante->update($data);

        return response()->json($estudiante->load('user', 'curso'));
    }

    public function destroy(string $id)
    {
        Estudiante::findOrFail($id)->delete();

        return response()->json(['message' => 'Estudiante eliminado correctamente.']);
    }
}
