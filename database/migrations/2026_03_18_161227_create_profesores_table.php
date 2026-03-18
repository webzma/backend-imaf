<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profesores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('cedula')->unique();
            $table->string('telefono')->nullable();
            $table->string('especialidad')->nullable();
            $table->enum('titulo', ['licenciatura', 'maestria', 'doctorado'])->nullable();
            $table->string('departamento')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->enum('genero', ['masculino', 'femenino', 'otro'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profesores');
    }
};
