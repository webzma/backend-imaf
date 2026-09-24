<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Especialidad;
use App\Models\TipoContrato;
use App\Models\Titulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    /**
     * Mapa de slug → modelo Eloquent.
     */
    private const MODELS = [
        'especialidades' => Especialidad::class,
        'departamentos' => Departamento::class,
        'titulos' => Titulo::class,
        'tipo-contratos' => TipoContrato::class,
    ];

    /**
     * Nombres de catálogo: letras, números, espacios y . - ' ( ). Antes solo
     * letras y el mensaje decía "letras, espacios y guiones", así que
     * "T.S.U. en Informática" pasaba pero "Contrato 2026" no, sin explicación.
     */
    private const REGEX_NOMBRE = 'regex:/^[\pL\pM\d\s\'\-\.\(\)]+$/u';

    private const MENSAJES = [
        'nombre.required' => 'El nombre es obligatorio.',
        'nombre.unique' => 'Ya existe un registro con ese nombre.',
        'nombre.regex' => "El nombre solo puede contener letras, números, espacios y los signos . - ' ( ).",
        'nombre.max' => 'El nombre no puede superar los 255 caracteres.',
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

        return new $class();
    }

    /**
     * GET /api/admin/{slug}
     */
    public function index(string $slug): JsonResponse
    {
        $modelo = $this->modelo($slug);

        // `profesores_count`: la pantalla avisa cuántos instructores lo usan
        // antes de borrarlo (al borrar, el campo les queda vacío).
        return response()->json($modelo::withCount('profesores')->orderBy('nombre')->get());
    }

    /**
     * POST /api/admin/{slug}
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $modelo = $this->modelo($slug);

        $request->merge(['nombre' => trim((string) $request->input('nombre'))]);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:'.$modelo->getTable().',nombre', self::REGEX_NOMBRE],
        ], self::MENSAJES);

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

        $request->merge(['nombre' => trim((string) $request->input('nombre'))]);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:255', 'unique:'.$modelo->getTable().',nombre,'.$id, self::REGEX_NOMBRE],
        ], self::MENSAJES);

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
