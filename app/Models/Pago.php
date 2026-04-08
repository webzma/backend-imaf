<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
<<<<<<< HEAD
=======
use Illuminate\Support\Facades\Storage;
>>>>>>> 346ff21d2910b23130442a07973ce6e5b7a2c287

class Pago extends Model
{
    protected $fillable = [
<<<<<<< HEAD
        'estudiante_id',
=======
        'user_id',
>>>>>>> 346ff21d2910b23130442a07973ce6e5b7a2c287
        'curso_id',
        'referencia',
        'banco_origen',
        'comprobante',
        'estado',
        'nota_admin',
    ];

<<<<<<< HEAD
    public function estudiante()
    {
        return $this->belongsTo(Estudiante::class);
=======
    protected $appends = ['comprobante_url'];

    public function getComprobanteUrlAttribute(): string
    {
        return Storage::url('comprobantes/' . $this->comprobante);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function estudiante()
    {
        return $this->hasOne(Estudiante::class, 'user_id', 'user_id');
>>>>>>> 346ff21d2910b23130442a07973ce6e5b7a2c287
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }
}
