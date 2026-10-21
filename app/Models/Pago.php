<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    use Auditable;

    protected string $auditEntidad = 'pago';

    protected string $auditNombre = 'pago';

    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'curso_id',
        'metodo_pago',
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

    /** "Ana Pérez → Dibujo" en la bitácora. */
    public function auditEtiqueta(): ?string
    {
        $usuario = User::find($this->user_id)?->name;
        $curso = Curso::withTrashed()->find($this->curso_id)?->nombre;

        return trim(($usuario ?? 'Usuario #'.$this->user_id).' → '.($curso ?? 'curso #'.$this->curso_id));
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
