<?php

namespace App\Services;

use App\Models\Bitacora;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Punto único de escritura de la bitácora.
 *
 *     Auditoria::registrar('pago.aprobado', 'Pago aprobado', [
 *         'entidad' => $pago,
 *         'metadatos' => ['monto' => 100],
 *     ]);
 *
 * El contexto (actor, IP, navegador, ruta, id de solicitud) se toma solo. Un
 * fallo al escribir se registra en el log de la aplicación y no interrumpe la
 * operación: la bitácora observa, no decide.
 */
class Auditoria
{
    /** Atributo de la petición donde vive el id de solicitud. */
    public const ATRIBUTO_SOLICITUD = 'auditoria_solicitud_id';

    private const CATEGORIA_POR_PREFIJO = [
        'auth' => 'autenticacion',
        'seguridad' => 'seguridad',
        'bitacora' => 'sistema',
        'sistema' => 'sistema',
    ];

    private static bool $silenciada = false;

    private static bool $comoSistema = false;

    /**
     * @param  array{
     *     categoria?: string,
     *     nivel?: 'info'|'aviso'|'critico',
     *     actor?: User|null|false,
     *     entidad?: Model|array{0: string, 1: int|string, 2?: string|null},
     *     cambios?: array|null,
     *     metadatos?: array|null,
     * }  $opciones  `actor`: User explícito, false = "Sistema", omitido = el
     *               usuario autenticado.
     */
    public static function registrar(string $evento, string $descripcion, array $opciones = []): ?Bitacora
    {
        if (self::$silenciada || ! config('auditoria.habilitada')) {
            return null;
        }

        try {
            $actor = array_key_exists('actor', $opciones)
                ? $opciones['actor']
                : (self::$comoSistema ? false : Auth::user());
            [$entidad, $entidadId, $etiqueta] = self::entidad($opciones['entidad'] ?? null);

            $registro = new Bitacora([
                'evento' => $evento,
                'categoria' => $opciones['categoria'] ?? self::categoria($evento),
                'nivel' => $opciones['nivel'] ?? 'info',
                'descripcion' => Str::limit($descripcion, 497),
                'actor_id' => $actor instanceof User ? $actor->id : null,
                'actor_nombre' => $actor instanceof User ? $actor->name : ($actor === false ? 'Sistema' : null),
                'actor_rol' => $actor instanceof User ? $actor->role : null,
                'entidad' => $entidad,
                'entidad_id' => $entidadId,
                'entidad_etiqueta' => $etiqueta ? Str::limit($etiqueta, 250) : null,
                // Ida y vuelta por JSON: lo que se firma es exactamente lo que
                // se leerá después de la base de datos.
                'cambios' => self::normalizar($opciones['cambios'] ?? null),
                'metadatos' => self::normalizar($opciones['metadatos'] ?? null),
                ...self::contexto(),
            ]);

            $registro->id = (string) Str::ulid();
            $registro->created_at = now();
            $registro->huella = Bitacora::calcularHuella($registro->datosFirmados());
            $registro->save();

            return $registro;
        } catch (Throwable $e) {
            Log::error('No se pudo escribir en la bitácora: '.$e->getMessage(), ['evento' => $evento]);

            return null;
        }
    }

    /** Ejecuta `$callback` sin registrar nada (semillas, migraciones de datos). */
    public static function sinRegistro(callable $callback): mixed
    {
        $previo = self::$silenciada;
        self::$silenciada = true;

        try {
            return $callback();
        } finally {
            self::$silenciada = $previo;
        }
    }

    /**
     * Ejecuta `$callback` atribuyendo sus eventos al sistema, no al usuario de
     * la petición: procesos automáticos que corren dentro de una petición
     * ajena (p. ej. la sincronización de estados de cursos).
     */
    public static function comoSistema(callable $callback): mixed
    {
        $previo = self::$comoSistema;
        self::$comoSistema = true;

        try {
            return $callback();
        } finally {
            self::$comoSistema = $previo;
        }
    }

    /** Id de la solicitud actual (el mismo para todos sus eventos). */
    public static function solicitudId(): ?string
    {
        if (! app()->bound('request')) {
            return null;
        }
        $request = request();
        $id = $request->attributes->get(self::ATRIBUTO_SOLICITUD);
        if (! $id) {
            $id = (string) Str::uuid();
            $request->attributes->set(self::ATRIBUTO_SOLICITUD, $id);
        }

        return $id;
    }

    /** Oculta campos sensibles en cualquier nivel. */
    public static function ocultar(?array $datos): ?array
    {
        if ($datos === null) {
            return null;
        }
        $ocultos = array_map('strtolower', config('auditoria.campos_ocultos', []));

        foreach ($datos as $clave => $valor) {
            if (is_string($clave) && in_array(strtolower($clave), $ocultos, true)) {
                $datos[$clave] = '[oculto]';
            } elseif (is_array($valor)) {
                $datos[$clave] = self::ocultar($valor);
            }
        }

        return $datos;
    }

    private static function normalizar(?array $datos): ?array
    {
        if ($datos === null || $datos === []) {
            return null;
        }

        return json_decode(json_encode(self::ocultar($datos), JSON_UNESCAPED_UNICODE), true);
    }

    private static function categoria(string $evento): string
    {
        $prefijo = Str::before($evento, '.');

        return self::CATEGORIA_POR_PREFIJO[$prefijo] ?? 'negocio';
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} */
    private static function entidad(mixed $entidad): array
    {
        if ($entidad instanceof Model) {
            $alias = method_exists($entidad, 'auditEntidad')
                ? $entidad->auditEntidad()
                : Str::snake(class_basename($entidad));
            $etiqueta = method_exists($entidad, 'auditEtiqueta') ? $entidad->auditEtiqueta() : null;

            return [$alias, (string) $entidad->getKey(), $etiqueta];
        }
        if (is_array($entidad)) {
            return [$entidad[0], (string) $entidad[1], $entidad[2] ?? null];
        }

        return [null, null, null];
    }

    /** @return array<string, string|null> */
    private static function contexto(): array
    {
        $request = app()->bound('request') ? request() : null;
        $esHttp = $request && $request->route();

        if (! $esHttp) {
            $argv = $_SERVER['argv'] ?? [];

            return [
                'origen' => app()->runningInConsole() ? 'consola' : 'sistema',
                'solicitud_id' => null,
                'ip' => null,
                'user_agent' => null,
                'metodo' => null,
                'ruta' => app()->runningInConsole() && isset($argv[1])
                    ? Str::limit('artisan '.implode(' ', array_slice($argv, 1)), 250)
                    : null,
            ];
        }

        return [
            'origen' => 'http',
            'solicitud_id' => self::solicitudId(),
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500) ?: null,
            'metodo' => $request->method(),
            'ruta' => Str::limit('/'.ltrim($request->path(), '/'), 250),
        ];
    }
}
