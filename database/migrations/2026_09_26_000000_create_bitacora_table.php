<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de auditoría: una fila por evento, nunca se edita.
 *
 * El actor se guarda también como texto (nombre y rol del momento) para que
 * el registro siga siendo legible aunque el usuario cambie o se elimine.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->timestamp('created_at', 6)->index();

            // Qué pasó
            $table->string('evento', 100)->index();
            $table->string('categoria', 30)->index();
            $table->string('nivel', 10)->default('info')->index();
            $table->string('descripcion', 500);

            // Quién
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_nombre')->nullable();
            $table->string('actor_rol', 30)->nullable();

            // Sobre qué
            $table->string('entidad', 50)->nullable();
            $table->string('entidad_id', 50)->nullable();
            $table->string('entidad_etiqueta')->nullable();

            // Detalle
            $table->json('cambios')->nullable();
            $table->json('metadatos')->nullable();

            // Desde dónde
            $table->string('origen', 10)->default('http');
            $table->uuid('solicitud_id')->nullable()->index();
            $table->string('ip', 45)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
            $table->string('metodo', 10)->nullable();
            $table->string('ruta', 255)->nullable();

            // Integridad
            $table->char('huella', 64);

            $table->index(['entidad', 'entidad_id']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
