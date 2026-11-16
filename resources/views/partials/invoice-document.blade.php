{{-- The invoice itself, shared by the client and admin pages. --}}
@php
    $money = fn ($cents) => \App\Support\Money::format($cents);
@endphp
<section class="card">
    <h2 class="h-section">Invoice details</h2>
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
                @foreach ($invoice->items as $item)
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
        <div class="flex justify-between gap-4"><dt class="text-muted">Subtotal</dt><dd>{{ $money($invoice->subtotal_cents) }}</dd></div>
        @if ($invoice->discount_cents > 0)
            <div class="flex justify-between gap-4"><dt class="text-muted">Discount</dt><dd>-{{ $money($invoice->discount_cents) }}</dd></div>
        @endif
        @if ($invoice->vat_cents > 0)
            <div class="flex justify-between gap-4"><dt class="text-muted">VAT ({{ rtrim(rtrim(number_format($invoice->vat_rate, 2), '0'), '.') }}%)</dt><dd>{{ $money($invoice->vat_cents) }}</dd></div>
        @endif
        <div class="flex justify-between gap-4 font-semibold"><dt>Total</dt><dd>{{ $money($invoice->total_cents) }}</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-muted">Paid</dt><dd>{{ $money($invoice->paid_cents) }}</dd></div>
        <div class="border-line flex justify-between gap-4 border-t pt-2 text-lg font-semibold"><dt>Balance due</dt><dd>{{ $money($invoice->balanceCents()) }}</dd></div>
    </dl>

    @if ($invoice->notes)
        <p class="border-line mt-4 border-t pt-4 whitespace-pre-line">{{ $invoice->notes }}</p>
    @endif
</section>
