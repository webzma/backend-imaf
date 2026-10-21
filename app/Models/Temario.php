<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Temario extends Model
{
    use Auditable;

    protected string $auditEntidad = 'tema';

    protected string $auditNombre = 'tema del temario';

    protected $fillable = ['curso_id', 'titulo', 'descripcion', 'orden'];

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }

    public function sesiones()
    {
        return $this->hasMany(Sesion::class);
    }
}
