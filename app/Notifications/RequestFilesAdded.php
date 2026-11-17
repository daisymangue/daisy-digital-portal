<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to admins when a client adds files to a request they already sent. */
class RequestFilesAdded extends Notification
{
    public function __construct(private ServiceRequest $serviceRequest, private int $count) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function sentence(): string
    {
        $r = $this->serviceRequest;

        return "{$r->client->business_name} added {$this->count} ".($this->count === 1 ? 'file' : 'files')." to {$r->number}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->sentence())
            ->line($this->sentence().'.')
            ->action('Open request', route('admin.requests.show', $this->serviceRequest));
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->sentence(), 'body' => null, 'url' => route('admin.requests.show', $this->serviceRequest, false)];
    }
}
