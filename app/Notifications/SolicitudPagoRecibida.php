<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SolicitudPagoRecibida extends Notification
{
    use Queueable;

    public const TIPO_SOLICITUD_PAGO = 'solicitud_pago';

    public function __construct(
        public string $nombreEstudiante,
        public string $nombreCurso,
        public int $cursoId,
        public int $estudianteId,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{titulo: string, mensaje: string, curso_id: int, estudiante_id: int, tipo: string, url: string}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'titulo' => 'Nueva solicitud de pago',
            'mensaje' => sprintf(
                '%s solicitó revisión del pago para el curso «%s».',
                $this->nombreEstudiante,
                $this->nombreCurso
            ),
            'curso_id' => $this->cursoId,
            'estudiante_id' => $this->estudianteId,
            'tipo' => self::TIPO_SOLICITUD_PAGO,
            'url' => '/admin/estudiantes',
        ];
    }
}
