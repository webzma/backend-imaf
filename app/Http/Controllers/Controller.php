<?php

namespace App\Http\Controllers;

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

    /**
     * Lee y acota el parámetro `per_page` para la paginación
     * (entero positivo, default 10, tope MAX_PER_PAGE).
     */
    protected function registrosPorPagina(Request $request): int
    {
        $perPage = $request->integer('per_page', 10);

        return max(1, min($perPage, self::MAX_PER_PAGE));
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
}
