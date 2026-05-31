<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Profesor;
use App\Notifications\GenericNotification;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CursoController extends Controller
{
    public function index()
    {
        return response()->json(Curso::with('instructor.user', 'estudiantes.user')->get());
    }

    public function indexActivos()
    {
        return response()->json(
            Curso::with('instructor.user', 'estudiantes.user')
                ->where('estado', 'activo')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'profesor_id' => ['required', Rule::exists('profesores', 'id')],
            'nombre' => 'required|string|max:255',
            'limite_cupo' => 'required|integer|min:1',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'descripcion' => 'nullable|string',
            'requisitos' => 'nullable|string',
            'precio' => 'required|numeric|min:0',
            'whatsapp_url' => 'nullable|url|max:500',
            'estado' => 'in:activo,inactivo',
        ]);

        $curso = Curso::create($data);

        // Notificar al profesor asignado
        $profesor = Profesor::with('user')->find($curso->profesor_id);
        if ($profesor && $profesor->user) {
            $profesor->user->notify(new GenericNotification(
                'Asignación de Curso',
                "Has sido asignado como instructor del curso: {$curso->nombre}.",
                "/profesor/cursos/{$curso->id}"
            ));
        }

        return response()->json($curso->load('instructor'), 201);
    }

    public function show(string $id)
    {
        $curso = Curso::with('instructor.user', 'estudiantes.user', 'temario', 'sesiones')->findOrFail($id);

        return response()->json($curso);
    }

    public function update(Request $request, string $id)
    {
        $curso = Curso::findOrFail($id);

        $data = $request->validate([
            'profesor_id' => ['sometimes', Rule::exists('profesores', 'id')],
            'nombre' => 'sometimes|string|max:255',
            'limite_cupo' => 'sometimes|integer|min:1',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date|after_or_equal:fecha_inicio',
            'descripcion' => 'nullable|string',
            'requisitos' => 'nullable|string',
            'precio' => 'sometimes|numeric|min:0',
            'whatsapp_url' => 'nullable|url|max:500',
            'estado' => 'in:activo,inactivo',
        ]);

        $oldProfesorId = $curso->profesor_id;
        $curso->update($data);

        // Si se cambió el profesor, notificar al nuevo profesor
        if (isset($data['profesor_id']) && (int) $data['profesor_id'] !== (int) $oldProfesorId) {
            $profesor = Profesor::with('user')->find($data['profesor_id']);
            if ($profesor && $profesor->user) {
                $profesor->user->notify(new GenericNotification(
                    'Asignación de Curso',
                    "Has sido asignado como instructor del curso: {$curso->nombre}.",
                    "/profesor/cursos/{$curso->id}"
                ));
            }
        }

        return response()->json($curso->load('instructor'));
    }

    public function destroy(string $id)
    {
        Curso::findOrFail($id)->delete();

        return response()->json(['message' => 'Curso eliminado correctamente.']);
    }
}
