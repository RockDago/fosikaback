<?php
// Créez ce fichier: app/Mail/UserAccountCreatedMail.php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserAccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $plainPassword;
    public $verificationCode;
    public $loginUrl;
    public $verificationUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $plainPassword, string $verificationCode)
    {
        $this->user = $user;
        $this->plainPassword = $plainPassword;
        $this->verificationCode = $verificationCode;
        $this->loginUrl = url('/login');
        $this->verificationUrl = url('/verify-email');
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('🎉 Votre compte a été créé - ' . config('app.name'))
            ->view('emails.user-account-created')
            ->with([
                'user' => $this->user,
                'plainPassword' => $this->plainPassword,
                'verificationCode' => $this->verificationCode,
                'loginUrl' => $this->loginUrl,
                'verificationUrl' => $this->verificationUrl,
                'appName' => config('app.name'),
                'expiration' => now()->addHours(24)->format('d/m/Y à H:i'),
            ]);
    }
}
