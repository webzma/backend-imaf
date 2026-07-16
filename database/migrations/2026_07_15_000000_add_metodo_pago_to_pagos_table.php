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
        Schema::table('pagos', function (Blueprint $table) {
            $table->string('metodo_pago')->default('pago_movil')->after('curso_id');
            // El pago en efectivo se reporta sin referencia ni comprobante
            $table->string('referencia')->nullable()->change();
            $table->string('banco_origen')->nullable()->change();
            $table->string('comprobante')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropColumn('metodo_pago');
        });
    }
};
