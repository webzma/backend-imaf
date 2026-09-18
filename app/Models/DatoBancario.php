<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DatoBancario extends Model
{
    protected $table = 'datos_bancarios';

    protected $fillable = [
        'tipo',
        'rif',
        'banco',
        'telefono',
        'concepto',
        'numero_cuenta',
        'nombre_titular',
    ];
}
