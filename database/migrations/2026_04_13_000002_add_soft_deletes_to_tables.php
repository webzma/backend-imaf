<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('estudiantes', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('profesores', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('cursos', fn (Blueprint $t) => $t->softDeletes());
        Schema::table('pagos', fn (Blueprint $t) => $t->softDeletes());
    }

    public function down(): void
    {
        Schema::table('estudiantes', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('profesores', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('cursos', fn (Blueprint $t) => $t->dropSoftDeletes());
        Schema::table('pagos', fn (Blueprint $t) => $t->dropSoftDeletes());
    }
};
