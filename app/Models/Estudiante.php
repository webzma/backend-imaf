<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\Auditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Estudiante extends Model
{
    use Auditable;

    protected string $auditEntidad = 'estudiante';

    protected string $auditNombre = 'estudiante';

    /**
     * La URL de la cédula es un dato sensible: se excluye de la bitácora y se
     * oculta por defecto. Los endpoints autorizados la exponen con
     * `makeVisible('foto_cedula')`.
     */
    protected array $auditExcluir = ['foto_cedula'];

    use HasFactory, SoftDeletes;

    protected $hidden = ['foto_cedula', 'inscripcionActual', 'ultimaInscripcion'];

    /**
     * El curso actual y los estados de pago y aprobación ya no son columnas:
     * se obtienen de `inscripciones`, y la API los sigue exponiendo igual.
     * Se cargan siempre para que un listado no haga una consulta por fila.
     */
    protected $with = ['inscripcionActual', 'ultimaInscripcion'];

    protected $appends = ['curso_id', 'estado_pago', 'estado_aprobacion_curso'];

    protected $fillable = [
        'user_id',
        'nombre',
        'nacionalidad',
        'cedula',
        'telefono',
        'municipio',
        'direccion',
        'fecha_nacimiento',
        'genero',
        'foto',
        'foto_cedula',
        'fecha_inscripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_inscripcion' => 'date',
        ];
    }

    public function getCursoIdAttribute(): ?int
    {
        return $this->inscripcionActual?->curso_id;
    }

    /**
     * El pago del curso actual o, sin curso actual, el de su última solicitud.
     * El listado de un curso fija el valor de ese curso con `forceFill` (ver
     * CursoController); se lee de `attributes` porque Laravel llama a los
     * campos calculados de `$appends` sin pasarles el valor.
     */
    public function getEstadoPagoAttribute(): string
    {
        return $this->attributes['estado_pago']
            ?? ($this->inscripcionActual ? 'aprobado' : $this->ultimaInscripcion?->estado_pago)
            ?? 'pendiente';
    }

    public function getEstadoAprobacionCursoAttribute(): string
    {
        return $this->attributes['estado_aprobacion_curso']
            ?? $this->inscripcionActual?->estado_aprobacion_curso
            ?? 'pendiente';
    }

    /** Filtra por el mismo valor que devuelve `estado_pago`. */
    public function scopeConEstadoPago(Builder $query, string $estado): void
    {
        $query->whereRaw(
            "COALESCE((
                SELECT i.estado_pago FROM inscripciones i
                WHERE i.estudiante_id = estudiantes.id AND i.deleted_at IS NULL
                ORDER BY i.es_actual DESC, i.updated_at DESC, i.id DESC
                LIMIT 1
            ), 'pendiente') = ?",
            [$estado],
        );
    }

    /**
     * Inscripción de este estudiante en un curso, nueva si no existía. Si lo
     * habían quitado de ese curso empieza de cero, sin el pago de entonces.
     * No la guarda: cada llamador fija antes el estado que corresponde.
     */
    public function inscripcionEn(int $cursoId): Inscripcion
    {
        $inscripcion = Inscripcion::withTrashed()->firstOrNew([
            'estudiante_id' => $this->id,
            'curso_id' => $cursoId,
        ]);

        if ($inscripcion->trashed()) {
            $inscripcion->deleted_at = null;
            $inscripcion->estado_pago = 'pendiente';
            $inscripcion->estado_aprobacion_curso = 'pendiente';
            $inscripcion->fecha_inscripcion = null;
        }

        $inscripcion->fecha_inscripcion ??= now()->toDateString();

        return $inscripcion;
    }

    /**
     * Regla: el curso actual es siempre uno con el pago aprobado. Por eso, al
     * asignar un curso, la inscripción queda aprobada; para sacarlo de un
     * curso sin pagar está `retirarDeCurso()`.
     */
    public function asignarCursoActual(int $cursoId): Inscripcion
    {
        return DB::transaction(function () use ($cursoId) {
            $inscripcion = $this->inscripcionEn($cursoId);

            if ($inscripcion->es_actual !== true) {
                $this->dejarSinCursoActual();
            }

            $inscripcion->estado_pago = 'aprobado';
            $inscripcion->es_actual = true;
            $inscripcion->save();

            $this->olvidarInscripciones();

            return $inscripcion;
        });
    }

    /** Conserva sus inscripciones; solo deja de tener un curso en marcha. */
    public function dejarSinCursoActual(): void
    {
        $this->inscripcionActual()->first()?->update(['es_actual' => null]);
        $this->olvidarInscripciones();
    }

    /**
     * Saca al estudiante de un curso cuyo pago no está aprobado. La
     * inscripción se conserva con ese estado (el listado interno del curso la
     * sigue mostrando), pero deja de dar acceso. Si era su curso actual,
     * vuelve al último curso pagado que le quede o se queda sin curso.
     *
     * @param  'pendiente'|'rechazado'  $estadoPago
     */
    public function retirarDeCurso(int $cursoId, string $estadoPago): void
    {
        $inscripcion = $this->inscripcionEn($cursoId);
        $eraActual = $inscripcion->es_actual === true;

        $inscripcion->estado_pago = $estadoPago;
        $inscripcion->es_actual = null;
        $inscripcion->save();

        Auditoria::registrar('inscripcion.retirada', "Retiro de {$this->nombre} del curso por pago {$estadoPago}", [
            'entidad' => $inscripcion,
            'metadatos' => ['curso_id' => $cursoId, 'estado_pago' => $estadoPago, 'era_curso_actual' => $eraActual],
        ]);

        if ($eraActual) {
            $this->volverAlUltimoCursoPagado();
        }

        $this->olvidarInscripciones();
    }

    /**
     * Quita al estudiante del curso. Si la inscripción tiene pagos se conserva
     * como retirada, porque esos pagos dependen de ella; si no, se borra.
     */
    public function quitarDeCurso(int $cursoId): void
    {
        $inscripcion = $this->inscripciones()->where('curso_id', $cursoId)->first();

        if (! $inscripcion) {
            return;
        }

        $eraActual = $inscripcion->es_actual === true;

        $inscripcion->update(['es_actual' => null]);
        $inscripcion->pagos()->withTrashed()->exists()
            ? $inscripcion->delete()
            : $inscripcion->forceDelete();

        if ($eraActual) {
            $this->volverAlUltimoCursoPagado();
        }

        $this->olvidarInscripciones();
    }

    private function volverAlUltimoCursoPagado(): void
    {
        $this->inscripciones()
            ->where('estado_pago', 'aprobado')
            ->orderByDesc('fecha_inscripcion')
            ->orderByDesc('id')
            ->first()
            ?->update(['es_actual' => true]);
    }

    /** Las relaciones ya cargadas dejan de ser válidas al cambiar la inscripción. */
    private function olvidarInscripciones(): void
    {
        $this->unsetRelation('inscripcionActual');
        $this->unsetRelation('ultimaInscripcion');
        $this->unsetRelation('curso');
    }

    /**
     * El curso que el estudiante está cursando ahora: su último curso pagado,
     * mientras no haya terminado. Uno finalizado sigue en su historial (y con
     * su certificado), pero ya no es "Mi curso".
     */
    public function cursoActual(): ?Curso
    {
        $curso = $this->curso()->first();

        return $curso?->enCurso() ? $curso : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Curso actual, a través de su inscripción marcada como tal. */
    public function curso()
    {
        return $this->hasOneThrough(Curso::class, Inscripcion::class, 'estudiante_id', 'id', 'id', 'curso_id')
            ->where('inscripciones.es_actual', true)
            ->where('inscripciones.estado_pago', 'aprobado');
    }

    public function inscripcionActual()
    {
        return $this->hasOne(Inscripcion::class)
            ->where('es_actual', true)
            ->where('estado_pago', 'aprobado');
    }

    /** La inscripción movida más recientemente: su última solicitud. */
    public function ultimaInscripcion()
    {
        return $this->hasOne(Inscripcion::class)->latestOfMany(['updated_at', 'id']);
    }

    public function inscripciones()
    {
        return $this->hasMany(Inscripcion::class);
    }

    /** Todos los cursos en los que se ha inscrito con el pago aprobado. */
    public function cursos()
    {
        return $this->belongsToMany(Curso::class, 'inscripciones')
            ->wherePivot('estado_pago', 'aprobado')
            ->wherePivotNull('deleted_at')
            ->withPivot('estado_pago', 'estado_aprobacion_curso', 'fecha_inscripcion')
            ->withTimestamps();
    }
}
