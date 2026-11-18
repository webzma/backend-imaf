<?php

namespace App\Services;

use Cloudinary\Cloudinary as CloudinaryClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class CedulaEstudiante
{
    /**
     * Sube la imagen de la cédula y devuelve su URL segura.
     *
     * El `public_id` fijo por estudiante hace que una imagen nueva reemplace a
     * la anterior en Cloudinary, sin dejar copias huérfanas.
     */
    public static function subir(UploadedFile $archivo, int $estudianteId): string
    {
        try {
            $upload = app(CloudinaryClient::class)->uploadApi()->upload((string) $archivo->getRealPath(), [
                'folder' => 'imaf/cedulas',
                'public_id' => 'estudiante_'.$estudianteId,
                'overwrite' => true,
                'invalidate' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error subiendo la cédula del estudiante: '.$e->getMessage());

            abort(500, 'No se pudo subir la imagen de la cédula. Inténtalo de nuevo.');
        }

        return (string) $upload['secure_url'];
    }
}
