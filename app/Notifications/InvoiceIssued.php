<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceIssued extends Notification
{
    public function __construct(private Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->invoice;

        return (new MailMessage)
            ->subject("Invoice {$i->number} from ".config('app.name'))
            ->greeting('Hi '.$notifiable->name.',')
            ->line("Invoice {$i->number} for \"{$i->title}\" is ready.")
            ->line('Amount due: '.Money::format($i->total_cents).'. Due by '.$i->due_date->format('j F Y').'.')
            ->action('View invoice', route('invoices.show', $i->id))
            ->line('Our bank details are on the invoice. Once you have paid, upload your proof of payment in the portal.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Invoice {$this->invoice->number} is ready",
            'body' => Money::format($this->invoice->total_cents),
            'url' => route('invoices.show', $this->invoice->id, false),
        ];
    }
}
