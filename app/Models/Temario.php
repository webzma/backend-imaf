<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Temario extends Model
{
    protected $fillable = ['curso_id', 'titulo', 'descripcion', 'orden'];

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }
}
