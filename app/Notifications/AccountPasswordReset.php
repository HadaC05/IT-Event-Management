<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountPasswordReset extends Notification
{
    use Queueable;
    public function __construct(public string $token, public string $accountType) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject("Reset your CITE {$this->accountType} account")
            ->line("This link resets only your {$this->accountType} account.")
            ->action('Reset this account password', route('password.reset', ['user' => $notifiable->id, 'token' => $this->token]))
            ->line('The link expires in 60 minutes. Your other CITE account is not affected.');
    }
}
