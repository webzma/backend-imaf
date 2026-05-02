<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_id')->constrained('sesiones')->onDelete('cascade');
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade');
            $table->boolean('presente')->default(false);
            $table->string('observacion')->nullable();
            $table->timestamps();

            $table->unique(['sesion_id', 'estudiante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
