{{-- The quote itself, shared by the client and admin pages. --}}
@php
    $money = fn ($cents) => \App\Support\Money::format($cents);
@endphp
@if ($quote->scope)
    <section class="card">
        <h2 class="h-section">Scope and deliverables</h2>
        <p class="mt-3 whitespace-pre-line">{{ $quote->scope }}</p>
    </section>
@endif

<section class="card">
    <h2 class="h-section">What is included</h2>
    <div class="mt-3 overflow-x-auto">
        <table class="w-full min-w-[460px] text-left">
            <thead class="text-muted text-sm">
                <tr>
                    <th scope="col" class="border-line border-b py-2 pr-3 font-medium">Item</th>
                    <th scope="col" class="border-line border-b px-3 py-2 text-right font-medium">Qty</th>
                    <th scope="col" class="border-line border-b px-3 py-2 text-right font-medium">Price</th>
                    <th scope="col" class="border-line border-b py-2 pl-3 text-right font-medium">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-line divide-y">
                @foreach ($quote->items as $item)
                    <tr>
                        <td class="py-3 pr-3">{{ $item->description }}</td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">{{ $item->quantityLabel() }}</td>
                        <td class="px-3 py-3 text-right whitespace-nowrap">{{ $money($item->unit_price_cents) }}</td>
                        <td class="py-3 pl-3 text-right whitespace-nowrap">{{ $money($item->line_total_cents) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <dl class="border-line mt-2 ml-auto max-w-xs space-y-2 border-t pt-4">
        <div class="flex justify-between gap-4"><dt class="text-muted">Subtotal</dt><dd>{{ $money($quote->subtotal_cents) }}</dd></div>
        @if ($quote->discount_cents > 0)
            <div class="flex justify-between gap-4"><dt class="text-muted">Discount</dt><dd>-{{ $money($quote->discount_cents) }}</dd></div>
        @endif
        @if ($quote->vat_rate > 0)
            <div class="flex justify-between gap-4"><dt class="text-muted">VAT ({{ rtrim(rtrim(number_format($quote->vat_rate, 2), '0'), '.') }}%)</dt><dd>{{ $money($quote->vat_cents) }}</dd></div>
        @endif
        <div class="flex justify-between gap-4 text-lg font-semibold"><dt>Total</dt><dd>{{ $money($quote->total_cents) }}</dd></div>
        @if ($quote->deposit_percent > 0)
            <div class="flex justify-between gap-4"><dt class="text-muted">Deposit to start ({{ rtrim(rtrim(number_format($quote->deposit_percent, 2), '0'), '.') }}%)</dt><dd>{{ $money($quote->depositCents()) }}</dd></div>
        @endif
    </dl>
</section>

@if ($quote->timeline || $quote->terms)
    <section class="card space-y-4">
        @if ($quote->timeline)
            <div>
                <h2 class="h-section">Estimated timeline</h2>
                <p class="mt-2">{{ $quote->timeline }}</p>
                <p class="text-muted mt-1 text-sm">An estimate that depends on feedback and content arriving on time.</p>
            </div>
        @endif
        @if ($quote->terms)
            <div>
                <h2 class="h-section">Terms</h2>
                <p class="mt-2 whitespace-pre-line">{{ $quote->terms }}</p>
            </div>
        @endif
    </section>
@endif
