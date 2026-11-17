<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent when the admin gives a client access to the portal. */
class PortalInvite extends Notification
{
    public function __construct(private string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your client portal with '.config('app.name'))
            ->greeting('Hi '.$notifiable->name.',')
            ->line('We have set up a client portal for you. You can see your quotes, invoices and project updates there, and send us new requests.')
            ->action('Open your portal', $this->url)
            ->line('This link signs you in once and works for 7 days. After that, go to the sign-in page and choose "Email me a link".')
            ->line('Your username is this email address: '.$notifiable->email.'.')
            ->line('No password is needed, but you can set one under Account once you are in.');
    }
}
