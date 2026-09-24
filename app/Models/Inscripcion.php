<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Un curso que un estudiante cursó o cursa. */
class Inscripcion extends Model
{
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

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class);
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }
}
