<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Estudiante y curso quedan relacionados solo por `inscripciones`.
 *
 * `es_actual` vale 1 o NULL, nunca 0: el índice único admite muchos NULL, así
 * que la propia base garantiza un curso actual como máximo por estudiante.
 *
 * `deleted_at` conserva la inscripción de quien fue quitado de un curso cuando
 * tiene pagos, para que esos pagos sigan vinculados a ella.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('inscripciones', function (Blueprint $t) {
            $t->boolean('es_actual')->nullable()->after('fecha_inscripcion');
            $t->softDeletes();
            $t->unique(['estudiante_id', 'es_actual'], 'inscripciones_curso_actual_unique');
        });

        // Igual que `Estudiante::cursoActual()`, solo cuenta si está pagado.
        DB::table('inscripciones')
            ->where('estado_pago', 'aprobado')
            ->whereExists(fn ($q) => $q->from('estudiantes')
                ->whereColumn('estudiantes.id', 'inscripciones.estudiante_id')
                ->whereColumn('estudiantes.curso_id', 'inscripciones.curso_id'))
            ->update(['es_actual' => true]);

        Schema::table('estudiantes', fn (Blueprint $t) => $t->dropForeign(['curso_id']));
        Schema::table('estudiantes', fn (Blueprint $t) => $t->dropColumn([
            'curso_id',
            'estado_pago',
            'estado_aprobacion_curso',
        ]));
    }

    public function down(): void
    {
        Schema::table('estudiantes', function (Blueprint $t) {
            $t->unsignedBigInteger('curso_id')->nullable()->after('user_id');
            $t->enum('estado_pago', ['pendiente', 'aprobado', 'reprobado'])->default('pendiente')->after('estado');
            $t->enum('estado_aprobacion_curso', ['pendiente', 'aprobado', 'reprobado'])->default('pendiente')->after('estado_pago');
            $t->foreign('curso_id')->references('id')->on('cursos')->onDelete('set null');
        });

        DB::table('inscripciones')->where('es_actual', true)->orderBy('id')->get()->each(
            fn ($i) => DB::table('estudiantes')->where('id', $i->estudiante_id)->update([
                'curso_id' => $i->curso_id,
                'estado_pago' => 'aprobado',
                'estado_aprobacion_curso' => $i->estado_aprobacion_curso,
            ]),
        );

        // Las inscripciones retiradas no existían en el esquema anterior.
        DB::table('inscripciones')->whereNotNull('deleted_at')->delete();

        Schema::table('inscripciones', function (Blueprint $t) {
            $t->dropUnique('inscripciones_curso_actual_unique');
            $t->dropSoftDeletes();
            $t->dropColumn('es_actual');
        });
    }
};
