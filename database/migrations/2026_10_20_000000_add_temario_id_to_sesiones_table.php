<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada sesión puede corresponder a un tema del temario del curso. El título
 * de la sesión se copia del tema; si el tema se borra, la sesión conserva su
 * título y queda sin enlace.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('sesiones', function (Blueprint $table) {
            $table->foreignId('temario_id')
                ->nullable()
                ->after('curso_id')
                ->constrained('temarios')
                ->nullOnDelete();
        });

        // Sesiones existentes cuyo título coincide con un tema de su curso.
        DB::table('sesiones')->whereNull('temario_id')->orderBy('id')->each(function ($sesion) {
            $tema = DB::table('temarios')
                ->where('curso_id', $sesion->curso_id)
                ->whereRaw('LOWER(TRIM(titulo)) = ?', [mb_strtolower(trim($sesion->titulo))])
                ->value('id');

            if ($tema) {
                DB::table('sesiones')->where('id', $sesion->id)->update(['temario_id' => $tema]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('sesiones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('temario_id');
        });
    }
};
