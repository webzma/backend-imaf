<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/** @mixin DatabaseNotification */
class DatabaseNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'id' => $this->id,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'data' => [
                'titulo' => $data['titulo'] ?? null,
                'mensaje' => $data['mensaje'] ?? null,
                'url' => $data['url'] ?? null,
                'curso_id' => $data['curso_id'] ?? null,
                'estudiante_id' => $data['estudiante_id'] ?? null,
                'tipo' => $data['tipo'] ?? null,
            ],
        ];
    }
}
