<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserPasswordReset extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $plainPassword,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe — Hub central AICE')
            ->greeting('Bonjour '.$notifiable->prenom.',')
            ->line('Votre mot de passe a été réinitialisé par un administrateur.')
            ->line('**Login :** '.$notifiable->login)
            ->line('**Nouveau mot de passe temporaire :** '.$this->plainPassword)
            ->action('Se connecter', url('/login'))
            ->line('Vous devrez définir un nouveau mot de passe lors de votre prochaine connexion.')
            ->salutation('Direction Générale du Trésor et de la Comptabilité Publique');
    }
}
