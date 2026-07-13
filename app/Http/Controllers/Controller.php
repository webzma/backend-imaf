<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /** Solo dígitos (cédula, teléfono, referencia). */
    public const REGEX_NUMERICO = 'regex:/^[0-9]+$/';

    /** Solo letras (incluye acentos), espacios, apóstrofes, puntos y guiones (nombres y apellidos). */
    public const REGEX_ALFABETICO = 'regex:/^[\pL\pM\s\'\-\.]+$/u';

    /**
     * Mensajes en español para las reglas de tipo de dato
     * de los campos comunes de los formularios.
     */
    protected function mensajesTipoDato(): array
    {
        return [
            'name.regex' => 'El nombre solo puede contener letras y espacios.',
            'nombre.regex' => 'El nombre solo puede contener letras y espacios.',
            'cedula.regex' => 'La cédula solo puede contener dígitos numéricos.',
            'telefono.regex' => 'El teléfono solo puede contener dígitos numéricos.',
        ];
    }
}
