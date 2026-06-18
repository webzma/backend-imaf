<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profesor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'profesores';

    protected $fillable = [
        'user_id',
        'cedula',
        'telefono',
        'municipio',
        'especialidad',
        'titulo',
        'departamento',
        'fecha_nacimiento',
        'genero',
        'foto',
        'tipo_contrato_id',
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

    public function tipoContrato()
    {
        return $this->belongsTo(TipoContrato::class, 'tipo_contrato_id');
    }

    public function cursos()
    {
        return $this->hasMany(Curso::class, 'profesor_id');
    }
}
