<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to admins when a client uploads proof of payment. */
class PaymentSubmitted extends Notification
{
    public function __construct(private Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function sentence(): string
    {
        $p = $this->payment;

        return "{$p->invoice->client->business_name} uploaded proof of payment for {$p->invoice->number} (".Money::format($p->amount_cents).')';
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Proof of payment for '.$this->payment->invoice->number)
            ->line($this->sentence().'.')
            ->line('Check your bank account, then confirm the payment in the portal.')
            ->action('Review payment', route('admin.invoices.show', $this->payment->invoice_id));
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->sentence(), 'body' => null, 'url' => route('admin.invoices.show', $this->payment->invoice_id, false)];
    }
}
