<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Departamento extends Model
{
    use Auditable;

    protected string $auditEntidad = 'departamento';

    protected string $auditNombre = 'departamento';

    protected $table = 'departamentos';

    protected $fillable = [
        'nombre',
    ];

    /** @phpstan-return HasMany<Profesor, Departamento> */
    public function profesores(): HasMany
    {
        return $this->hasMany(Profesor::class, 'departamento_id');
    }
}
