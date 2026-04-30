<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->enum('estado_pago', ['pendiente', 'aprobado', 'reprobado'])->default('pendiente')->after('estado');
            $table->enum('estado_aprobacion_curso', ['pendiente', 'aprobado', 'reprobado'])->default('pendiente')->after('estado_pago');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->dropColumn(['estado_pago', 'estado_aprobacion_curso']);
        });
    }
};
