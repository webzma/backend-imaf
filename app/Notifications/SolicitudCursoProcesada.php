<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SolicitudCursoProcesada extends Notification
{
    use Queueable;

    public const TIPO_APROBACION_PAGO = 'aprobacion_pago';

    public const TIPO_APROBACION_CURSO = 'aprobacion_curso';

    /**
     * @param  'aprobado'|'reprobado'  $estado
     */
    public function __construct(
        public string $nombreCurso,
        public string $estado,
        public int $cursoId,
        public string $tipo = self::TIPO_APROBACION_CURSO,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{titulo: string, mensaje: string, curso_id: int, tipo: string, url: string}
     */
    public function toDatabase(object $notifiable): array
    {
        $aprobado = $this->estado === 'aprobado';

        $titulo = $aprobado ? 'Solicitud aprobada' : 'Solicitud reprobada';

        $mensajeBase = match ($this->tipo) {
            self::TIPO_APROBACION_PAGO => $aprobado
                ? 'Tu pago fue aprobado. Ya puedes acceder al contenido del curso.'
                : 'Tu pago no fue aprobado. No podrás acceder al curso hasta regularizar el pago.',
            self::TIPO_APROBACION_CURSO => $aprobado
                ? 'Has completado los requisitos. Puedes obtener tu certificado.'
                : 'No se aprueba la finalización del curso para certificado.',
            default => $aprobado
                ? 'Tu solicitud relacionada con el curso fue aprobada.'
                : 'Tu solicitud relacionada con el curso fue reprobada.',
        };

        $mensaje = sprintf('%s Curso: %s.', $mensajeBase, $this->nombreCurso);

        $url = match ($this->tipo) {
            self::TIPO_APROBACION_PAGO,
            self::TIPO_APROBACION_CURSO => "/estudiante/curso?id={$this->cursoId}",
            default => '/estudiante/notificaciones',
        };

        return [
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'curso_id' => $this->cursoId,
            'tipo' => $this->tipo,
            'url' => $url,
        ];
    }
}
