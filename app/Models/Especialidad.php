<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Especialidad extends Model
{
    use Auditable;

    protected string $auditEntidad = 'especialidad';

    protected string $auditNombre = 'especialidad';

    protected $table = 'especialidades';

    protected $fillable = [
        'nombre',
    ];

    /** @phpstan-return HasMany<Profesor, Especialidad> */
    public function profesores(): HasMany
    {
        return $this->hasMany(Profesor::class, 'especialidad_id');
    }
}
