<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Un curso que un estudiante cursó o cursa. */
class Inscripcion extends Model
{
    use Auditable, SoftDeletes;

    protected string $auditEntidad = 'inscripcion';

    protected string $auditNombre = 'inscripción';

    protected $table = 'inscripciones';

    protected $fillable = [
        'estudiante_id',
        'curso_id',
        'estado_pago',
        'estado_aprobacion_curso',
        'fecha_inscripcion',
        'es_actual',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inscripcion' => 'date',
            // Solo `true` o `null`: ver el índice único de la migración.
            'es_actual' => 'boolean',
        ];
    }

    /** "Ana Pérez → Dibujo" en la bitácora. */
    public function auditEtiqueta(): ?string
    {
        $estudiante = Estudiante::withTrashed()->find($this->estudiante_id)?->nombre;
        $curso = Curso::withTrashed()->find($this->curso_id)?->nombre;

        return ($estudiante ?? 'Estudiante #'.$this->estudiante_id).' → '.($curso ?? 'curso #'.$this->curso_id);
    }

    /** @phpstan-return BelongsTo<Estudiante, Inscripcion> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }

    /** @phpstan-return BelongsTo<Curso, Inscripcion> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** @phpstan-return HasMany<Pago, Inscripcion> */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }
}
