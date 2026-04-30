<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Temario;
use Illuminate\Http\Request;

class TemarioController extends Controller
{
    public function index(string $cursoId)
    {
        Curso::findOrFail($cursoId);

        return response()->json(
            Temario::where('curso_id', $cursoId)->orderBy('orden')->get()
        );
    }

    public function store(Request $request, string $cursoId)
    {
        Curso::findOrFail($cursoId);

        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'orden' => 'nullable|integer|min:0',
        ]);

        if (! isset($data['orden'])) {
            $data['orden'] = Temario::where('curso_id', $cursoId)->max('orden') + 1;
        }

        $temario = Temario::create(['curso_id' => $cursoId, ...$data]);

        return response()->json($temario, 201);
    }

    public function update(Request $request, string $cursoId, string $id)
    {
        $temario = Temario::where('curso_id', $cursoId)->findOrFail($id);

        $data = $request->validate([
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'orden' => 'nullable|integer|min:0',
        ]);

        $temario->update($data);

        return response()->json($temario);
    }

    public function destroy(string $cursoId, string $id)
    {
        $temario = Temario::where('curso_id', $cursoId)->findOrFail($id);
        $temario->delete();

        return response()->json(['message' => 'Tema eliminado.']);
    }
}
