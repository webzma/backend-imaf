<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Cloudinary\Cloudinary as CloudinaryClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * @method static Builder<Pago> deCurso(int|string $cursoId)
 * @method static Builder<Pago> deUsuario(int $userId)
 */
class Pago extends Model
{
    use Auditable;

    protected string $auditEntidad = 'pago';

    protected string $auditNombre = 'pago';

    use SoftDeletes;

    protected $fillable = [
        'inscripcion_id',
        'metodo_pago',
        'referencia',
        'banco_origen',
        'comprobante',
        'estado',
        'nota_admin',
    ];

    protected $with = ['inscripcion'];

    protected $appends = ['comprobante_url', 'curso_id'];

    protected $hidden = ['inscripcion'];

    public function getComprobanteUrlAttribute(): ?string
    {
        if (empty($this->comprobante)) {
            return null;
        }

        try {
            return (string) app(CloudinaryClient::class)->image($this->comprobante)->toUrl();
        } catch (\Exception $e) {
            return null;
        }
    }

    /** El curso del pago, aunque la inscripción se haya retirado. */
    public function getCursoIdAttribute(): ?int
    {
        return $this->inscripcion?->curso_id;
    }

    /** El usuario que pagó, aunque su ficha de estudiante esté dada de baja. */
    public function getUserAttribute(): ?User
    {
        return $this->inscripcion?->estudiante()->withTrashed()->first()?->user;
    }

    /** "Ana Pérez → Dibujo" en la bitácora. */
    public function auditEtiqueta(): ?string
    {
        $usuario = $this->user?->name;
        $curso = Curso::withTrashed()->find($this->curso_id)?->nombre;

        return trim(($usuario ?? 'Usuario desconocido').' → '.($curso ?? 'curso #'.$this->curso_id));
    }

    /** @param Builder<Pago> $query */
    public function scopeDeCurso(Builder $query, int|string $cursoId): void
    {
        $query->whereHas('inscripcion', fn (Builder $i) => $i->where('curso_id', $cursoId));
    }

    /** @param Builder<Pago> $query */
    public function scopeDeUsuario(Builder $query, int $userId): void
    {
        $query->whereHas('inscripcion.estudiante', fn (Builder $e) => $e->where('user_id', $userId));
    }

    /**
     * Incluye las retiradas: el pago sigue existiendo aunque quiten al estudiante.
     *
     * @phpstan-return BelongsTo<Inscripcion, Pago>
     */
    public function inscripcion(): BelongsTo
    {
        $relacion = $this->belongsTo(Inscripcion::class);
        $relacion->getQuery()->withoutGlobalScope(SoftDeletingScope::class);

        return $relacion;
    }

    /** @phpstan-return HasOneThrough<Estudiante, Inscripcion, Pago> */
    public function estudiante(): HasOneThrough
    {
        return $this->hasOneThrough(Estudiante::class, Inscripcion::class, 'id', 'id', 'inscripcion_id', 'estudiante_id')
            ->withTrashedParents();
    }

    /** @phpstan-return HasOneThrough<Curso, Inscripcion, Pago> */
    public function curso(): HasOneThrough
    {
        return $this->hasOneThrough(Curso::class, Inscripcion::class, 'id', 'id', 'inscripcion_id', 'curso_id')
            ->withTrashedParents();
    }
}
