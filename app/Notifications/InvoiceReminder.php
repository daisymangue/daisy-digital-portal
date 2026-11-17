<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A friendly nudge about an unpaid invoice. Sent automatically when overdue, or by hand. */
class InvoiceReminder extends Notification
{
    public function __construct(private Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $i = $this->invoice;
        $overdue = $i->due_date && $i->due_date->endOfDay()->isPast();

        return (new MailMessage)
            ->subject(($overdue ? 'Reminder: invoice ' : 'Invoice ')."{$i->number} ".($overdue ? 'is overdue' : 'is due soon'))
            ->greeting('Hi '.$notifiable->name.',')
            ->line("This is a friendly reminder that invoice {$i->number} for \"{$i->title}\" has ".Money::format($i->balanceCents()).' outstanding.')
            ->line(($overdue ? 'It was due on ' : 'It is due on ').$i->due_date->format('j F Y').'.')
            ->action('View and pay', route('invoices.show', $i->id))
            ->line('If you have already paid, please upload your proof of payment in the portal and ignore this email. Thank you.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Reminder: invoice {$this->invoice->number}",
            'body' => Money::format($this->invoice->balanceCents()).' outstanding',
            'url' => route('invoices.show', $this->invoice->id, false),
        ];
    }
}
