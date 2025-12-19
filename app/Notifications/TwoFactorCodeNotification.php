<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TwoFactorCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $code;
    public $expiresAt;

    public function __construct($code, $expiresAt)
    {
        $this->code = $code;
        $this->expiresAt = $expiresAt;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        // Formatage de la date d'expiration pour l'affichage
        // Exemple : "11/12/2025 à 17:45"
        $expirationFormatted = $this->expiresAt->format('d/m/Y à H:i');

        return (new MailMessage)
            ->subject('Votre code de sécurité 2FA - ' . config('app.name'))
            // On indique à Laravel d'utiliser une vue Blade spécifique
            // et on lui passe les données nécessaires
            ->view('emails.two_factor_code', [
                'code' => $this->code,
                'expiration' => $expirationFormatted,
                'user' => $notifiable,
                // On peut passer le logo si nécessaire, ou le gérer dans la vue
            ]);
    }
}
