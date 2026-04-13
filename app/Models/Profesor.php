<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Profesor extends Model
{
    protected $table = 'profesores';

    protected $fillable = [
        'user_id',
        'cedula',
        'telefono',
        'especialidad',
        'titulo',
        'departamento',
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
