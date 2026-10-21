<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Un evento de la bitácora de auditoría.
 *
 * Solo se inserta: modificar o borrar un registro lanza una excepción. La
 * depuración por antigüedad (`bitacora:depurar`) borra con el constructor de
 * consultas, a propósito fuera de este modelo, y deja constancia de ello.
 */
class Bitacora extends Model
{
    use HasUlids;

    protected $table = 'bitacora';

    public const UPDATED_AT = null;

    public const CATEGORIAS = ['autenticacion', 'seguridad', 'datos', 'negocio', 'sistema'];

    public const NIVELES = ['info', 'aviso', 'critico'];

    protected $guarded = [];

    /** Microsegundos: dos eventos de la misma petición se ordenan bien. */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'cambios' => 'array',
            'metadatos' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los registros de la bitácora no se pueden modificar.'));
        static::deleting(fn () => throw new LogicException('Los registros de la bitácora no se pueden eliminar.'));
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Campos que entran en la huella, en orden fijo.
     *
     * @return array<string, mixed>
     */
    public function datosFirmados(): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s.u'),
            'evento' => $this->evento,
            'categoria' => $this->categoria,
            'nivel' => $this->nivel,
            'descripcion' => $this->descripcion,
            'actor_id' => $this->actor_id,
            'actor_nombre' => $this->actor_nombre,
            'actor_rol' => $this->actor_rol,
            'entidad' => $this->entidad,
            'entidad_id' => $this->entidad_id,
            'cambios' => $this->cambios,
            'metadatos' => $this->metadatos,
            'origen' => $this->origen,
            'solicitud_id' => $this->solicitud_id,
            'ip' => $this->ip,
            'metodo' => $this->metodo,
            'ruta' => $this->ruta,
        ];
    }

    /**
     * HMAC-SHA256 de la forma canónica de los datos: claves ordenadas en
     * todos los niveles, porque MySQL reordena las de las columnas JSON.
     */
    public static function calcularHuella(array $datos): string
    {
        return hash_hmac(
            'sha256',
            json_encode(self::canonico($datos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (string) config('auditoria.clave'),
        );
    }

    private static function canonico(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }
        if (! array_is_list($valor)) {
            ksort($valor);
        }

        return array_map(self::canonico(...), $valor);
    }

    public function huellaValida(): bool
    {
        return hash_equals($this->huella, self::calcularHuella($this->datosFirmados()));
    }

    /** Filtros comunes al listado y a la exportación. */
    public function scopeFiltrar(Builder $query, array $f): Builder
    {
        return $query
            ->when($f['categoria'] ?? null, fn ($q, $v) => $q->whereIn('categoria', (array) $v))
            ->when($f['nivel'] ?? null, fn ($q, $v) => $q->whereIn('nivel', (array) $v))
            ->when($f['evento'] ?? null, function ($q, $v) {
                // "pago.*" filtra por prefijo.
                str_ends_with($v, '*')
                    ? $q->where('evento', 'like', rtrim($v, '*').'%')
                    : $q->where('evento', $v);
            })
            ->when($f['actor_id'] ?? null, fn ($q, $v) => $q->where('actor_id', $v))
            ->when($f['entidad'] ?? null, fn ($q, $v) => $q->where('entidad', $v))
            ->when($f['entidad_id'] ?? null, fn ($q, $v) => $q->where('entidad_id', (string) $v))
            ->when($f['ip'] ?? null, fn ($q, $v) => $q->where('ip', $v))
            ->when($f['solicitud_id'] ?? null, fn ($q, $v) => $q->where('solicitud_id', $v))
            ->when($f['origen'] ?? null, fn ($q, $v) => $q->where('origen', $v))
            ->when($f['desde'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v))
            ->when($f['hasta'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v))
            ->when($f['q'] ?? null, function ($q, $v) {
                $q->where(fn ($w) => $w
                    ->where('descripcion', 'like', "%{$v}%")
                    ->orWhere('actor_nombre', 'like', "%{$v}%")
                    ->orWhere('entidad_etiqueta', 'like', "%{$v}%"));
            });
    }
}
