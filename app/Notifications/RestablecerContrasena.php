<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Correo con el enlace para restablecer la contraseña, en español y con el
 * diseño de las pantallas de contraseña.
 *
 * Hereda de la notificación de Laravel para no reescribir el enlace: el
 * token, la ruta `password.reset` y el correo que va en la URL siguen
 * saliendo de `resetUrl()`. Sólo cambia el mensaje. Se envía al momento,
 * sin cola, como la original: el enlace vence en minutos.
 */
class RestablecerContrasena extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $broker = config('auth.defaults.passwords');

        return (new MailMessage)
            ->subject('Elige tu contraseña nueva · UTAMED')
            ->view(['mail.restablecer-contrasena', 'mail.restablecer-contrasena-texto'], [
                'nombre'  => $this->nombreDe($notifiable),
                'url'     => $this->resetUrl($notifiable),
                'minutos' => (int) config("auth.passwords.{$broker}.expire"),
            ]);
    }

    /**
     * El primer nombre, como se lee: la base guarda los nombres en mayúsculas.
     */
    private function nombreDe($notifiable): ?string
    {
        $nombre = trim((string) ($notifiable->nombre1 ?? ''));

        return $nombre === '' ? null : mb_convert_case(mb_strtolower($nombre, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
}
