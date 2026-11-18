<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Titulo extends Model
{
    use Auditable;

    protected string $auditEntidad = 'titulo';

    protected string $auditNombre = 'título';

    protected $table = 'titulos';

    protected $fillable = [
        'nombre',
    ];

    /** @phpstan-return HasMany<Profesor, Titulo> */
    public function profesores(): HasMany
    {
        return $this->hasMany(Profesor::class, 'titulo_id');
    }
}
