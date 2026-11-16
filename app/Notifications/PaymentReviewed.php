<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the client when a payment is confirmed or could not be confirmed. */
class PaymentReviewed extends Notification
{
    public function __construct(private Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function title(): string
    {
        $p = $this->payment;

        return $p->status === 'confirmed'
            ? 'Payment of '.Money::format($p->amount_cents)." received for {$p->invoice->number}"
            : "We could not confirm your payment for {$p->invoice->number}";
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payment;
        $mail = (new MailMessage)->subject($this->title())->greeting('Hi '.$notifiable->name.',');

        if ($p->status === 'confirmed') {
            $balance = $p->invoice->balanceCents();
            $mail->line('Thank you. We have received your payment of '.Money::format($p->amount_cents).'.')
                ->line($balance > 0 ? 'Still owing on this invoice: '.Money::format($balance).'.' : "Invoice {$p->invoice->number} is now paid in full.");
        } else {
            $mail->line('We could not confirm the payment you told us about.')
                ->line('Reason: '.$p->rejection_reason)
                ->line('Please check the details and upload proof again, or reply to this email.');
        }

        return $mail->action('View invoice', route('invoices.show', $p->invoice_id));
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->payment->rejection_reason, 'url' => route('invoices.show', $this->payment->invoice_id, false)];
    }
}
