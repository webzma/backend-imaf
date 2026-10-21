<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Sesion extends Model
{
    use Auditable;

    protected string $auditEntidad = 'sesion';

    protected string $auditNombre = 'sesión';

    protected $table = 'sesiones';

    protected $fillable = [
        'curso_id',
        'temario_id',
        'titulo',
        'descripcion',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date:Y-m-d',
    ];

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }

    /** Tema del temario que se imparte en esta sesión. */
    public function temario()
    {
        return $this->belongsTo(Temario::class);
    }
}
