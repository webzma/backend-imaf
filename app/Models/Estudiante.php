<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Estudiante extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'curso_id',
        'nombre',
        'nacionalidad',
        'cedula',
        'telefono',
        'municipio',
        'direccion',
        'fecha_nacimiento',
        'genero',
        'foto',
        'fecha_inscripcion',
        'estado',
        'estado_pago',
        'estado_aprobacion_curso',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_inscripcion' => 'date',
        ];
    }

    /**
     * `curso_id` es el curso actual; `inscripciones` guarda todos. Cada vez
     * que cambia el curso actual se registra la inscripción, y el estado de
     * aprobación pasa a ser el de ese curso (antes arrastraba el del curso
     * anterior, y con él un certificado que no correspondía).
     */
    protected static function booted(): void
    {
        static::saving(function (Estudiante $estudiante) {
            if (
                $estudiante->exists &&
                $estudiante->isDirty('curso_id') &&
                ! $estudiante->isDirty('estado_aprobacion_curso')
            ) {
                $estudiante->estado_aprobacion_curso = $estudiante->curso_id
                    ? Inscripcion::where('estudiante_id', $estudiante->id)
                        ->where('curso_id', $estudiante->curso_id)
                        ->value('estado_aprobacion_curso') ?? 'pendiente'
                    : 'pendiente';
            }
        });

        static::saved(function (Estudiante $estudiante) {
            if (! $estudiante->curso_id) {
                return;
            }

            if (
                ! $estudiante->wasRecentlyCreated &&
                ! $estudiante->wasChanged(['curso_id', 'estado_aprobacion_curso'])
            ) {
                return;
            }

            $inscripcion = Inscripcion::firstOrNew([
                'estudiante_id' => $estudiante->id,
                'curso_id' => $estudiante->curso_id,
            ]);
            $inscripcion->fecha_inscripcion ??= now()->toDateString();
            // El curso actual es, por definición, uno en el que está inscrito.
            $inscripcion->estado_pago = 'aprobado';
            $inscripcion->estado_aprobacion_curso = $estudiante->estado_aprobacion_curso ?? 'pendiente';
            $inscripcion->save();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Curso actual. */
    public function curso()
    {
        return $this->belongsTo(Curso::class);
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
            ->withPivot('estado_pago', 'estado_aprobacion_curso', 'fecha_inscripcion')
            ->withTimestamps();
    }
}
