<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $token,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');

        $url = sprintf(
            '%s/reset-password?token=%s&email=%s',
            $frontendUrl,
            $this->token,
            urlencode($notifiable->getEmailForPasswordReset()),
        );

        $expira = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage())
            ->subject('Restablece tu contraseña · IMAF')
            ->greeting('Hola '.$notifiable->name)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $url)
            ->line(sprintf('Este enlace expira en %d minutos.', $expira))
            ->line('Si no solicitaste este cambio, puedes ignorar este correo; tu contraseña seguirá siendo la misma.');
    }
}
