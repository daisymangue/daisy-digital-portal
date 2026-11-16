@php
    $state = $invoice->effectiveStatus();
    $money = fn ($cents) => \App\Support\Money::format($cents);
    $room = max(0, $invoice->balanceCents() - $pending);
@endphp
<x-layouts.app :title="'Invoice '.$invoice->number">
    <a href="{{ route('invoices.index') }}" class="link text-sm">Back to invoices</a>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="h-page">Invoice {{ $invoice->number }}</h1>
            <p class="text-muted mt-1">{{ $invoice->title }} · Issued {{ $invoice->issue_date->format('j F Y') }} · Due {{ $invoice->due_date->format('j F Y') }}</p>
        </div>
        <x-status :status="$state" :label="$invoice->clientStatusLabel()" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @include('partials.invoice-document', ['invoice' => $invoice])

            @if ($invoice->payments->isNotEmpty())
                <section class="card">
                    <h2 class="h-section">Payments</h2>
                    <ul class="divide-line mt-2 divide-y">
                        @foreach ($invoice->payments as $payment)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div>
                                    <p class="font-medium">{{ $money($payment->amount_cents) }} · {{ $payment->paid_on->format('j M Y') }}</p>
                                    @if ($payment->status === 'rejected')
                                        <p class="text-sm text-red-800">{{ $payment->rejection_reason }}</p>
                                    @endif
                                </div>
                                <x-status :status="$payment->status" :label="$payment->statusLabel()" />
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <aside class="min-w-0 space-y-6">
            @if ($invoice->isOpen())
                <div class="card">
                    <h2 class="h-section">How to pay</h2>
                    <p class="mt-2">Pay <span class="font-semibold">{{ $money($invoice->balanceCents()) }}</span> by EFT into this account:</p>
                    @if (trim($settings['bank_details']) !== '')
                        <p class="bg-sand-dark mt-3 rounded-xl px-4 py-3 whitespace-pre-line">{{ $settings['bank_details'] }}</p>
                    @else
                        <p class="bg-sand-dark mt-3 rounded-xl px-4 py-3">Please contact us for our bank details.</p>
                    @endif
                    <p class="mt-3">Use <span class="font-semibold">{{ $invoice->number }}</span> as your payment reference.</p>
                </div>

                @if ($pending > 0)
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-950">
                        We have your proof of payment for {{ $money($pending) }}. We will confirm it once the money shows in our account, usually within one or two business days.
                    </div>
                @endif

                @if ($room > 0)
                    <form method="POST" action="{{ route('invoices.pay', $invoice->id) }}" enctype="multipart/form-data" class="card space-y-4">
                        @csrf
                        <h2 class="h-section">I have paid</h2>
                        <p class="text-muted text-sm">Upload your proof of payment and we will confirm it.</p>
                        <div>
                            <label for="amount" class="label">Amount paid (R)</label>
                            <input id="amount" name="amount" inputmode="decimal" required class="field" value="{{ old('amount', number_format($room / 100, $room % 100 === 0 ? 0 : 2, '.', '')) }}">
                        </div>
                        <div>
                            <label for="paid_on" class="label">Date paid</label>
                            <input id="paid_on" name="paid_on" type="date" required max="{{ today()->toDateString() }}" value="{{ old('paid_on', today()->toDateString()) }}" class="field">
                        </div>
                        <div>
                            <label for="proof" class="label">Proof of payment</label>
                            <input id="proof" name="proof" type="file" required accept=".jpg,.jpeg,.png,.webp,.pdf" class="field file:bg-sand-dark file:mr-3 file:rounded-full file:px-4 file:py-1.5 file:font-medium">
                            <p class="text-muted mt-1.5 text-sm">A screenshot or PDF from your bank, up to 10 MB.</p>
                        </div>
                        <button type="submit" class="btn btn-primary w-full">Send proof of payment</button>
                    </form>
                @endif
            @elseif ($state === 'paid')
                <div class="card"><h2 class="h-section">Paid in full</h2><p class="mt-2">Thank you. This invoice was settled on {{ $invoice->paid_at?->format('j F Y') }}.</p></div>
            @elseif ($state === 'cancelled')
                <div class="card"><h2 class="h-section">Cancelled</h2><p class="mt-2">This invoice was cancelled and nothing is owing on it.</p></div>
            @endif

            <a href="{{ route('invoices.pdf', $invoice->id) }}" class="btn btn-light w-full">Download PDF</a>
        </aside>
    </div>
</x-layouts.app>
