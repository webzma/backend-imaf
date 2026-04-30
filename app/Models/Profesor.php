<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profesor extends Model
{
    use SoftDeletes;

    protected $table = 'profesores';

    protected $fillable = [
        'user_id',
        'cedula',
        'telefono',
        'municipio',
        'especialidad',
        'titulo',
        'departamento',
        'municipio',
        'fecha_nacimiento',
        'genero',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cursos()
    {
        return $this->hasMany(Curso::class, 'profesor_id');
    }
}
