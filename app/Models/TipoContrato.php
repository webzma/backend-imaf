<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class TipoContrato extends Model
{
    use Auditable;

    protected string $auditEntidad = 'tipo_contrato';

    protected string $auditNombre = 'tipo de contrato';

    protected $table = 'tipo_contratos';

    protected $fillable = [
        'nombre',
    ];

    public function profesores()
    {
        return $this->hasMany(Profesor::class, 'tipo_contrato_id');
    }
}
