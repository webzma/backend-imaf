<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class CatalogoController extends Controller
{
    /**
     * Mapa de slug → modelo Eloquent.
     */
    private const MODELS = [
        'especialidades'  => \App\Models\Especialidad::class,
        'departamentos'   => \App\Models\Departamento::class,
        'titulos'         => \App\Models\Titulo::class,
        'tipo-contratos'  => \App\Models\TipoContrato::class,
    ];

    /**
     * Retorna el modelo Eloquent para el slug dado.
     */
    private function modelo(string $slug): Model
    {
        $class = self::MODELS[$slug] ?? null;

        if (! $class || ! class_exists($class)) {
            abort(404);
        }

        return new $class;
    }

    /**
     * GET /api/admin/{slug}
     */
    public function index(string $slug): JsonResponse
    {
        $modelo = $this->modelo($slug);

        return response()->json($modelo::orderBy('nombre')->get());
    }

    /**
     * POST /api/admin/{slug}
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $modelo = $this->modelo($slug);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:'.$modelo->getTable().',nombre', self::REGEX_ALFABETICO],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique'   => 'Ya existe un registro con ese nombre.',
            'nombre.regex'    => 'El nombre solo puede contener letras, espacios y guiones.',
        ]);

        $registro = $modelo->create($data);

        return response()->json($registro, 201);
    }

    /**
     * PUT /api/admin/{slug}/{id}
     */
    public function update(Request $request, string $slug, string $id): JsonResponse
    {
        $modelo = $this->modelo($slug);
        $registro = $modelo->findOrFail($id);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:'.$modelo->getTable().',nombre,'.$id, self::REGEX_ALFABETICO],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.unique'   => 'Ya existe un registro con ese nombre.',
            'nombre.regex'    => 'El nombre solo puede contener letras, espacios y guiones.',
        ]);

        $registro->update($data);

        return response()->json($registro);
    }

    /**
     * DELETE /api/admin/{slug}/{id}
     */
    public function destroy(string $slug, string $id): JsonResponse
    {
        $modelo = $this->modelo($slug);
        $registro = $modelo->findOrFail($id);
        $registro->delete();

        return response()->json(['message' => 'Registro eliminado correctamente.']);
    }
}
