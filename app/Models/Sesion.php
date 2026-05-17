<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sesion extends Model
{
    protected $table = 'sesiones';

    protected $fillable = [
        'curso_id',
        'titulo',
        'descripcion',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
    ];

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }
}
