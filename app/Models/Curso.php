<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Curso extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Toda la oferta de IMAF se dicta de forma presencial en la sede. No es
     * un dato por curso sino de la institución, así que es una constante y
     * no una columna; se expone en la API para que ninguna pantalla tenga
     * que suponerlo.
     */
    public const MODALIDAD = 'presencial';

    public const SEDE = '5ta av. entre calles 29 y 30, antigua sede de la Unidad de Diálisis';

    protected $fillable = [
        'profesor_id',
        'nombre',
        'limite_cupo',
        'minimo_estudiantes',
        'fecha_inicio',
        'fecha_fin',
        'descripcion',
        'requisitos',
        'precio',
        'whatsapp_url',
        'estado',
    ];

    protected $appends = ['cupos_restantes', 'modalidad', 'sede'];

    protected $casts = [
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin' => 'date:Y-m-d',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Curso $curso) {
            do {
                $codigo = 'CUR-'.strtoupper(Str::random(6));
            } while (static::where('codigo', $codigo)->exists());

            $curso->codigo = $codigo;
        });
    }

    public function getCuposRestantesAttribute(): int
    {
        $ocupados = $this->estudiantes()->count();

        return max(0, $this->limite_cupo - $ocupados);
    }

    public function getModalidadAttribute(): string
    {
        return self::MODALIDAD;
    }

    public function getSedeAttribute(): string
    {
        return self::SEDE;
    }

    public function instructor()
    {
        return $this->belongsTo(Profesor::class, 'profesor_id');
    }

    /**
     * Inscritos con el pago aprobado, incluidos los que ya pasaron a otro
     * curso. Es lo que cuenta para cupo, mínimo, asistencia y reportes.
     */
    public function estudiantes()
    {
        return $this->matriculas()->wherePivot('estado_pago', 'aprobado');
    }

    /**
     * Todas las inscripciones, pagadas o no. Es el listado interno del curso
     * (admin e instructor); ahí figuran también quienes no han pagado.
     */
    public function matriculas()
    {
        return $this->belongsToMany(Estudiante::class, 'inscripciones')
            ->withPivot('estado_pago', 'estado_aprobacion_curso', 'fecha_inscripcion')
            ->withTimestamps();
    }

    public function temario()
    {
        return $this->hasMany(Temario::class)->orderBy('orden');
    }

    public function sesiones()
    {
        return $this->hasMany(Sesion::class)->orderBy('fecha')->orderBy('hora_inicio');
    }
}
