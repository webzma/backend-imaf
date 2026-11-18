<?php

namespace App\Http\Controllers;

use App\Models\Departamento;
use App\Models\Especialidad;
use App\Models\Profesor;
use App\Models\TipoContrato;
use App\Models\Titulo;
use App\Models\User;
use Cloudinary\Cloudinary as CloudinaryClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProfesorController extends Controller
{
    /**
     * Columnas ordenables. El nombre vive en `users`, así que se ordena por
     * subconsulta en vez de arrastrar un join a toda la lista.
     *
     * @return array<string,mixed>
     */
    private function ordenProfesores(): array
    {
        return [
            'nombre' => fn ($q, $dir) => $q->orderBy(
                User::query()->select('name')->whereColumn('users.id', 'profesores.user_id'),
                $dir
            ),
            'cedula' => 'cedula',
            'municipio' => 'municipio',
        ];
    }

    /** @phpstan-return JsonResponse */
    public function index(Request $request): JsonResponse
    {
        $query = Profesor::query()->with('user', 'tipoContrato', 'especialidad', 'departamento', 'titulo');

        // Los filtros se resuelven en la base de datos. Antes la pantalla
        // filtraba en memoria sobre los 10 registros de la página actual, de
        // modo que buscar a alguien de la página 4 daba "sin resultados".
        if ($search = $this->terminoBusqueda($request)) {
            $query->where(function ($q) use ($search) {
                $q->where('cedula', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                    ->orWhereHas('especialidad', fn ($e) => $e->where('nombre', 'like', "%{$search}%"))
                    ->orWhereHas('departamento', fn ($d) => $d->where('nombre', 'like', "%{$search}%"));
            });
        }

        foreach (['titulo_id', 'departamento_id', 'especialidad_id', 'tipo_contrato_id'] as $filtro) {
            if (! $request->filled($filtro)) {
                continue;
            }

            str_starts_with((string) $request->input($filtro), 'sin_')
                ? $query->whereNull($filtro)
                : $query->where($filtro, $request->input($filtro));
        }

        if ($request->filled('municipio')) {
            $request->municipio === 'sin_municipio'
                ? $query->whereNull('municipio')
                : $query->where('municipio', $request->municipio);
        }

        $this->aplicarOrden($query, $request, $this->ordenProfesores(), 'nombre');

        return response()->json($query->paginate($this->registrosPorPagina($request)));
    }

    /**
     * Totales sobre la tabla completa, para las tarjetas de resumen.
     *
     * @phpstan-return JsonResponse
     */
    public function resumen(): JsonResponse
    {
        return response()->json([
            'total' => Profesor::query()->count(),
            'con_titulo' => Profesor::query()->whereNotNull('titulo_id')->count(),
            'departamentos' => Profesor::query()->whereNotNull('departamento_id')
                ->distinct('departamento_id')
                ->count('departamento_id'),
            'con_contrato' => Profesor::query()->whereNotNull('tipo_contrato_id')->count(),
        ]);
    }

    /*
     * Estas rutas explícitas atienden el GET de la pantalla de Catálogos (van
     * antes del `{slug}` de CatalogoController). `profesores_count` permite
     * avisar cuántos instructores usan cada opción antes de borrarla.
     */

    /** @phpstan-return JsonResponse */
    public function getTipoContratos(): JsonResponse
    {
        return response()->json(TipoContrato::query()->withCount('profesores')->orderBy('nombre')->get());
    }

    /** @phpstan-return JsonResponse */
    public function getEspecialidades(): JsonResponse
    {
        return response()->json(Especialidad::query()->withCount('profesores')->orderBy('nombre')->get());
    }

    /** @phpstan-return JsonResponse */
    public function getDepartamentos(): JsonResponse
    {
        return response()->json(Departamento::query()->withCount('profesores')->orderBy('nombre')->get());
    }

    /** @phpstan-return JsonResponse */
    public function getTitulos(): JsonResponse
    {
        return response()->json(Titulo::query()->withCount('profesores')->orderBy('nombre')->get());
    }

    /** @phpstan-return JsonResponse */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'primer_nombre' => ['required', 'string', 'max:100', self::REGEX_NOMBRES],
            'segundo_nombre' => ['nullable', 'string', 'max:100', self::REGEX_NOMBRES],
            'primer_apellido' => ['required', 'string', 'max:100', self::REGEX_NOMBRES],
            'segundo_apellido' => ['required', 'string', 'max:100', self::REGEX_NOMBRES],
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'nacionalidad' => 'required|in:V,E',
            'cedula' => ['required', 'string', 'max:15', 'unique:profesores,cedula', self::REGEX_CEDULA],
            'telefono' => ['nullable', 'string', 'max:20', self::REGEX_NUMERICO],
            'municipio' => 'nullable|string|max:255',
            'especialidad_id' => 'required|exists:especialidades,id',
            'titulo_id' => 'required|exists:titulos,id',
            'departamento_id' => 'required|exists:departamentos,id',
            'fecha_nacimiento' => 'nullable|date',
            'genero' => 'nullable|in:masculino,femenino,otro',
            'tipo_contrato_id' => 'required|exists:tipos_contrato,id',
        ], $this->mensajesTipoDato());

        /** @var Profesor $profesor */
        $profesor = DB::transaction(function () use ($request) {
            $user = User::query()->create([
                'primer_nombre' => $request->primer_nombre,
                'segundo_nombre' => $request->segundo_nombre,
                'primer_apellido' => $request->primer_apellido,
                'segundo_apellido' => $request->segundo_apellido,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'profesor',
            ]);

            /** @var Profesor $profesor */
            $profesor = Profesor::query()->create([
                'user_id' => $user->id,
                'nacionalidad' => $request->nacionalidad,
                'cedula' => $request->cedula,
                'telefono' => $request->telefono,
                'municipio' => $request->municipio,
                'especialidad_id' => $request->especialidad_id,
                'titulo_id' => $request->titulo_id,
                'departamento_id' => $request->departamento_id,
                'fecha_nacimiento' => $request->fecha_nacimiento,
                'genero' => $request->genero,
                'tipo_contrato_id' => $request->tipo_contrato_id,
            ]);

            return $profesor;
        });

        return response()->json($profesor->load('user', 'tipoContrato', 'especialidad', 'departamento', 'titulo'), 201);
    }

    /** @phpstan-return JsonResponse */
    public function show(string $id): JsonResponse
    {
        /** @var Profesor $profesor */
        $profesor = Profesor::query()->with('user', 'cursos', 'tipoContrato', 'especialidad', 'departamento', 'titulo')->findOrFail($id);

        return response()->json($profesor);
    }

    /** @phpstan-return JsonResponse */
    public function update(Request $request, string $id): JsonResponse
    {
        /** @var Profesor $profesor */
        $profesor = Profesor::query()->with('user')->findOrFail($id);
        $authUser = Auth::user();

        $isAdmin = $authUser->role === 'admin';
        $isSelf = (int) $profesor->user_id === (int) $authUser->id;

        if (! $isAdmin && ! $isSelf) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $rules = [
            'nacionalidad' => ['sometimes', 'in:V,E'],
            'cedula' => ['sometimes', 'string', 'max:15', 'unique:profesores,cedula,'.$id, self::REGEX_CEDULA],
            'telefono' => ['nullable', 'string', 'max:20', self::REGEX_NUMERICO],
            'municipio' => 'nullable|string|max:255',
            'especialidad_id' => 'nullable|exists:especialidades,id',
            'titulo_id' => 'nullable|exists:titulos,id',
            'departamento_id' => 'nullable|exists:departamentos,id',
            'fecha_nacimiento' => 'nullable|date',
            'genero' => 'nullable|in:masculino,femenino,otro',
            'tipo_contrato_id' => 'sometimes|exists:tipos_contrato,id',
        ];

        if ($isAdmin) {
            $rules['primer_nombre'] = ['sometimes', 'string', 'max:100', self::REGEX_NOMBRES];
            $rules['segundo_nombre'] = ['nullable', 'string', 'max:100', self::REGEX_NOMBRES];
            $rules['primer_apellido'] = ['sometimes', 'string', 'max:100', self::REGEX_NOMBRES];
            $rules['segundo_apellido'] = ['sometimes', 'string', 'max:100', self::REGEX_NOMBRES];
            $rules['email'] = 'sometimes|email|unique:users,email,'.$profesor->user_id;
        }

        $data = $request->validate($rules, $this->mensajesTipoDato());

        if ($isAdmin) {
            $userFields = array_intersect_key($data, array_flip([
                'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'email',
            ]));
            if (! empty($userFields)) {
                $profesor->user->update($userFields);
            }
        }

        $profesor->update(array_diff_key($data, array_flip([
            'primer_nombre', 'segundo_nombre', 'primer_apellido', 'segundo_apellido', 'email',
        ])));

        return response()->json($profesor->load('user', 'tipoContrato', 'especialidad', 'departamento', 'titulo'));
    }

    /** @phpstan-return JsonResponse */
    public function uploadFotoMe(Request $request): JsonResponse
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        /** @var Profesor $profesor */
        $profesor = Profesor::query()->where('user_id', Auth::id())->firstOrFail();

        try {
            $upload = app(CloudinaryClient::class)->uploadApi()->upload(
                $this->archivoRequerido($request, 'foto')->getRealPath(),
                [
                    'folder' => 'imaf/perfiles',
                    'public_id' => 'profesor_'.$profesor->id,
                    'overwrite' => true,
                    'invalidate' => true,
                    'transformation' => [
                        'width' => 400,
                        'height' => 400,
                        'crop' => 'fill',
                        'gravity' => 'face',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::error('Error subiendo foto de profesor: '.$e->getMessage());

            return response()->json([
                'message' => 'No se pudo subir la imagen. Inténtalo de nuevo.',
            ], 500);
        }

        $profesor->update(['foto' => $upload['secure_url']]);
        $profesor->refresh();

        return response()->json($profesor->load('user', 'tipoContrato', 'especialidad', 'departamento', 'titulo'));
    }

    private function archivoRequerido(Request $request, string $campo): UploadedFile
    {
        $archivo = $request->file($campo);

        if (! $archivo instanceof UploadedFile) {
            throw ValidationException::withMessages([
                $campo => ['El archivo es obligatorio.'],
            ]);
        }

        return $archivo;
    }

    /** @phpstan-return JsonResponse */
    public function destroy(string $id): JsonResponse
    {
        Profesor::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Profesor eliminado correctamente.']);
    }
}
