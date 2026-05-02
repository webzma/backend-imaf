<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Curso extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'profesor_id',
        'nombre',
        'limite_cupo',
        'fecha_inicio',
        'fecha_fin',
        'descripcion',
        'requisitos',
        'precio',
        'whatsapp_url',
        'estado',
    ];

    protected $appends = ['cupos_restantes'];

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

    public function instructor()
    {
        return $this->belongsTo(Profesor::class, 'profesor_id');
    }

    public function estudiantes()
    {
        return $this->hasMany(Estudiante::class);
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
