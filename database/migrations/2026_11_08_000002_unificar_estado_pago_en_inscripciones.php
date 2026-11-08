<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un pago se aprueba o se rechaza, igual que en `pagos.estado`. `reprobado` se
 * reserva para `estado_aprobacion_curso`: es quien no aprueba el curso.
 */
return new class() extends Migration
{
    public function up(): void
    {
        $this->cambiar('reprobado', 'rechazado');
    }

    public function down(): void
    {
        $this->cambiar('rechazado', 'reprobado');
    }

    private function cambiar(string $viejo, string $nuevo): void
    {
        $base = ['pendiente', 'aprobado'];

        Schema::table('inscripciones', fn (Blueprint $t) => $t
            ->enum('estado_pago', [...$base, $viejo, $nuevo])->default('aprobado')->change());

        DB::table('inscripciones')->where('estado_pago', $viejo)->update(['estado_pago' => $nuevo]);

        Schema::table('inscripciones', fn (Blueprint $t) => $t
            ->enum('estado_pago', [...$base, $nuevo])->default('aprobado')->change());
    }
};
