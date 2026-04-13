<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Profesor;

class Curso extends Model
{
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
                $codigo = 'CUR-' . strtoupper(Str::random(6));
            } while (static::where('codigo', $codigo)->exists());

            $curso->codigo = $codigo;
        });
    }

    public function getCuposRestantesAttribute(): int
    {
        $ocupados = $this->estudiantes()->count();

        return max(0, $this->limite_cupo - $ocupados);
    }

    public function profesor()
    {
        return $this->belongsTo(Profesor::class, 'profesor_id');
    }

    public function estudiantes()
    {
        return $this->hasMany(Estudiante::class);
    }
}
