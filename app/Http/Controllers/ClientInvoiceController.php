<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PaymentSubmitted;
use App\Support\DocumentPdf;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ClientInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $base = Invoice::where('client_id', $request->user()->client_id)->visibleToClient();

        return view('client.invoices.index', [
            'invoices' => (clone $base)->latest('issued_at')->paginate(15),
            'outstanding' => (int) (clone $base)->open()->sum(DB::raw('total_cents - paid_cents')),
        ]);
    }

    public function show(Request $request, string $id)
    {
        $invoice = $this->find($request, $id);

        return view('client.invoices.show', [
            'invoice' => $invoice,
            'settings' => Setting::values(),
            'pending' => $invoice->pendingCents(),
        ]);
    }

    public function pdf(Request $request, string $id)
    {
        return DocumentPdf::invoice($this->find($request, $id));
    }

    /**
     * Client tells us they have paid and uploads proof. This does NOT mark the invoice paid.
     * It creates a pending payment that an admin confirms after checking the bank.
     */
    public function pay(Request $request, string $id)
    {
        $invoice = $this->find($request, $id);

        $data = $request->validate([
            'amount' => ['required', 'string', 'max:20'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:120'],
            'proof' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [
            'proof.required' => 'Please attach your proof of payment.',
            'proof.mimes' => 'Proof of payment must be an image or a PDF.',
            'proof.max' => 'The file can be up to 10 MB.',
        ]);

        if (! $invoice->isOpen()) {
            return back()->withErrors(['amount' => 'This invoice is not open for payment.']);
        }

        $amount = Money::toCents($data['amount']) ?? 0;
        $room = $invoice->balanceCents() - $invoice->pendingCents();
        if ($amount <= 0) {
            return back()->withErrors(['amount' => 'Enter the amount you paid.'])->withInput();
        }
        if ($amount > $room) {
            return back()->withErrors(['amount' => $room > 0
                ? 'That is more than the '.Money::format($room).' still to pay on this invoice.'
                : 'We already have a payment for the full amount waiting to be checked.'])->withInput();
        }

        $user = $request->user();

        $payment = DB::transaction(function () use ($invoice, $data, $amount, $user, $request) {
            $payment = new Payment(['amount_cents' => $amount, 'method' => 'eft', 'paid_on' => $data['paid_on'], 'reference' => $data['reference'] ?? null]);
            $payment->invoice_id = $invoice->id;
            $payment->client_id = $invoice->client_id;
            $payment->status = 'pending';
            $payment->submitted_by = $user->id;
            $payment->save();

            $upload = $request->file('proof');
            $payment->files()->create([
                'client_id' => $invoice->client_id,
                'uploaded_by' => $user->id,
                'original_name' => mb_substr($upload->getClientOriginalName(), 0, 200),
                'path' => $upload->store("clients/{$invoice->client_id}/payments/{$payment->id}", 'local'),
                'mime' => $upload->getMimeType(),
                'size' => $upload->getSize(),
            ]);

            return $payment;
        });

        AuditLog::record('payment.submitted', $payment, ['invoice' => $invoice->number, 'amount_cents' => $amount]);

        try {
            Notification::send(User::where('role', User::ROLE_ADMIN)->get(), new PaymentSubmitted($payment->load('invoice.client')));
        } catch (\Throwable $e) {
            report($e);
        }

        return redirect()->route('invoices.show', $invoice->id)
            ->with('status', 'Thank you. We have your proof of payment and will confirm once the money shows in our account.');
    }

    private function find(Request $request, string $id): Invoice
    {
        return Invoice::where('client_id', $request->user()->client_id)->visibleToClient()
            ->with('items', 'client', 'payments.files')->findOrFail($id);
    }
}
