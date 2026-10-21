<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Especialidad extends Model
{
    use Auditable;

    protected string $auditEntidad = 'especialidad';

    protected string $auditNombre = 'especialidad';

    protected $table = 'especialidades';

    protected $fillable = [
        'nombre',
    ];

    public function profesores()
    {
        return $this->hasMany(Profesor::class, 'especialidad_id');
    }
}
