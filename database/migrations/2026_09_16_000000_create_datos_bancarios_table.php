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
        Schema::create('datos_bancarios', function (Blueprint $table) {
            $table->id();
            $table->string('tipo')->comment('pago_movil o transferencia');
            $table->string('rif')->comment('RIF de la institución');
            $table->string('banco')->comment('Nombre del banco');
            $table->string('telefono')->nullable()->comment('Teléfono para pago móvil');
            $table->decimal('monto', 10, 2)->comment('Monto en bolívares');
            $table->string('concepto')->comment('Concepto del pago');
            $table->string('numero_cuenta')->nullable()->comment('Número de cuenta para transferencia');
            $table->string('nombre_titular')->nullable()->comment('Nombre del titular para transferencia');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datos_bancarios');
    }
};
