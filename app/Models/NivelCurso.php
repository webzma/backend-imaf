<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NivelCurso extends Model
{
    use Auditable;

    protected string $auditEntidad = 'nivel_curso';

    protected string $auditNombre = 'nivel de curso';

    protected $table = 'niveles_curso';

    protected $fillable = [
        'nombre',
    ];

    /** @phpstan-return HasMany<Curso, NivelCurso> */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class, 'nivel_curso_id');
    }
}
