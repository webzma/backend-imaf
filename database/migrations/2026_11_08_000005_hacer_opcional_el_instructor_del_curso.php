<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un curso puede quedarse sin instructor. Borrar a un profesor ya no elimina
 * sus cursos ni, en cadena, sus inscripciones, sesiones y asistencias.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', fn (Blueprint $t) => $t->dropForeign(['profesor_id']));
        Schema::table('cursos', fn (Blueprint $t) => $t->unsignedBigInteger('profesor_id')->nullable()->change());
        Schema::table('cursos', fn (Blueprint $t) => $t
            ->foreign('profesor_id')->references('id')->on('profesores')->nullOnDelete());
    }

    public function down(): void
    {
        if (DB::table('cursos')->whereNull('profesor_id')->exists()) {
            throw new RuntimeException('Hay cursos sin instructor: asígnales uno antes de revertir.');
        }

        Schema::table('cursos', fn (Blueprint $t) => $t->dropForeign(['profesor_id']));
        Schema::table('cursos', fn (Blueprint $t) => $t->unsignedBigInteger('profesor_id')->nullable(false)->change());
        Schema::table('cursos', fn (Blueprint $t) => $t
            ->foreign('profesor_id')->references('id')->on('profesores')->onDelete('cascade'));
    }
};
