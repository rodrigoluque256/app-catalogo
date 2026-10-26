<?php

declare(strict_types=1);

namespace App\Application\Auth\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ResetPasswordLink extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly int $expiresInMinutes,
    ) {
        $this->locale('es');
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = config('services.panel.url').'/admin/restablecer?'.http_build_query(['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)
            ->subject('Restablecé tu contraseña')
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line('Recibimos un pedido para restablecer la contraseña de tu cuenta.')
            ->action('Restablecer contraseña', $url)
            ->line("El link vence en {$this->expiresInMinutes} minutos y sirve una sola vez.")
            ->line('Si no lo pediste, ignorá este email: tu contraseña no cambia.');
    }
}
