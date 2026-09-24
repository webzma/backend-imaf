<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El listado interno de un curso debe mostrar también a quien no ha pagado
 * (solicitud pendiente o pago rechazado). En vez de borrar o no crear la
 * inscripción, se guarda su estado de pago; cupo, asistencia y la vista del
 * estudiante solo cuentan las `aprobado`.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('inscripciones', function (Blueprint $table) {
            $table->enum('estado_pago', ['pendiente', 'aprobado', 'reprobado'])
                ->default('aprobado')
                ->after('curso_id');
        });

        $ahora = now();

        // Último pago de cada estudiante por curso sin inscripción: pendiente
        // o rechazado (los aprobados ya tienen su inscripción).
        $pagos = DB::table('pagos')
            ->join('estudiantes', 'estudiantes.user_id', '=', 'pagos.user_id')
            ->join('cursos', 'cursos.id', '=', 'pagos.curso_id')
            ->whereNull('pagos.deleted_at')
            ->whereIn('pagos.estado', ['pendiente', 'rechazado'])
            ->whereNotExists(fn ($q) => $q->from('inscripciones')
                ->whereColumn('inscripciones.estudiante_id', 'estudiantes.id')
                ->whereColumn('inscripciones.curso_id', 'pagos.curso_id'))
            ->orderBy('pagos.created_at')
            ->get(['estudiantes.id as estudiante_id', 'pagos.curso_id', 'pagos.estado', 'pagos.created_at'])
            ->keyBy(fn ($p) => $p->estudiante_id.'-'.$p->curso_id);

        $pagos->values()
            ->map(fn ($p) => [
                'estudiante_id' => $p->estudiante_id,
                'curso_id' => $p->curso_id,
                'estado_pago' => $p->estado === 'pendiente' ? 'pendiente' : 'reprobado',
                'estado_aprobacion_curso' => 'pendiente',
                'fecha_inscripcion' => $p->created_at ? substr((string) $p->created_at, 0, 10) : null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])
            ->chunk(200)
            ->each(fn ($chunk) => DB::table('inscripciones')->insert($chunk->values()->all()));
    }

    public function down(): void
    {
        DB::table('inscripciones')->where('estado_pago', '!=', 'aprobado')->delete();

        Schema::table('inscripciones', function (Blueprint $table) {
            $table->dropColumn('estado_pago');
        });
    }
};
