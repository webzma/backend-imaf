<?php

namespace App\Http\Controllers;

use App\Models\Curso;
use App\Models\Temario;
use App\Notifications\GenericNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemarioController extends Controller
{
    /** @phpstan-return void */
    private function notifyInstructor(string $cursoId, string $accion): void
    {
        /** @var Curso|null $curso */
        $curso = Curso::query()->with('instructor.user')->find($cursoId);
        if ($curso && $curso->instructor && $curso->instructor->user) {
            $curso->instructor->user->notify(new GenericNotification(
                'Temario Actualizado',
                "El administrador ha {$accion} un tema en el temario del curso: {$curso->nombre}.",
                "/profesor/cursos/{$curso->id}"
            ));
        }
    }

    /** @phpstan-return JsonResponse */
    public function index(string $cursoId): JsonResponse
    {
        Curso::query()->findOrFail($cursoId);

        return response()->json(
            Temario::query()->where('curso_id', $cursoId)->orderBy('orden')->get()
        );
    }

    /** @phpstan-return JsonResponse */
    public function store(Request $request, string $cursoId): JsonResponse
    {
        Curso::query()->findOrFail($cursoId);

        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'orden' => 'nullable|integer|min:0',
        ]);

        if (! isset($data['orden'])) {
            $data['orden'] = Temario::query()->where('curso_id', $cursoId)->max('orden') + 1;
        }

        /** @var Temario $temario */
        $temario = Temario::query()->create(['curso_id' => $cursoId, ...$data]);

        $this->notifyInstructor($cursoId, 'agregado');

        return response()->json($temario, 201);
    }

    /** @phpstan-return JsonResponse */
    public function update(Request $request, string $cursoId, string $id): JsonResponse
    {
        /** @var Temario $temario */
        $temario = Temario::query()->where('curso_id', $cursoId)->findOrFail($id);

        $data = $request->validate([
            'titulo' => 'sometimes|string|max:255',
            'descripcion' => 'nullable|string',
            'orden' => 'nullable|integer|min:0',
        ]);

        $temario->update($data);

        // Las sesiones de este tema muestran su título: se mantienen al día.
        if ($temario->wasChanged('titulo')) {
            $temario->sesiones()->get()->each->update(['titulo' => $temario->titulo]);
        }

        $this->notifyInstructor($cursoId, 'actualizado');

        return response()->json($temario);
    }

    /** @phpstan-return JsonResponse */
    public function destroy(string $cursoId, string $id): JsonResponse
    {
        /** @var Temario $temario */
        $temario = Temario::query()->where('curso_id', $cursoId)->findOrFail($id);
        $temario->delete();

        $this->notifyInstructor($cursoId, 'eliminado');

        return response()->json(['message' => 'Tema eliminado.']);
    }
}
