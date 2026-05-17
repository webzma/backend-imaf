<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Sesion;
use Illuminate\Http\Request;

class SesionController extends Controller
{
    public function index(string $cursoId)
    {
        Curso::findOrFail($cursoId);

        return response()->json(
            Sesion::where('curso_id', $cursoId)->orderBy('fecha')->orderBy('hora_inicio')->get()
        );
    }

    public function horario(Request $request)
    {
        $query = Sesion::with(['curso.instructor.user'])
            ->orderBy('fecha')
            ->orderBy('hora_inicio');

        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->input('desde'));
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->input('hasta'));
        }
        if ($request->filled('curso_id')) {
            $query->where('curso_id', $request->input('curso_id'));
        }
        if ($request->filled('instructor_id')) {
            $query->whereHas('curso', function ($q) use ($request) {
                $q->where('profesor_id', $request->input('instructor_id'));
            });
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->input('estado'));
        }

        return response()->json($query->get());
    }

    public function storeGlobal(Request $request)
    {
        $data = $request->validate([
            'curso_id' => 'required|exists:cursos,id',
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'required|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'estado' => 'in:programada,realizada,cancelada',
        ]);

        $sesion = Sesion::create($data);
        $sesion->load(['curso.instructor.user']);

        return response()->json($sesion, 201);
    }

    public function updateGlobal(Request $request, string $id)
    {
        $sesion = Sesion::findOrFail($id);

        $data = $request->validate([
            'curso_id' => 'sometimes|exists:cursos,id',
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'sometimes|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'estado' => 'in:programada,realizada,cancelada',
        ]);

        $sesion->update($data);
        $sesion->load(['curso.instructor.user']);

        return response()->json($sesion);
    }

    public function destroyGlobal(string $id)
    {
        $sesion = Sesion::findOrFail($id);
        $sesion->delete();

        return response()->json(['message' => 'Sesión eliminada.']);
    }

    public function store(Request $request, string $cursoId)
    {
        Curso::findOrFail($cursoId);

        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'required|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'estado' => 'in:programada,realizada,cancelada',
        ]);

        $sesion = Sesion::create(['curso_id' => $cursoId, ...$data]);

        return response()->json($sesion, 201);
    }

    public function update(Request $request, string $cursoId, string $id)
    {
        $sesion = Sesion::where('curso_id', $cursoId)->findOrFail($id);

        $data = $request->validate([
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'fecha' => 'sometimes|date',
            'hora_inicio' => 'nullable|date_format:H:i',
            'hora_fin' => 'nullable|date_format:H:i|after:hora_inicio',
            'estado' => 'in:programada,realizada,cancelada',
        ]);

        $sesion->update($data);

        return response()->json($sesion);
    }

    public function destroy(string $cursoId, string $id)
    {
        $sesion = Sesion::where('curso_id', $cursoId)->findOrFail($id);
        $sesion->delete();

        return response()->json(['message' => 'Sesión eliminada.']);
    }
}
