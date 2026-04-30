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
            $table->string('municipio')->nullable()->after('telefono');
        });

        Schema::table('profesores', function (Blueprint $table) {
            $table->string('municipio')->nullable()->after('telefono');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->dropColumn('municipio');
        });

        Schema::table('profesores', function (Blueprint $table) {
            $table->dropColumn('municipio');
        });
    }
};
