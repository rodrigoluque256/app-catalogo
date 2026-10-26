<?php

declare(strict_types=1);

namespace App\Application\Auth\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Alta de un usuario (o del dueño de una tienda nueva): link para elegir su contraseña. Reemplaza a la temporal.
 */
final class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $storeName,
        public readonly int $expiresInHours,
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
        $url = config('services.panel.url').'/admin/invitacion?'.http_build_query(['token' => $this->token, 'email' => $notifiable->email]);

        return (new MailMessage)
            ->subject("Te invitaron a administrar {$this->storeName}")
            ->greeting("¡Hola, {$notifiable->name}!")
            ->line("Te dieron acceso al panel de **{$this->storeName}**. Para entrar, elegí tu contraseña:")
            ->action('Elegir mi contraseña', $url)
            ->line("El link vence en {$this->expiresInHours} horas. Si venció, pedile a quien te invitó que te lo reenvíe.")
            ->line('Si no esperabas este email, podés ignorarlo.');
    }
}
