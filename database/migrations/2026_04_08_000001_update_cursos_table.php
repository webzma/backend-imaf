<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            // Remove fields no longer needed
            $table->dropColumn(['creditos']);

            // New fields
            $table->unsignedInteger('limite_cupo')->default(30)->after('nombre');
            $table->date('fecha_inicio')->nullable()->after('limite_cupo');
            $table->date('fecha_fin')->nullable()->after('fecha_inicio');
            $table->text('requisitos')->nullable()->after('descripcion');
            $table->decimal('precio', 10, 2)->default(0)->after('requisitos');
            $table->string('whatsapp_url')->nullable()->after('precio');
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn(['limite_cupo', 'fecha_inicio', 'fecha_fin', 'requisitos', 'precio', 'whatsapp_url']);
            $table->unsignedTinyInteger('creditos')->default(3);
        });
    }
};
