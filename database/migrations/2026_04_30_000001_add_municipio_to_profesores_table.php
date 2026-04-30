<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('profesores', 'municipio')) {
            return;
        }

        Schema::table('profesores', function (Blueprint $table) {
            $table->string('municipio')->nullable()->after('departamento');
        });
    }

    public function down(): void
    {
        Schema::table('profesores', function (Blueprint $table) {
            $table->dropColumn('municipio');
        });
    }
};
