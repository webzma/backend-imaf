<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // 1. Convertir los valores actuales de profesor_id (user_id) al id real de profesores
        DB::statement('
            UPDATE cursos c
            JOIN profesores p ON p.user_id = c.profesor_id
            SET c.profesor_id = p.id
        ');

        // 2. Quitar el FK viejo que apuntaba a users.id
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropForeign(['profesor_id']);
        });

        // 3. Agregar el FK correcto apuntando a profesores.id
        Schema::table('cursos', function (Blueprint $table) {
            $table->foreign('profesor_id')
                ->references('id')
                ->on('profesores')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Revertir FK a users.id
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropForeign(['profesor_id']);
        });

        // Reconvertir ids de profesores → user_id
        DB::statement('
            UPDATE cursos c
            JOIN profesores p ON p.id = c.profesor_id
            SET c.profesor_id = p.user_id
        ');

        Schema::table('cursos', function (Blueprint $table) {
            $table->foreign('profesor_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }
};
