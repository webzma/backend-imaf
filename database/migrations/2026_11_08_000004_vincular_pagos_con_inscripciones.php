<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un pago pertenece a una inscripción (1:N: se puede reintentar tras un
 * rechazo). El estudiante y el curso se obtienen a través de ella.
 *
 * `restrict`: un registro de pago no debe desaparecer como efecto secundario.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('pagos', fn (Blueprint $t) => $t
            ->foreignId('inscripcion_id')->nullable()->after('id')
            ->constrained('inscripciones')->restrictOnDelete());

        DB::table('pagos')->orderBy('id')->chunkById(200, function ($pagos) {
            foreach ($pagos as $pago) {
                DB::table('pagos')->where('id', $pago->id)->update([
                    'inscripcion_id' => $this->inscripcionDe($pago),
                ]);
            }
        });

        Schema::table('pagos', fn (Blueprint $t) => $t
            ->unsignedBigInteger('inscripcion_id')->nullable(false)->change());

        Schema::table('pagos', function (Blueprint $t) {
            $t->dropForeign(['user_id']);
            $t->dropForeign(['curso_id']);
        });
        Schema::table('pagos', fn (Blueprint $t) => $t->dropColumn(['user_id', 'curso_id']));
    }

    /**
     * Un pago sin inscripción es de alguien a quien quitaron del curso: se
     * crea como retirada para no perder el vínculo.
     */
    private function inscripcionDe(object $pago): int
    {
        $estudianteId = DB::table('estudiantes')->where('user_id', $pago->user_id)->value('id');

        $existente = DB::table('inscripciones')
            ->where('estudiante_id', $estudianteId)
            ->where('curso_id', $pago->curso_id)
            ->value('id');

        if ($existente) {
            return $existente;
        }

        $ahora = now();

        return DB::table('inscripciones')->insertGetId([
            'estudiante_id' => $estudianteId,
            'curso_id' => $pago->curso_id,
            'estado_pago' => in_array($pago->estado, ['pendiente', 'aprobado'], true) ? $pago->estado : 'rechazado',
            'estado_aprobacion_curso' => 'pendiente',
            'fecha_inscripcion' => substr((string) $pago->created_at, 0, 10) ?: null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
            'deleted_at' => $ahora,
        ]);
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->nullable()->after('id');
            $t->unsignedBigInteger('curso_id')->nullable()->after('user_id');
        });

        DB::table('pagos')->orderBy('id')->chunkById(200, function ($pagos) {
            foreach ($pagos as $pago) {
                $fila = DB::table('inscripciones')
                    ->join('estudiantes', 'estudiantes.id', '=', 'inscripciones.estudiante_id')
                    ->where('inscripciones.id', $pago->inscripcion_id)
                    ->first(['estudiantes.user_id', 'inscripciones.curso_id']);

                DB::table('pagos')->where('id', $pago->id)->update([
                    'user_id' => $fila->user_id,
                    'curso_id' => $fila->curso_id,
                ]);
            }
        });

        Schema::table('pagos', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->nullable(false)->change();
            $t->unsignedBigInteger('curso_id')->nullable(false)->change();
        });
        Schema::table('pagos', function (Blueprint $t) {
            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $t->foreign('curso_id')->references('id')->on('cursos')->onDelete('cascade');
            $t->dropForeign(['inscripcion_id']);
        });
        Schema::table('pagos', fn (Blueprint $t) => $t->dropColumn('inscripcion_id'));
    }
};
