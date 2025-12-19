<?php

namespace App\Mail;

use App\Models\User; // Changé de Profile à User
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TwoFactorAuthMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $code;
    public $expiration;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, $twoFactorCode) // Changé le type hint
    {
        $this->user = $user;
        $this->code = $twoFactorCode;
        $this->expiration = now()->addMinutes(10)->format('H:i');
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('🔒 Code de vérification à deux facteurs - ' . config('app.name'))
            ->view('emails.two-factor')
            ->with([
                'user' => $this->user,
                'code' => $this->code,
                'expiration' => $this->expiration,
            ]);
    }
}
