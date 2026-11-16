<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLoginLink extends Notification
{
    public function __construct(private string $url, private int $minutes) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your sign-in link')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Use the button below to sign in to your client portal. No password needed.')
            ->action('Sign in', $this->url)
            ->line("This link works once and expires in {$this->minutes} minutes.")
            ->line('If you did not ask for it, you can ignore this email.');
    }
}
