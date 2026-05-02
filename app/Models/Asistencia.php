<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    protected $fillable = [
        'sesion_id',
        'estudiante_id',
        'presente',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'presente' => 'boolean',
        ];
    }

    public function sesion()
    {
        return $this->belongsTo(Sesion::class);
    }

    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class);
    }
}
