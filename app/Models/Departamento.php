<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    use Auditable;

    protected string $auditEntidad = 'departamento';

    protected string $auditNombre = 'departamento';

    protected $table = 'departamentos';

    protected $fillable = [
        'nombre',
    ];

    public function profesores()
    {
        return $this->hasMany(Profesor::class, 'departamento_id');
    }
}
