<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de niveles de curso (básico, intermedio, avanzado…). Es una lista
 * editable desde Configuración › Catálogos, igual que las de instructores.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('niveles_cursos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('niveles_cursos');
    }
};
