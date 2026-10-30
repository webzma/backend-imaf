<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nivel del curso. Es opcional para no invalidar los cursos que ya existían;
 * si se borra el nivel desde el catálogo, el curso queda sin nivel.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->foreignId('nivel_curso_id')
                ->nullable()
                ->after('profesor_id')
                ->constrained('niveles_cursos')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nivel_curso_id');
        });
    }
};
