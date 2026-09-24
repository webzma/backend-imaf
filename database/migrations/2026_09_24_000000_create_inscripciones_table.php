<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Historial de cursos por estudiante.
 *
 * `estudiantes.curso_id` solo guarda el curso actual: al aprobar un pago nuevo
 * se sobrescribía y el curso anterior desaparecía (también del cupo y del
 * certificado). Cada fila de `inscripciones` es un curso que el estudiante
 * cursó o cursa; `curso_id` queda como "curso actual".
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade');
            $table->foreignId('curso_id')->constrained('cursos')->onDelete('cascade');
            $table->enum('estado_aprobacion_curso', ['pendiente', 'aprobado', 'reprobado'])->default('pendiente');
            $table->date('fecha_inscripcion')->nullable();
            $table->timestamps();

            $table->unique(['estudiante_id', 'curso_id']);
        });

        $ahora = now();

        // Fecha en que se aprobó el pago de cada curso: es la fecha real de
        // inscripción (`estudiantes.fecha_inscripcion` es la del registro).
        $pagosAprobados = DB::table('pagos')
            ->join('estudiantes', 'estudiantes.user_id', '=', 'pagos.user_id')
            ->join('cursos', 'cursos.id', '=', 'pagos.curso_id')
            ->where('pagos.estado', 'aprobado')
            ->whereNull('pagos.deleted_at')
            ->orderBy('pagos.updated_at')
            ->get(['estudiantes.id as estudiante_id', 'estudiantes.user_id', 'pagos.curso_id', 'pagos.updated_at'])
            ->keyBy(fn ($p) => $p->estudiante_id.'-'.$p->curso_id);

        // Aprobaciones de curso ya comunicadas al estudiante: la última
        // notificación por curso dice cómo quedó.
        $aprobaciones = DB::table('notifications')
            ->where('type', 'App\\Notifications\\SolicitudCursoProcesada')
            ->orderBy('created_at')
            ->get(['notifiable_id', 'data'])
            ->mapWithKeys(function ($n) {
                $data = json_decode($n->data, true) ?: [];
                if (($data['tipo'] ?? null) !== 'aprobacion_curso' || empty($data['curso_id'])) {
                    return [];
                }

                return [$n->notifiable_id.'-'.$data['curso_id'] => ($data['titulo'] ?? '') === 'Solicitud aprobada' ? 'aprobado' : 'reprobado'];
            });

        $fila = fn (int $estudianteId, int $cursoId, ?string $estado, $fecha) => [
            'estudiante_id' => $estudianteId,
            'curso_id' => $cursoId,
            'estado_aprobacion_curso' => $estado ?? 'pendiente',
            'fecha_inscripcion' => $fecha ? substr((string) $fecha, 0, 10) : null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];

        // Curso actual de cada estudiante, con su estado de aprobación.
        $filas = DB::table('estudiantes')
            ->whereNotNull('curso_id')
            ->get(['id', 'curso_id', 'estado_aprobacion_curso', 'fecha_inscripcion'])
            ->map(fn ($e) => $fila(
                $e->id,
                $e->curso_id,
                $e->estado_aprobacion_curso,
                $pagosAprobados->get($e->id.'-'.$e->curso_id)?->updated_at ?? $e->fecha_inscripcion,
            ));

        // Cursos anteriores: los que tienen un pago aprobado.
        $previos = $pagosAprobados->map(fn ($p) => $fila(
            $p->estudiante_id,
            $p->curso_id,
            $aprobaciones->get($p->user_id.'-'.$p->curso_id),
            $p->updated_at,
        ));

        $filas->concat($previos->values())
            ->unique(fn ($f) => $f['estudiante_id'].'-'.$f['curso_id'])
            ->chunk(200)
            ->each(fn ($chunk) => DB::table('inscripciones')->insert($chunk->values()->all()));
    }

    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};
