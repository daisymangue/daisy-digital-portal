@php
    $state = $invoice->effectiveStatus();
    $money = fn ($cents) => \App\Support\Money::format($cents);
    $plain = fn ($cents) => number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, '.', '');
@endphp
<x-layouts.app :title="'Invoice '.$invoice->reference()">
    <a href="{{ route('admin.invoices.index') }}" class="link text-sm">Back to invoices</a>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="h-page">{{ $invoice->isDraft() ? 'Draft invoice' : 'Invoice '.$invoice->number }}</h1>
            <p class="text-muted mt-1">
                {{ $invoice->title }} · <a href="{{ route('admin.clients.show', $invoice->client) }}" class="link">{{ $invoice->client->business_name }}</a>
                @if ($invoice->quote) · Quote <a href="{{ route('admin.quotes.show', $invoice->quote) }}" class="link">{{ $invoice->quote->reference() }}</a> @endif
                @if ($invoice->due_date) · Due {{ $invoice->due_date->format('j F Y') }} @endif
            </p>
        </div>
        <x-status :status="$state" :label="$invoice->statusLabel()" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @include('partials.invoice-document', ['invoice' => $invoice])

            <section class="card">
                <h2 class="h-section">Payments</h2>
                @forelse ($invoice->payments as $payment)
                    <div class="border-line mt-4 border-t pt-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ $money($payment->amount_cents) }} · {{ $payment->methodLabel() }} · {{ $payment->paid_on->format('j M Y') }}</p>
                                <p class="text-muted text-sm">
                                    {{ $payment->status === 'pending' ? 'Submitted' : 'Recorded' }} by {{ $payment->submitter->name }}
                                    @if ($payment->reference) · Ref {{ $payment->reference }} @endif
                                    @if ($payment->reviewer && $payment->status !== 'pending') · Reviewed by {{ $payment->reviewer->name }}, {{ $payment->reviewed_at->format('j M Y H:i') }} @endif
                                </p>
                                @foreach ($payment->files as $file)
                                    <p class="mt-1 text-sm"><a href="{{ route('files.download', $file) }}" class="link break-all">Proof: {{ $file->original_name }}</a></p>
                                @endforeach
                                @if ($payment->rejection_reason)<p class="mt-1 text-sm text-red-800">{{ $payment->rejection_reason }}</p>@endif
                            </div>
                            <x-status :status="$payment->status" :label="$payment->statusLabel()" />
                        </div>

                        @if ($payment->status === 'pending')
                            <div class="mt-4 grid gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 sm:grid-cols-2">
                                <form method="POST" action="{{ route('admin.payments.confirm', $payment) }}" class="space-y-3">
                                    @csrf
                                    <label for="confirm-{{ $payment->id }}" class="label">Amount that arrived (R)</label>
                                    <input id="confirm-{{ $payment->id }}" name="amount" inputmode="decimal" value="{{ $plain($payment->amount_cents) }}" class="field">
                                    <button type="submit" class="btn btn-primary w-full">Confirm, money received</button>
                                </form>
                                <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="space-y-3">
                                    @csrf
                                    <label for="reject-{{ $payment->id }}" class="label">Reason, if it did not arrive</label>
                                    <input id="reject-{{ $payment->id }}" name="rejection_reason" required maxlength="500" class="field" placeholder="Shown to the client">
                                    <button type="submit" class="btn btn-light w-full">Not received</button>
                                </form>
                                <p class="text-sm text-amber-950 sm:col-span-2">Check your bank account first. Proof of payment can be faked or reversed, so confirm only when the money shows.</p>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted mt-2">No payments yet.</p>
                @endforelse
            </section>
        </div>

        <aside class="min-w-0 space-y-6">
            <div class="card space-y-3">
                <h2 class="h-section">Actions</h2>
                @if ($invoice->isDraft())
                    <form method="POST" action="{{ route('admin.invoices.issue', $invoice) }}" onsubmit="return confirm('Issue this invoice to the client? It gets its number and can no longer be edited.')">
                        @csrf
                        <button type="submit" class="btn btn-primary w-full">Issue to client</button>
                    </form>
                    <a href="{{ route('admin.invoices.edit', $invoice) }}" class="btn btn-light w-full">Edit draft</a>
                    <p class="text-muted text-sm">The invoice number is assigned when you issue it, so numbers run in order with no gaps.</p>
                @endif
                <a href="{{ route('admin.invoices.pdf', $invoice) }}" class="btn btn-light w-full">Download PDF</a>
            </div>

            @if ($invoice->isOpen())
                <form method="POST" action="{{ route('admin.invoices.payments.store', $invoice) }}" class="card space-y-4">
                    @csrf
                    <h2 class="h-section">Record a payment</h2>
                    <p class="text-muted text-sm">For money you have already seen in your account.</p>
                    <div>
                        <label for="amount" class="label">Amount received (R)</label>
                        <input id="amount" name="amount" inputmode="decimal" required value="{{ old('amount', $plain($invoice->balanceCents())) }}" class="field">
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="method" class="label">Method</label>
                            <select id="method" name="method" class="field">
                                @foreach (\App\Models\Payment::METHODS as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="paid_on" class="label">Date</label>
                            <input id="paid_on" name="paid_on" type="date" required max="{{ today()->toDateString() }}" value="{{ old('paid_on', today()->toDateString()) }}" class="field">
                        </div>
                    </div>
                    <div>
                        <label for="reference" class="label">Bank reference <span class="text-muted font-normal">(optional)</span></label>
                        <input id="reference" name="reference" maxlength="120" class="field">
                    </div>
                    <button type="submit" class="btn btn-dark w-full">Record payment</button>
                </form>

                <form method="POST" action="{{ route('admin.invoices.cancel', $invoice) }}" class="card space-y-3" onsubmit="return confirm('Cancel this invoice?')">
                    @csrf
                    <h2 class="h-section">Cancel invoice</h2>
                    <label for="cancel_reason" class="label">Reason</label>
                    <input id="cancel_reason" name="cancel_reason" required maxlength="500" class="field">
                    <button type="submit" class="btn w-full text-red-800 hover:bg-red-50">Cancel invoice</button>
                    <p class="text-muted text-sm">Only possible while no payment is recorded. The number is kept for your records.</p>
                </form>
            @endif

            @if ($invoice->status === 'cancelled')
                <div class="card"><h2 class="h-section">Cancelled</h2><p class="mt-2">{{ $invoice->cancel_reason }}</p><p class="text-muted mt-1 text-sm">{{ $invoice->cancelled_at?->format('j M Y, H:i') }}</p></div>
            @endif
        </aside>
    </div>
</x-layouts.app>
