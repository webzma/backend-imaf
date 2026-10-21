<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

/** Un curso que un estudiante cursó o cursa. */
class Inscripcion extends Model
{
    use Auditable;

    protected string $auditEntidad = 'inscripcion';

    protected string $auditNombre = 'inscripción';

    protected $table = 'inscripciones';

    protected $fillable = [
        'estudiante_id',
        'curso_id',
        'estado_pago',
        'estado_aprobacion_curso',
        'fecha_inscripcion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inscripcion' => 'date',
        ];
    }

    /** "Ana Pérez → Dibujo" en la bitácora. */
    public function auditEtiqueta(): ?string
    {
        $estudiante = Estudiante::withTrashed()->find($this->estudiante_id)?->nombre;
        $curso = Curso::withTrashed()->find($this->curso_id)?->nombre;

        return ($estudiante ?? 'Estudiante #'.$this->estudiante_id).' → '.($curso ?? 'curso #'.$this->curso_id);
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }
}
