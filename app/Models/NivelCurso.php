<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class NivelCurso extends Model
{
    use Auditable;

    protected string $auditEntidad = 'nivel_curso';

    protected string $auditNombre = 'nivel de curso';

    protected $table = 'niveles_cursos';

    protected $fillable = [
        'nombre',
    ];

    public function cursos()
    {
        return $this->hasMany(Curso::class, 'nivel_curso_id');
    }
}
