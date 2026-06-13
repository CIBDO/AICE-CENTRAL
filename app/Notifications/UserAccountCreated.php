<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserAccountCreated extends Notification implements ShouldQueue
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
            ->subject('Votre compte AICE — Hub central DGTCP')
            ->greeting('Bonjour '.$notifiable->prenom.',')
            ->line('Un compte vous a été créé sur le hub central AICE.')
            ->line('**Login :** '.$notifiable->login)
            ->line('**Mot de passe temporaire :** '.$this->plainPassword)
            ->action('Se connecter', url('/login'))
            ->line('Vous devrez changer votre mot de passe lors de votre première connexion.')
            ->salutation('Direction Générale du Trésor et de la Comptabilité Publique');
    }
}
