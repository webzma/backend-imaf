<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Services\Auditoria;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Consulta de la bitácora de auditoría (solo administradores).
 *
 * Es de solo lectura: no hay rutas para modificar ni borrar registros. La
 * exportación también queda registrada, porque sacar la bitácora del sistema
 * es en sí un evento relevante.
 */
class BitacoraController extends Controller
{
    /** Listado paginado, del más reciente al más antiguo, con filtros. */
    public function index(Request $request)
    {
        $filtros = $this->filtros($request);

        $registros = Bitacora::query()
            ->filtrar($filtros)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->registrosPorPagina($request));

        $registros->getCollection()->transform(fn (Bitacora $b) => $this->formato($b));

        return response()->json($registros);
    }

    /** Un registro con su detalle completo y si su huella es válida. */
    public function show(string $id)
    {
        $registro = Bitacora::findOrFail($id);

        return response()->json([
            ...$this->formato($registro, detalle: true),
            'integridad' => $registro->huellaValida() ? 'valida' : 'alterada',
            // Otros eventos de la misma petición, para ver el contexto.
            'relacionados' => $registro->solicitud_id
                ? Bitacora::where('solicitud_id', $registro->solicitud_id)
                    ->whereKeyNot($registro->id)
                    ->orderBy('created_at')
                    ->limit(50)
                    ->get()
                    ->map(fn (Bitacora $b) => $this->formato($b))
                : [],
        ]);
    }

    /** Historial completo de una entidad (p. ej. /entidades/curso/5). */
    public function historial(Request $request, string $entidad, string $id)
    {
        $registros = Bitacora::where('entidad', $entidad)
            ->where('entidad_id', $id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->registrosPorPagina($request));

        $registros->getCollection()->transform(fn (Bitacora $b) => $this->formato($b));

        return response()->json($registros);
    }

    /** Indicadores para un panel de seguridad y actividad. */
    public function resumen()
    {
        $hace24h = now()->subDay();
        $hace7d = now()->subDays(7);

        $porDia = Bitacora::where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->get(['created_at', 'nivel'])
            ->groupBy(fn (Bitacora $b) => $b->created_at->toDateString())
            ->map(fn ($grupo) => [
                'total' => $grupo->count(),
                'criticos' => $grupo->where('nivel', 'critico')->count(),
            ]);

        $serie = collect(range(13, 0))->map(function (int $dias) use ($porDia) {
            $fecha = now()->subDays($dias)->toDateString();

            return ['fecha' => $fecha, ...($porDia[$fecha] ?? ['total' => 0, 'criticos' => 0])];
        });

        return response()->json([
            'total' => Bitacora::count(),
            'ultimas_24h' => Bitacora::where('created_at', '>=', $hace24h)->count(),
            'por_categoria_7d' => $this->conteo('categoria', $hace7d),
            'por_nivel_7d' => $this->conteo('nivel', $hace7d),
            'logins_fallidos_24h' => Bitacora::where('evento', 'auth.login_fallido')
                ->where('created_at', '>=', $hace24h)->count(),
            'accesos_denegados_24h' => Bitacora::where('evento', 'seguridad.acceso_denegado')
                ->where('created_at', '>=', $hace24h)->count(),
            'ips_con_mas_fallos_24h' => Bitacora::where('evento', 'auth.login_fallido')
                ->where('created_at', '>=', $hace24h)
                ->select('ip', DB::raw('COUNT(*) as total'))
                ->groupBy('ip')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
            'actores_mas_activos_7d' => Bitacora::whereNotNull('actor_id')
                ->where('created_at', '>=', $hace7d)
                ->select('actor_id', 'actor_nombre', 'actor_rol', DB::raw('COUNT(*) as total'))
                ->groupBy('actor_id', 'actor_nombre', 'actor_rol')
                ->orderByDesc('total')
                ->limit(5)
                ->get(),
            'criticos_recientes' => Bitacora::where('nivel', 'critico')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->map(fn (Bitacora $b) => $this->formato($b)),
            'serie_14d' => $serie,
        ]);
    }

    /** Valores posibles de los filtros, sacados de lo registrado. */
    public function opciones()
    {
        return response()->json([
            'categorias' => Bitacora::CATEGORIAS,
            'niveles' => Bitacora::NIVELES,
            'eventos' => Bitacora::query()->distinct()->orderBy('evento')->pluck('evento'),
            'entidades' => Bitacora::query()->whereNotNull('entidad')->distinct()->orderBy('entidad')->pluck('entidad'),
        ]);
    }

    /** CSV con los mismos filtros que el listado (máximo configurable). */
    public function exportar(Request $request): StreamedResponse
    {
        $filtros = $this->filtros($request);
        $maximo = (int) config('auditoria.max_exportacion', 50000);
        $total = Bitacora::query()->filtrar($filtros)->count();

        Auditoria::registrar('bitacora.exportada', 'Exportación de la bitácora ('.min($total, $maximo).' registros)', [
            'nivel' => 'aviso',
            'metadatos' => ['filtros' => $filtros, 'registros' => min($total, $maximo)],
        ]);

        $nombre = 'bitacora-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($filtros, $maximo) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, [
                'Fecha', 'Evento', 'Categoría', 'Nivel', 'Descripción', 'Actor', 'Rol',
                'Entidad', 'Entidad ID', 'IP', 'Método', 'Ruta', 'Solicitud', 'Cambios', 'Metadatos',
            ]);

            $escritos = 0;
            Bitacora::query()
                ->filtrar($filtros)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->lazy(500)
                ->each(function (Bitacora $b) use ($salida, $maximo, &$escritos) {
                    if ($escritos >= $maximo) {
                        return false;
                    }
                    fputcsv($salida, [
                        $b->created_at?->format('Y-m-d H:i:s'),
                        $b->evento,
                        $b->categoria,
                        $b->nivel,
                        $b->descripcion,
                        $b->actor_nombre,
                        $b->actor_rol,
                        $b->entidad,
                        $b->entidad_id,
                        $b->ip,
                        $b->metodo,
                        $b->ruta,
                        $b->solicitud_id,
                        $b->cambios ? json_encode($b->cambios, JSON_UNESCAPED_UNICODE) : '',
                        $b->metadatos ? json_encode($b->metadatos, JSON_UNESCAPED_UNICODE) : '',
                    ]);
                    $escritos++;
                });

            fclose($salida);
        }, $nombre, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function filtros(Request $request): array
    {
        $datos = $request->validate([
            'categoria' => 'nullable',
            'categoria.*' => 'in:'.implode(',', Bitacora::CATEGORIAS),
            'nivel' => 'nullable',
            'nivel.*' => 'in:'.implode(',', Bitacora::NIVELES),
            'evento' => 'nullable|string|max:100',
            'actor_id' => 'nullable|integer',
            'entidad' => 'nullable|string|max:50',
            'entidad_id' => 'nullable|string|max:50',
            'ip' => 'nullable|ip',
            'solicitud_id' => 'nullable|uuid',
            'origen' => 'nullable|in:http,consola,sistema',
            'desde' => 'nullable|date',
            'hasta' => 'nullable|date|after_or_equal:desde',
            'q' => 'nullable|string|max:100',
        ]);

        // "categoria=seguridad,autenticacion" o categoria[]=...
        foreach (['categoria', 'nivel'] as $campo) {
            if (isset($datos[$campo]) && is_string($datos[$campo])) {
                $datos[$campo] = array_filter(explode(',', $datos[$campo]));
                validator(
                    [$campo => $datos[$campo]],
                    ["{$campo}.*" => 'in:'.implode(',', $campo === 'nivel' ? Bitacora::NIVELES : Bitacora::CATEGORIAS)],
                )->validate();
            }
        }

        // Una fecha sin hora cubre el día completo.
        if (! empty($datos['desde'])) {
            $datos['desde'] = strlen($datos['desde']) <= 10
                ? Carbon::parse($datos['desde'])->startOfDay()
                : Carbon::parse($datos['desde']);
        }
        if (! empty($datos['hasta'])) {
            $datos['hasta'] = strlen($datos['hasta']) <= 10
                ? Carbon::parse($datos['hasta'])->endOfDay()
                : Carbon::parse($datos['hasta']);
        }

        return array_filter($datos, fn ($v) => $v !== null && $v !== '' && $v !== []);
    }

    /** @return array<string, mixed> */
    private function conteo(string $campo, Carbon $desde): array
    {
        return Bitacora::where('created_at', '>=', $desde)
            ->select($campo, DB::raw('COUNT(*) as total'))
            ->groupBy($campo)
            ->pluck('total', $campo)
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @return array<string, mixed> */
    private function formato(Bitacora $b, bool $detalle = false): array
    {
        $base = [
            'id' => $b->id,
            'fecha' => $b->created_at?->toIso8601String(),
            'evento' => $b->evento,
            'categoria' => $b->categoria,
            'nivel' => $b->nivel,
            'descripcion' => $b->descripcion,
            'actor' => $b->actor_nombre || $b->actor_id ? [
                'id' => $b->actor_id,
                'nombre' => $b->actor_nombre,
                'rol' => $b->actor_rol,
            ] : null,
            'entidad' => $b->entidad ? [
                'tipo' => $b->entidad,
                'id' => $b->entidad_id,
                'etiqueta' => $b->entidad_etiqueta,
            ] : null,
            'ip' => $b->ip,
            'origen' => $b->origen,
            'solicitud_id' => $b->solicitud_id,
        ];

        if (! $detalle) {
            return $base + ['tiene_cambios' => ! empty($b->cambios)];
        }

        return $base + [
            'cambios' => $b->cambios,
            'metadatos' => $b->metadatos,
            'user_agent' => $b->user_agent,
            'metodo' => $b->metodo,
            'ruta' => $b->ruta,
        ];
    }
}
