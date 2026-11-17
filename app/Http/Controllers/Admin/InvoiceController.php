<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Setting;
use App\Notifications\InvoiceReminder;
use App\Support\DocumentPdf;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $invoices = Invoice::with('client')
            ->when($status === 'overdue', fn ($q) => $q->overdue())
            ->when($status !== 'overdue' && isset(Invoice::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($c) => $c->where('business_name', 'like', "%{$search}%"))))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.invoices.index', [
            'invoices' => $invoices,
            'status' => $status,
            'search' => $search,
            'clients' => Client::whereNull('archived_at')->orderBy('business_name')->get(['id', 'business_name']),
            'outstanding' => (int) Invoice::open()->sum(DB::raw('total_cents - paid_cents')),
            'overdueCount' => Invoice::overdue()->count(),
        ]);
    }

    /** Blank invoice for a client, for work that never had a quote. */
    public function store(Request $request)
    {
        $data = $request->validate(['client_id' => ['required', 'uuid', 'exists:clients,id']]);

        $invoice = $this->newDraft($data['client_id'], null, 'custom', 'Invoice');
        $invoice->vat_rate = (float) Setting::get('vat_rate');
        $invoice->save();

        return redirect()->route('admin.invoices.edit', $invoice);
    }

    /** Deposit, balance or full invoice from an accepted quote. Amounts come from the quote, to the cent. */
    public function storeFromQuote(Request $request, Quote $quote)
    {
        $data = $request->validate(['type' => ['required', Rule::in(['deposit', 'balance', 'full'])]]);
        $type = $data['type'];

        if ($quote->status !== 'accepted') {
            return back()->withErrors(['invoice' => 'Invoices can only be created from an accepted quote.']);
        }

        $existing = Invoice::where('quote_id', $quote->id)->where('status', '!=', 'cancelled')->get();
        $invoiced = (int) $existing->sum('total_cents');
        $remaining = $quote->total_cents - $invoiced;

        $error = match (true) {
            $type === 'deposit' && $quote->deposit_percent <= 0 => 'This quote has no deposit set.',
            $type === 'deposit' && $existing->isNotEmpty() => 'This quote already has an invoice, so use a balance invoice for the rest.',
            $type === 'full' && $existing->isNotEmpty() => 'This quote already has an invoice, so use a balance invoice for the rest.',
            $type === 'balance' && $existing->isEmpty() => 'Create the deposit or full invoice first.',
            $remaining <= 0 => 'This quote has already been invoiced in full.',
            default => null,
        };
        if ($error) {
            return back()->withErrors(['invoice' => $error]);
        }

        $invoice = DB::transaction(function () use ($quote, $type, $remaining) {
            $label = Invoice::TYPES[$type];
            $invoice = $this->newDraft($quote->client_id, $quote, $type, "{$quote->title} ({$label})");
            $invoice->vat_rate = $quote->vat_rate;

            if ($type === 'full') {
                foreach ($quote->items as $i => $item) {
                    $invoice->items()->create($item->only(['description', 'quantity', 'unit', 'unit_price_cents', 'line_total_cents']) + ['sort_order' => $i + 1]);
                }
                $invoice->subtotal_cents = $quote->subtotal_cents;
                $invoice->discount_cents = $quote->discount_cents;
                $invoice->vat_cents = $quote->vat_cents;
                $invoice->total_cents = $quote->total_cents;
            } else {
                // One line for the amount due. VAT is the difference, so deposit plus balance always equals the quote total exactly.
                $total = $type === 'deposit' ? $quote->depositCents() : $remaining;
                $excl = (int) round($total / (1 + $quote->vat_rate / 100));
                $pct = rtrim(rtrim(number_format($quote->deposit_percent, 2), '0'), '.');
                $description = $type === 'deposit'
                    ? "Deposit ({$pct}%) for quote {$quote->reference()}: {$quote->title}"
                    : "Balance for quote {$quote->reference()}: {$quote->title}";

                $invoice->items()->create(['description' => $description, 'quantity' => 1, 'unit_price_cents' => $excl, 'line_total_cents' => $excl, 'sort_order' => 1]);
                $invoice->subtotal_cents = $excl;
                $invoice->vat_cents = $total - $excl;
                $invoice->total_cents = $total;
            }
            $invoice->save();

            return $invoice;
        });

        return redirect()->route('admin.invoices.show', $invoice)->with('status', 'Draft invoice created. Check it, then issue it to the client.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load('client.users', 'items', 'quote', 'payments.files', 'payments.submitter', 'payments.reviewer');

        return view('admin.invoices.show', ['invoice' => $invoice, 'settings' => Setting::values()]);
    }

    public function edit(Invoice $invoice)
    {
        if (! $invoice->isDraft()) {
            return redirect()->route('admin.invoices.show', $invoice)->with('status', 'An issued invoice cannot be edited. Cancel it and create a new one if it is wrong.');
        }

        return view('admin.invoices.edit', ['invoice' => $invoice->load('client', 'items')]);
    }

    public function update(Request $request, Invoice $invoice)
    {
        abort_unless($invoice->isDraft(), 409, 'An issued invoice cannot be edited.');

        $rules = [
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'due_date' => ['required', 'date', 'after_or_equal:today'],
        ];
        if (! $invoice->isFromQuote()) {
            $rules += [
                'discount' => ['nullable', 'string', 'max:20'],
                'vat_rate' => ['required', 'numeric', 'min:0', 'max:50'],
                'items' => ['required', 'array', 'min:1', 'max:100'],
                'items.*.description' => ['required', 'string', 'max:500'],
                'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:100000'],
                'items.*.unit' => ['nullable', 'string', 'max:30'],
                'items.*.price' => ['nullable', 'string', 'max:20'],
            ];
        }
        $data = $request->validate($rules, ['items.required' => 'Add at least one line item.']);

        DB::transaction(function () use ($invoice, $data) {
            $invoice->fill(['title' => $data['title'], 'notes' => $data['notes'] ?? null, 'due_date' => $data['due_date']]);

            if (! $invoice->isFromQuote()) {
                $invoice->discount_cents = Money::toCents($data['discount'] ?? null) ?? 0;
                $invoice->vat_rate = $data['vat_rate'];
                $invoice->items()->delete();
                foreach (array_values($data['items']) as $i => $item) {
                    $price = Money::toCents($item['price'] ?? null) ?? 0;
                    $invoice->items()->create([
                        'description' => $item['description'],
                        'quantity' => (float) $item['quantity'],
                        'unit' => ($item['unit'] ?? null) ?: null,
                        'unit_price_cents' => $price,
                        'line_total_cents' => (int) round((float) $item['quantity'] * $price),
                        'sort_order' => $i + 1,
                    ]);
                }
                $invoice->recalculate();
            }
            $invoice->save();
        });

        AuditLog::record('invoice.updated', $invoice);

        if ($request->input('action') === 'issue') {
            return $this->issue($invoice);
        }

        return redirect()->route('admin.invoices.edit', $invoice)->with('status', 'Draft saved. The client cannot see it until you issue it.');
    }

    public function issue(Invoice $invoice)
    {
        abort_unless($invoice->isDraft(), 409);

        if ($invoice->total_cents <= 0) {
            return redirect()->route('admin.invoices.edit', $invoice)->withErrors(['items' => 'An invoice needs an amount before it can be issued.']);
        }

        $invoice->issueNow();

        $message = $invoice->client->users()->exists()
            ? "Invoice {$invoice->number} issued. The client has been notified."
            : "Invoice {$invoice->number} issued. This client has no portal login, so download the PDF and send it to them yourself.";

        return redirect()->route('admin.invoices.show', $invoice)->with('status', $message);
    }

    public function cancel(Request $request, Invoice $invoice)
    {
        $data = $request->validate(['cancel_reason' => ['required', 'string', 'min:3', 'max:500']]);

        if (! $invoice->isOpen()) {
            return back()->withErrors(['invoice' => 'Only an issued, unpaid invoice can be cancelled.']);
        }
        if ($invoice->payments()->whereIn('status', ['confirmed', 'pending'])->exists()) {
            return back()->withErrors(['invoice' => 'This invoice has payments recorded or waiting to be checked, so it cannot be cancelled.']);
        }

        // The number is kept, so the sequence still has no gaps. The record stays for the books.
        $invoice->status = 'cancelled';
        $invoice->cancelled_at = now();
        $invoice->cancel_reason = $data['cancel_reason'];
        $invoice->save();

        AuditLog::record('invoice.cancelled', $invoice, ['number' => $invoice->number, 'reason' => $data['cancel_reason']]);

        return back()->with('status', "Invoice {$invoice->number} cancelled.");
    }

    public function destroy(Invoice $invoice)
    {
        abort_unless($invoice->isDraft(), 409, 'Only drafts can be deleted.');

        AuditLog::record('invoice.draft_deleted', $invoice);
        $invoice->delete();

        return redirect()->route('admin.invoices.index')->with('status', 'Draft deleted.');
    }

    public function pdf(Invoice $invoice)
    {
        return DocumentPdf::invoice($invoice);
    }

    private function newDraft(string $clientId, ?Quote $quote, string $type, string $title): Invoice
    {
        $invoice = new Invoice(['title' => $title, 'due_date' => today()->addDays((int) Setting::get('invoice_due_days'))]);
        $invoice->client_id = $clientId;
        $invoice->quote_id = $quote?->id;
        $invoice->type = $type;
        $invoice->status = 'draft';
        $invoice->created_by = auth()->id();
        $invoice->save();

        AuditLog::record('invoice.created', $invoice, ['type' => $type]);

        return $invoice;
    }

    public function remind(Invoice $invoice)
    {
        abort_unless($invoice->isOpen(), 409, 'Only unpaid issued invoices can be reminded.');
        if ($invoice->client->users()->doesntExist()) {
            return back()->withErrors(['remind' => 'This client has no portal login. Send the PDF yourself, or give them access first.']);
        }
        if ($invoice->last_reminded_at?->isToday()) {
            return back()->withErrors(['remind' => 'A reminder already went out today.']);
        }

        Notification::send($invoice->client->users, new InvoiceReminder($invoice));
        $invoice->last_reminded_at = now();
        $invoice->save();
        AuditLog::record('invoice.reminder', $invoice, ['number' => $invoice->number, 'manual' => true]);

        return back()->with('status', 'Reminder sent.');
    }
}
