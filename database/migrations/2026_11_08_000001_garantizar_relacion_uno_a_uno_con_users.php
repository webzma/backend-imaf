<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Un usuario tiene como máximo una ficha de estudiante y una de profesor. */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('estudiantes', fn (Blueprint $t) => $t->unique('user_id'));
        Schema::table('profesores', fn (Blueprint $t) => $t->unique('user_id'));
    }

    public function down(): void
    {
        // MySQL exige un índice para la FK: se crea el simple antes de quitar el único.
        foreach (['estudiantes', 'profesores'] as $tabla) {
            // Puede existir ya si la migración se revirtió antes.
            if (! Schema::hasIndex($tabla, "{$tabla}_user_id_index")) {
                Schema::table($tabla, fn (Blueprint $t) => $t->index('user_id'));
            }

            Schema::table($tabla, fn (Blueprint $t) => $t->dropUnique(['user_id']));
        }
    }
};
