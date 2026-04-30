<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // Convertir los valores actuales de profesor_id (user_id) al id real de profesores.
        // Usa subquery en lugar de UPDATE…JOIN para compatibilidad con SQLite.
        DB::statement('
            UPDATE cursos
            SET profesor_id = (
                SELECT p.id FROM profesores p WHERE p.user_id = cursos.profesor_id
            )
            WHERE EXISTS (
                SELECT 1 FROM profesores p WHERE p.user_id = cursos.profesor_id
            )
        ');

        Schema::table('cursos', function (Blueprint $table) {
            $table->dropForeign(['profesor_id']);
        });

        Schema::table('cursos', function (Blueprint $table) {
            $table->foreign('profesor_id')
                ->references('id')
                ->on('profesores')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropForeign(['profesor_id']);
        });

        DB::statement('
            UPDATE cursos
            SET profesor_id = (
                SELECT p.user_id FROM profesores p WHERE p.id = cursos.profesor_id
            )
            WHERE EXISTS (
                SELECT 1 FROM profesores p WHERE p.id = cursos.profesor_id
            )
        ');

        Schema::table('cursos', function (Blueprint $table) {
            $table->foreign('profesor_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
};
