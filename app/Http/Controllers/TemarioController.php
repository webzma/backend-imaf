<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Temario;
use App\Notifications\GenericNotification;
use Illuminate\Http\Request;

class TemarioController extends Controller
{
    private function notifyInstructor(string $cursoId, string $accion)
    {
        $curso = Curso::with('instructor.user')->find($cursoId);
        if ($curso && $curso->instructor && $curso->instructor->user) {
            $curso->instructor->user->notify(new GenericNotification(
                'Temario Actualizado',
                "El administrador ha {$accion} un tema en el temario del curso: {$curso->nombre}.",
                "/profesor/cursos/{$curso->id}"
            ));
        }
    }

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

        $this->notifyInstructor($cursoId, 'agregado');

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

        $this->notifyInstructor($cursoId, 'actualizado');

        return response()->json($temario);
    }

    public function destroy(string $cursoId, string $id)
    {
        $temario = Temario::where('curso_id', $cursoId)->findOrFail($id);
        $temario->delete();

        $this->notifyInstructor($cursoId, 'eliminado');

        return response()->json(['message' => 'Tema eliminado.']);
    }
}
