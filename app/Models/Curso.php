<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curso extends Model
{
    protected $fillable = [
        'profesor_id',
        'nombre',
        'codigo',
        'descripcion',
        'creditos',
        'estado',
    ];

    public function profesor()
    {
        return $this->belongsTo(User::class, 'profesor_id');
    }

    public function estudiantes()
    {
        return $this->hasMany(Estudiante::class);
    }
}
