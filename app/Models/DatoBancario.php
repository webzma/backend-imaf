<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class DatoBancario extends Model
{
    use Auditable;

    protected string $auditEntidad = 'dato_bancario';

    protected string $auditNombre = 'datos bancarios';

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
