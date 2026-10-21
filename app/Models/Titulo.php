<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Titulo extends Model
{
    use Auditable;

    protected string $auditEntidad = 'titulo';

    protected string $auditNombre = 'título';

    protected $table = 'titulos';

    protected $fillable = [
        'nombre',
    ];

    public function profesores()
    {
        return $this->hasMany(Profesor::class, 'titulo_id');
    }
}
