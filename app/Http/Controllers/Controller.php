<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\Request;

abstract class Controller
{
    /** Solo dígitos (teléfono, referencia). */
    public const REGEX_NUMERICO = 'regex:/^[0-9]+$/';

    /** Cédula: 7 u 8 dígitos numéricos. */
    public const REGEX_CEDULA = 'regex:/^\d{7,8}$/';

    /** Solo letras (incluye acentos), espacios, apóstrofes, puntos y guiones (nombres y apellidos). */
    public const REGEX_ALFABETICO = 'regex:/^[\pL\pM\s\'\-\.]+$/u';

    /** Solo letras (incluye acentos) y espacios (campos de nombre individuales). */
    public const REGEX_NOMBRES = 'regex:/^[\pL\s]+$/u';

    /** Dirección: cualquier carácter excepto comillas, backticks, punto y coma y backslash. */
    public const REGEX_DIRECCION = 'regex:/^[^\'"`;\\\\]+$/u';

    /** Tope máximo de registros por página. */
    public const MAX_PER_PAGE = 1000;

    /** Tope máximo de registros por página en los catálogos (estudiante y profesor). */
    public const MAX_PER_PAGE_CATALOGO = 100;

    /** Registros por página por defecto. */
    public const DEFAULT_PER_PAGE = 10;

    /**
     * Lee y acota el parámetro `per_page` para la paginación.
     * Un valor inválido (0, negativo o no numérico) cae al default.
     */
    protected function registrosPorPagina(Request $request, ?int $maxPerPage = null): int
    {
        $maxPerPage = $maxPerPage ?? self::MAX_PER_PAGE;
        $perPage = $request->integer('per_page', self::DEFAULT_PER_PAGE);

        if ($perPage < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($perPage, $maxPerPage);
    }

    /**
     * Mensajes en español para las reglas de tipo de dato
     * de los campos comunes de los formularios.
     */
    protected function mensajesTipoDato(): array
    {
        return [
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'primer_nombre.regex' => 'El primer nombre solo puede contener letras y espacios.',
            'segundo_nombre.regex' => 'El segundo nombre solo puede contener letras y espacios.',
            'primer_apellido.regex' => 'El primer apellido solo puede contener letras y espacios.',
            'segundo_apellido.regex' => 'El segundo apellido solo puede contener letras y espacios.',
            'cedula.regex' => 'La cédula debe tener 7 u 8 dígitos numéricos.',
            'telefono.regex' => 'El teléfono solo puede contener dígitos numéricos.',
            'direccion.regex' => 'La dirección contiene caracteres no permitidos.',
        ];
    }

    /**
     * Aplica `?sort=&direction=` sobre una lista blanca de columnas.
     *
     * La lista blanca no es opcional: `sort` viene del cliente y acaba dentro
     * de un ORDER BY, así que aceptar cualquier cadena sería inyección de SQL.
     * Cada clave del mapa es el nombre que usa la UI y su valor es la columna
     * real (o un closure que ordena por una relación).
     *
     * @param  array<string, string|callable>  $columnas
     */
    protected function aplicarOrden(
        Builder $query,
        Request $request,
        array $columnas,
        string $porDefecto,
        string $direccionPorDefecto = 'asc',
    ): void {
        $sort = (string) $request->query('sort', '');
        $direccion = strtolower((string) $request->query('direction', ''));

        if (! array_key_exists($sort, $columnas)) {
            $sort = $porDefecto;
            $direccion = $direccion !== '' ? $direccion : $direccionPorDefecto;
        }

        $direccion = in_array($direccion, ['asc', 'desc'], true) ? $direccion : $direccionPorDefecto;

        $columna = $columnas[$sort] ?? null;

        if (is_callable($columna)) {
            $columna($query, $direccion);

            return;
        }

        if (is_string($columna)) {
            $query->orderBy($columna, $direccion);
        }
    }

    /**
     * Término de búsqueda saneado.
     *
     * `%` y `_` son comodines de LIKE: sin escaparlos, buscar "100%" devuelve
     * la tabla entera.
     */
    protected function terminoBusqueda(Request $request, string $campo = 'search'): ?string
    {
        $valor = trim((string) $request->query($campo, ''));

        if ($valor === '') {
            return null;
        }

        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $valor);
    }
}
