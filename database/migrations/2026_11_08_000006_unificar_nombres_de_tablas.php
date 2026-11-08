<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/** Nombres en plural y con el sustantivo principal delante. */
return new class() extends Migration
{
    private const NOMBRES = [
        'tipo_contratos' => 'tipos_contrato',
        'niveles_cursos' => 'niveles_curso',
        'bitacora' => 'bitacoras',
    ];

    public function up(): void
    {
        foreach (self::NOMBRES as $viejo => $nuevo) {
            Schema::rename($viejo, $nuevo);
        }
    }

    public function down(): void
    {
        foreach (self::NOMBRES as $viejo => $nuevo) {
            Schema::rename($nuevo, $viejo);
        }
    }
};
