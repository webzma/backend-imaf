<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profesor extends Model
{
    use Auditable;

    protected string $auditEntidad = 'instructor';

    protected string $auditNombre = 'instructor';

    use HasFactory, SoftDeletes;

    protected $table = 'profesores';

    protected $fillable = [
        'user_id',
        'nacionalidad',
        'cedula',
        'telefono',
        'municipio',
        'especialidad_id',
        'titulo_id',
        'departamento_id',
        'fecha_nacimiento',
        'genero',
        'foto',
        'tipo_contrato_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
        ];
    }

    /** @phpstan-return BelongsTo<User, Profesor> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @phpstan-return BelongsTo<TipoContrato, Profesor> */
    public function tipoContrato(): BelongsTo
    {
        return $this->belongsTo(TipoContrato::class, 'tipo_contrato_id');
    }

    /** @phpstan-return BelongsTo<Especialidad, Profesor> */
    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class, 'especialidad_id');
    }

    /** @phpstan-return BelongsTo<Departamento, Profesor> */
    public function departamento(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    /** @phpstan-return BelongsTo<Titulo, Profesor> */
    public function titulo(): BelongsTo
    {
        return $this->belongsTo(Titulo::class, 'titulo_id');
    }

    /** @phpstan-return HasMany<Curso, Profesor> */
    public function cursos(): HasMany
    {
        return $this->hasMany(Curso::class, 'profesor_id');
    }
}
