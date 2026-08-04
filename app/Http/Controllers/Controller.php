<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Solo dígitos (teléfono, referencia). */
    public const REGEX_NUMERICO = 'regex:/^[0-9]+$/';

    /** Cédula dominicana: 11 dígitos, con o sin guiones (001-1234567-8). */
    public const REGEX_CEDULA = 'regex:/^\d{3}-?\d{7}-?\d$/';

    /** Solo letras (incluye acentos), espacios, apóstrofes, puntos y guiones (nombres y apellidos). */
    public const REGEX_ALFABETICO = 'regex:/^[\pL\pM\s\'\-\.]+$/u';

    /** Solo letras (incluye acentos) y espacios (campos de nombre individuales). */
    public const REGEX_NOMBRES = 'regex:/^[\pL\s]+$/u';

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
            'cedula.regex' => 'La cédula debe tener 11 dígitos, con o sin guiones (001-1234567-8).',
            'telefono.regex' => 'El teléfono solo puede contener dígitos numéricos.',
        ];
    }
}
