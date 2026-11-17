@php
    $money = fn ($cents) => \App\Support\Money::format($cents);
    $pct = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
    $client = $invoice->client;
    $isTax = $invoice->vat_cents > 0 && trim($settings['vat_number']) !== '';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->reference() }}</title>
    <style>
        @page { margin: 40px 44px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1b1d33; line-height: 1.5; }
        h1 { font-size: 22px; margin: 0; color: #12142b; }
        h2 { font-size: 12px; margin: 22px 0 6px; color: #12142b; text-transform: uppercase; letter-spacing: 1px; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; }
        .brand { font-size: 16px; font-weight: bold; letter-spacing: 2px; color: #12142b; }
        .muted { color: #565a70; }
        .right { text-align: right; }
        .bar { height: 4px; background: #db1f63; margin: 14px 0 20px; }
        .items th { text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: #565a70; border-bottom: 1px solid #c9c5bb; padding: 6px 4px; }
        .items th.right { text-align: right; }
        .items td { border-bottom: 1px solid #e9e6df; padding: 8px 4px; vertical-align: top; }
        .totals { width: 46%; margin-left: 54%; margin-top: 10px; }
        .totals td { padding: 4px; }
        .totals .grand td { border-top: 1px solid #12142b; font-size: 14px; font-weight: bold; padding-top: 8px; }
        .box { border: 1px solid #e9e6df; padding: 10px 12px; margin-top: 18px; }
        .pre { white-space: pre-line; }
        .foot { position: fixed; bottom: -10px; left: 0; right: 0; font-size: 9px; color: #565a70; text-align: center; }
    </style>
</head>
<body>
    <div class="foot">
        {{ collect([
            $settings['business_name'],
            $settings['registration_number'] ? 'Reg '.$settings['registration_number'] : null,
            $settings['vat_number'] ? 'VAT '.$settings['vat_number'] : null,
            $settings['business_website'],
        ])->filter()->implode(' · ') }}
    </div>

    <table class="head">
        <tr>
            <td>
                @php
                    $logo = \App\Support\Brand::logoDataUri();
                @endphp
                @if ($logo)
                    <img src="{{ $logo }}" alt="{{ $settings['business_name'] }}" style="height: 44px; margin-bottom: 8px">
                @else
                    <div class="brand">{{ strtoupper($settings['business_name']) }}</div>
                @endif
                <div class="muted pre">{{ $settings['business_address'] }}</div>
                <div class="muted">{{ collect([$settings['business_email'], $settings['business_phone']])->filter()->implode(' · ') }}</div>
                @if ($settings['vat_number'])<div class="muted">VAT number: {{ $settings['vat_number'] }}</div>@endif
            </td>
            <td class="right">
                <h1>{{ $isTax ? 'TAX INVOICE' : 'INVOICE' }}</h1>
                <div>{{ $invoice->reference() }}</div>
                @if ($invoice->issue_date)<div class="muted">Date: {{ $invoice->issue_date->format('j F Y') }}</div>@endif
                @if ($invoice->due_date)<div class="muted">Due: {{ $invoice->due_date->format('j F Y') }}</div>@endif
                @if ($invoice->isDraft())<div style="color:#a3271b; font-weight:bold;">DRAFT</div>@endif
                @if ($invoice->status === 'paid')<div style="color:#1f6b3a; font-weight:bold;">PAID</div>@endif
                @if ($invoice->status === 'cancelled')<div style="color:#a3271b; font-weight:bold;">CANCELLED</div>@endif
            </td>
        </tr>
    </table>
    <div class="bar"></div>

    <table class="head">
        <tr>
            <td style="width: 50%">
                <div class="muted">Billed to</div>
                <div style="font-weight: bold">{{ $invoice->client->business_name }}</div>
                @if ($client->contactName())<div>{{ $client->contactName() }}</div>@endif
                @if ($client->contactEmail())<div class="muted">{{ $client->contactEmail() }}</div>@endif
                @if ($client->address)<div class="muted pre">{{ $client->address }}</div>@endif
            </td>
            <td>
                <div class="muted">For</div>
                <div style="font-weight: bold">{{ $invoice->title }}</div>
                @if ($invoice->quote)<div class="muted">Quote {{ $invoice->quote->reference() }}</div>@endif
            </td>
        </tr>
    </table>

    <h2>Items</h2>
    <table class="items">
        <thead>
            <tr><th>Description</th><th class="right" style="width: 16%">Qty</th><th class="right" style="width: 17%">Price</th><th class="right" style="width: 17%">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ $item->quantityLabel() }}</td>
                    <td class="right">{{ $money($item->unit_price_cents) }}</td>
                    <td class="right">{{ $money($item->line_total_cents) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">Subtotal</td><td class="right">{{ $money($invoice->subtotal_cents) }}</td></tr>
        @if ($invoice->discount_cents > 0)
            <tr><td class="muted">Discount</td><td class="right">-{{ $money($invoice->discount_cents) }}</td></tr>
        @endif
        @if ($invoice->vat_cents > 0)
            <tr><td class="muted">VAT ({{ $pct($invoice->vat_rate) }}%)</td><td class="right">{{ $money($invoice->vat_cents) }}</td></tr>
        @endif
        <tr><td>Total</td><td class="right">{{ $money($invoice->total_cents) }}</td></tr>
        @if ($invoice->paid_cents > 0)
            <tr><td class="muted">Paid</td><td class="right">-{{ $money($invoice->paid_cents) }}</td></tr>
        @endif
        <tr class="grand"><td>Balance due</td><td class="right">{{ $money($invoice->balanceCents()) }}</td></tr>
    </table>

    @if ($invoice->notes)
        <h2>Note</h2>
        <div class="pre">{{ $invoice->notes }}</div>
    @endif

    @if ($invoice->isOpen() || $invoice->isDraft())
        <div class="box">
            <strong>How to pay</strong>
            @if (trim($settings['bank_details']) !== '')
                <div class="pre">{{ $settings['bank_details'] }}</div>
            @else
                <div>Please contact us for our bank details.</div>
            @endif
            @if ($invoice->number)<div>Payment reference: <strong>{{ $invoice->number }}</strong></div>@endif
        </div>
    @endif
</body>
</html>
