<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'primer_nombre',
        'segundo_nombre',
        'primer_apellido',
        'segundo_apellido',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            $user->sincronizarNombre();
        });

        static::updating(function (User $user) {
            $user->sincronizarNombre();
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim(collect([
            $this->primer_nombre,
            $this->segundo_nombre,
            $this->primer_apellido,
            $this->segundo_apellido,
        ])->filter()->implode(' '));
    }

    /**
     * Mantiene `name` sincronizado con las 4 columnas de nombre.
     * Si solo llega `name` (clientes antiguos), reparte las palabras
     * en las 4 columnas para no dejar campos vacíos.
     */
    private function sincronizarNombre(): void
    {
        $tienePartes = $this->primer_nombre
            || $this->segundo_nombre
            || $this->primer_apellido
            || $this->segundo_apellido;

        if ($tienePartes) {
            $compuesto = $this->getNombreCompletoAttribute();
            if ($compuesto !== '') {
                $this->name = $compuesto;
            }

            return;
        }

        if ($this->name && (! $this->exists || $this->isDirty('name'))) {
            [$this->primer_nombre, $this->segundo_nombre, $this->primer_apellido, $this->segundo_apellido] = self::repartirNombre($this->name);
        }
    }

    /**
     * Heurística por cantidad de palabras para repartir un nombre completo.
     */
    public static function repartirNombre(?string $nombre): array
    {
        if (! $nombre || trim($nombre) === '') {
            return [null, null, null, null];
        }

        $partes = preg_split('/\s+/u', trim($nombre));
        $total = count($partes);

        return match (true) {
            $total === 1 => [$partes[0], null, null, null],
            $total === 2 => [$partes[0], null, $partes[1], null],
            $total === 3 => [$partes[0], $partes[1], $partes[2], null],
            default => [
                $partes[0],
                implode(' ', array_slice($partes, 1, $total - 3)),
                $partes[$total - 2],
                $partes[$total - 1],
            ],
        };
    }

    public function isProfesor(): bool
    {
        return $this->role === 'profesor';
    }

    public function isEstudiante(): bool
    {
        return $this->role === 'estudiante';
    }

    public function estudiante()
    {
        return $this->hasOne(Estudiante::class);
    }

    public function profesor()
    {
        return $this->hasOne(Profesor::class);
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class);
    }
}
