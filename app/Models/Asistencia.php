<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    protected $fillable = [
        'sesion_id',
        'estudiante_id',
        'presente',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'presente' => 'boolean',
        ];
    }

    /** @phpstan-return BelongsTo<Sesion, Asistencia> */
    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Sesion::class);
    }

    /** @phpstan-return BelongsTo<Estudiante, Asistencia> */
    public function estudiante(): BelongsTo
    {
        return $this->belongsTo(Estudiante::class);
    }
}
