<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Notifications\PaymentReviewed;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    /** Admin records a payment they have seen in the bank. Counts straight away. */
    public function store(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => ['required', 'string', 'max:20'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $payment = DB::transaction(function () use ($invoice, $data, $request) {
            $locked = Invoice::whereKey($invoice->id)->lockForUpdate()->first();
            $amount = $this->checkedAmount($locked, $data['amount']);

            $payment = new Payment(['amount_cents' => $amount, 'method' => $data['method'], 'paid_on' => $data['paid_on'], 'reference' => $data['reference'] ?? null, 'note' => $data['note'] ?? null]);
            $payment->invoice_id = $locked->id;
            $payment->client_id = $locked->client_id;
            $payment->status = 'confirmed';
            $payment->submitted_by = $request->user()->id;
            $payment->reviewed_by = $request->user()->id;
            $payment->reviewed_at = now();
            $payment->save();

            $locked->syncPaymentStatus();

            return $payment;
        });

        AuditLog::record('payment.recorded', $payment, ['invoice' => $invoice->number, 'amount_cents' => $payment->amount_cents]);
        $this->tellClient($payment);

        return back()->with('status', 'Payment of '.Money::format($payment->amount_cents).' recorded.');
    }

    /** Admin has checked the bank and the client's payment is there. */
    public function confirm(Request $request, Payment $payment)
    {
        $data = $request->validate(['amount' => ['nullable', 'string', 'max:20']]);

        DB::transaction(function () use ($payment, $data, $request) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->first();
            abort_unless($locked->status === 'pending', 409, 'This payment has already been reviewed.');

            $invoice = Invoice::whereKey($locked->invoice_id)->lockForUpdate()->first();
            // The admin can correct the amount to what actually arrived.
            $locked->amount_cents = $this->checkedAmount($invoice, $data['amount'] ?? (string) ($locked->amount_cents / 100));
            $locked->status = 'confirmed';
            $locked->reviewed_by = $request->user()->id;
            $locked->reviewed_at = now();
            $locked->save();

            $invoice->syncPaymentStatus();
        });

        $payment->refresh();
        AuditLog::record('payment.confirmed', $payment, ['invoice' => $payment->invoice->number, 'amount_cents' => $payment->amount_cents]);
        $this->tellClient($payment);

        return back()->with('status', 'Payment confirmed. The client has been notified.');
    }

    public function reject(Request $request, Payment $payment)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'min:3', 'max:500']]);

        abort_unless($payment->status === 'pending', 409, 'This payment has already been reviewed.');

        $payment->status = 'rejected';
        $payment->rejection_reason = $data['rejection_reason'];
        $payment->reviewed_by = $request->user()->id;
        $payment->reviewed_at = now();
        $payment->save();

        AuditLog::record('payment.rejected', $payment, ['invoice' => $payment->invoice->number]);
        $this->tellClient($payment);

        return back()->with('status', 'Payment marked as not confirmed. The client has been told why.');
    }

    private function checkedAmount(Invoice $invoice, string $input): int
    {
        if (! $invoice->isOpen()) {
            throw ValidationException::withMessages(['amount' => 'Payments can only be recorded on an issued invoice that is not fully paid.']);
        }

        $amount = Money::toCents($input) ?? 0;
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter the amount received.']);
        }
        if ($amount > $invoice->balanceCents()) {
            throw ValidationException::withMessages(['amount' => 'That is more than the '.Money::format($invoice->balanceCents()).' still owing on this invoice.']);
        }

        return $amount;
    }

    private function tellClient(Payment $payment): void
    {
        try {
            Notification::send($payment->invoice->client->users, new PaymentReviewed($payment));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
