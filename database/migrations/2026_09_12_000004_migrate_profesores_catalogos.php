<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // ── Add FK columns (nullable for existing data) ──
        Schema::table('profesores', function (Blueprint $table) {
            if (! Schema::hasColumn('profesores', 'especialidad_id')) {
                $table->foreignId('especialidad_id')->nullable()->constrained('especialidades')->onDelete('set null');
            }
            if (! Schema::hasColumn('profesores', 'departamento_id')) {
                $table->foreignId('departamento_id')->nullable()->constrained('departamentos')->onDelete('set null');
            }
            if (! Schema::hasColumn('profesores', 'titulo_id')) {
                $table->foreignId('titulo_id')->nullable()->constrained('titulos')->onDelete('set null');
            }
        });

        // ── Drop old columns ──
        Schema::table('profesores', function (Blueprint $table) {
            $table->dropColumn(['especialidad', 'departamento', 'titulo']);
        });
    }

    public function down(): void
    {
        // ── Restore old columns ──
        Schema::table('profesores', function (Blueprint $table) {
            $table->string('especialidad')->nullable()->after('telefono');
            $table->string('departamento')->nullable()->after('especialidad');
            $table->enum('titulo', ['licenciatura', 'maestria', 'doctorado'])->nullable()->after('departamento');
        });

        // ── Drop FK columns ──
        Schema::table('profesores', function (Blueprint $table) {
            $table->dropForeign(['especialidad_id']);
            $table->dropColumn('especialidad_id');
            $table->dropForeign(['departamento_id']);
            $table->dropColumn('departamento_id');
            $table->dropForeign(['titulo_id']);
            $table->dropColumn('titulo_id');
        });
    }
};
