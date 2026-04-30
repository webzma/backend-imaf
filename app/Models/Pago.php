<?php

namespace App\Models;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'curso_id',
        'referencia',
        'banco_origen',
        'comprobante',
        'estado',
        'nota_admin',
    ];

    protected $appends = ['comprobante_url'];

    public function getComprobanteUrlAttribute(): ?string
    {
        if (empty($this->comprobante)) {
            return null;
        }

        try {
            return Cloudinary::image($this->comprobante)->toUrl();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function estudiante()
    {
        return $this->hasOne(Estudiante::class, 'user_id', 'user_id');
    }

    public function curso()
    {
        return $this->belongsTo(Curso::class);
    }
}
