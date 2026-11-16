<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to admins when a client submits a request. */
class NewRequestSubmitted extends Notification
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
            ->subject("New request {$r->number} from {$r->client->business_name}")
            ->line("{$r->user->name} at {$r->client->business_name} has submitted a new request.")
            ->action('Review request', route('admin.requests.show', $r));
    }

    public function toArray(object $notifiable): array
    {
        $r = $this->serviceRequest;

        return [
            'title' => "New request {$r->number}",
            'body' => $r->client->business_name,
            'url' => route('admin.requests.show', $r, false),
        ];
    }
}
