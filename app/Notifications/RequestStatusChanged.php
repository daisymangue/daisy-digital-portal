<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the client's users when the team moves their request along. */
class RequestStatusChanged extends Notification
{
    public function __construct(private ServiceRequest $serviceRequest) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->serviceRequest;

        return (new MailMessage)
            ->subject("Update on your request {$r->number}")
            ->greeting('Hi '.$notifiable->name.',')
            ->line("Your request {$r->number} is now: {$r->clientStatusLabel()}.")
            ->action('View request', route('requests.show', $r->id));
    }

    public function toArray(object $notifiable): array
    {
        $r = $this->serviceRequest;

        return [
            'title' => "Request {$r->number}: {$r->clientStatusLabel()}",
            'body' => null,
            'url' => route('requests.show', $r->id, false),
        ];
    }
}
