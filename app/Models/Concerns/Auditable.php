<?php

namespace App\Models\Concerns;

use App\Services\Auditoria;
use Illuminate\Support\Str;

/**
 * Registra en la bitácora cada alta, cambio, borrado y restauración del
 * modelo, con los valores de antes y después de los campos que cambiaron.
 *
 * Personalización opcional en el modelo:
 *   protected string $auditEntidad = 'curso';          // alias en la bitácora
 *   protected array $auditExcluir = ['foto'];          // campos que no se guardan
 *   protected string $auditNombre = 'curso';           // para la descripción
 *
 * Las actualizaciones masivas (`Modelo::where(...)->update()`) no disparan
 * eventos de Eloquent: quien las haga debe registrar un evento propio.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($modelo) {
            $modelo->registrarAuditoria('creado', 'Alta de', [
                'despues' => $modelo->valoresAuditables($modelo->getAttributes()),
            ]);
        });

        static::updated(function ($modelo) {
            $cambios = $modelo->cambiosAuditables();
            if ($cambios === null) {
                return;
            }
            $modelo->registrarAuditoria('actualizado', 'Modificación de', $cambios);
        });

        static::deleted(function ($modelo) {
            $definitivo = ! method_exists($modelo, 'isForceDeleting') || $modelo->isForceDeleting();
            $modelo->registrarAuditoria(
                $definitivo ? 'eliminado' : 'archivado',
                $definitivo ? 'Eliminación de' : 'Archivado de',
                ['antes' => $modelo->valoresAuditables($modelo->getRawOriginal())],
                'aviso',
            );
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function ($modelo) {
                $modelo->registrarAuditoria('restaurado', 'Restauración de', null);
            });
        }
    }

    public function auditEntidad(): string
    {
        return property_exists($this, 'auditEntidad')
            ? $this->auditEntidad
            : Str::snake(class_basename($this));
    }

    /** Texto legible del registro: su nombre, código o título. */
    public function auditEtiqueta(): ?string
    {
        foreach (['nombre', 'name', 'titulo', 'codigo', 'referencia', 'email'] as $campo) {
            $valor = $this->getAttribute($campo);
            if (is_string($valor) && $valor !== '') {
                return $valor;
            }
        }

        return null;
    }

    protected function registrarAuditoria(string $accion, string $verbo, ?array $cambios, string $nivel = 'info'): void
    {
        $entidad = $this->auditEntidad();
        $nombre = property_exists($this, 'auditNombre')
            ? $this->auditNombre
            : str_replace('_', ' ', $entidad);
        $etiqueta = $this->auditEtiqueta();

        Auditoria::registrar(
            "{$entidad}.{$accion}",
            sprintf(
                '%s %s %s',
                $verbo,
                $nombre,
                $etiqueta ? "«{$etiqueta}» (#{$this->getKey()})" : "#{$this->getKey()}",
            ),
            [
                'categoria' => 'datos',
                'nivel' => $nivel,
                'entidad' => $this,
                'cambios' => $cambios,
            ],
        );
    }

    /** @return array{antes: array, despues: array}|null */
    protected function cambiosAuditables(): ?array
    {
        $ignorados = config('auditoria.campos_ignorados', []);
        $nuevos = array_diff_key($this->getChanges(), array_flip($ignorados));
        $nuevos = $this->valoresAuditables($nuevos);

        if ($nuevos === []) {
            return null;
        }

        $antes = [];
        foreach (array_keys($nuevos) as $campo) {
            $antes[$campo] = $this->getRawOriginal($campo);
        }

        return ['antes' => $antes, 'despues' => $nuevos];
    }

    /** Quita los campos excluidos del modelo; los sensibles los oculta el servicio. */
    protected function valoresAuditables(array $valores): array
    {
        $excluir = property_exists($this, 'auditExcluir') ? $this->auditExcluir : [];

        return array_diff_key($valores, array_flip($excluir));
    }
}
