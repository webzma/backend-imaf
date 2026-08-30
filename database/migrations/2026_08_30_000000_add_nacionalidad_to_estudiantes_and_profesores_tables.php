<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->string('nacionalidad', 1)
                  ->nullable()
                  ->default('V')
                  ->after('cedula');
        });

        Schema::table('profesores', function (Blueprint $table) {
            $table->string('nacionalidad', 1)
                  ->nullable()
                  ->default('V')
                  ->after('cedula');
        });
    }

    public function down(): void
    {
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->dropColumn('nacionalidad');
        });

        Schema::table('profesores', function (Blueprint $table) {
            $table->dropColumn('nacionalidad');
        });
    }
};
