<?php

namespace App\Http\Resources;

use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Curso */
class CursoResumenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion,
            'estado' => $this->estado,
            'modalidad' => $this->modalidad,
            'limite_cupo' => $this->limite_cupo,
            'cupos_restantes' => $this->cupos_restantes,
            'fecha_inicio' => $this->fecha_inicio?->toDateString(),
            'fecha_fin' => $this->fecha_fin?->toDateString(),
        ];
    }
}
