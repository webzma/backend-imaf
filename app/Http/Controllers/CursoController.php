<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CursoController extends Controller
{
    public function index()
    {
        return response()->json(Curso::with('profesor')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'profesor_id' => ['required', Rule::exists('users', 'id')->where('role', 'profesor')],
            'nombre'      => 'required|string|max:255',
            'codigo'      => 'required|string|unique:cursos,codigo',
            'descripcion' => 'nullable|string',
            'creditos'    => 'required|integer|min:1|max:10',
            'estado'      => 'in:activo,inactivo',
        ]);

        $curso = Curso::create($data);

        return response()->json($curso->load('profesor'), 201);
    }

    public function show(string $id)
    {
        $curso = Curso::with('profesor', 'estudiantes.user')->findOrFail($id);

        return response()->json($curso);
    }

    public function update(Request $request, string $id)
    {
        $curso = Curso::findOrFail($id);

        $data = $request->validate([
            'profesor_id' => ['sometimes', Rule::exists('users', 'id')->where('role', 'profesor')],
            'nombre'      => 'sometimes|string|max:255',
            'codigo'      => 'sometimes|string|unique:cursos,codigo,' . $id,
            'descripcion' => 'nullable|string',
            'creditos'    => 'sometimes|integer|min:1|max:10',
            'estado'      => 'in:activo,inactivo',
        ]);

        $curso->update($data);

        return response()->json($curso->load('profesor'));
    }

    public function destroy(string $id)
    {
        Curso::findOrFail($id)->delete();

        return response()->json(['message' => 'Curso eliminado correctamente.']);
    }
}
