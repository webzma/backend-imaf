<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Temario extends Model
{
    use Auditable;

    protected string $auditEntidad = 'tema';

    protected string $auditNombre = 'tema del temario';

    protected $fillable = ['curso_id', 'titulo', 'descripcion', 'orden'];

    /** @phpstan-return BelongsTo<Curso, Temario> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class);
    }

    /** @phpstan-return HasMany<Sesion, Temario> */
    public function sesiones(): HasMany
    {
        return $this->hasMany(Sesion::class);
    }
}
