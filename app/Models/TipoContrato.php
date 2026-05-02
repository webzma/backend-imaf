<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoContrato extends Model
{
    protected $table = 'tipo_contratos';

    protected $fillable = [
        'nombre',
    ];

    public function profesores()
    {
        return $this->hasMany(Profesor::class, 'tipo_contrato_id');
    }
}
