<?php

namespace App\Notifications;

use App\Models\Quote;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the client's users when a quote is ready. */
class QuoteSent extends Notification
{
    public function __construct(private Quote $quote) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $q = $this->quote;

        return (new MailMessage)
            ->subject("Your quote {$q->reference()} is ready")
            ->greeting('Hi '.$notifiable->name.',')
            ->line("Your quote for \"{$q->title}\" is ready to view.")
            ->line('Total: '.Money::format($q->total_cents).'. Valid until '.$q->valid_until->format('j F Y').'.')
            ->action('View quote', route('quotes.show', $q->id))
            ->line('You can accept it, ask for changes or decline it in the portal.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Quote {$this->quote->reference()} is ready",
            'body' => $this->quote->title,
            'url' => route('quotes.show', $this->quote->id, false),
        ];
    }
}
