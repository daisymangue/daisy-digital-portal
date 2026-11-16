@php
    $money = fn ($cents) => \App\Support\Money::format($cents);
    $pct = fn ($v) => rtrim(rtrim(number_format($v, 2), '0'), '.');
    $client = $quote->client;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Quote {{ $quote->reference() }}</title>
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
                <div class="brand">{{ strtoupper($settings['business_name']) }}</div>
                <div class="muted pre">{{ $settings['business_address'] }}</div>
                <div class="muted">{{ collect([$settings['business_email'], $settings['business_phone']])->filter()->implode(' · ') }}</div>
            </td>
            <td class="right">
                <h1>QUOTE</h1>
                <div>{{ $quote->reference() }}</div>
                <div class="muted">Date: {{ ($quote->sent_at ?? $quote->created_at)->format('j F Y') }}</div>
                @if ($quote->valid_until)<div class="muted">Valid until: {{ $quote->valid_until->format('j F Y') }}</div>@endif
                @if ($quote->isDraft())<div style="color:#a3271b; font-weight:bold;">DRAFT</div>@endif
            </td>
        </tr>
    </table>
    <div class="bar"></div>

    <table class="head">
        <tr>
            <td style="width: 50%">
                <div class="muted">Prepared for</div>
                <div style="font-weight: bold">{{ $quote->client->business_name }}</div>
                @if ($client->contactName())<div>{{ $client->contactName() }}</div>@endif
                @if ($client->contactEmail())<div class="muted">{{ $client->contactEmail() }}</div>@endif
                @if ($client->address)<div class="muted pre">{{ $client->address }}</div>@endif
            </td>
            <td>
                <div class="muted">Project</div>
                <div style="font-weight: bold">{{ $quote->title }}</div>
                @if ($quote->timeline)<div class="muted">Estimated timeline: {{ $quote->timeline }}</div>@endif
            </td>
        </tr>
    </table>

    @if ($quote->scope)
        <h2>Scope and deliverables</h2>
        <div class="pre">{{ $quote->scope }}</div>
    @endif

    <h2>Items</h2>
    <table class="items">
        <thead>
            <tr><th>Description</th><th class="right" style="width: 16%">Qty</th><th class="right" style="width: 17%">Price</th><th class="right" style="width: 17%">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($quote->items as $item)
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
        <tr><td class="muted">Subtotal</td><td class="right">{{ $money($quote->subtotal_cents) }}</td></tr>
        @if ($quote->discount_cents > 0)
            <tr><td class="muted">Discount</td><td class="right">-{{ $money($quote->discount_cents) }}</td></tr>
        @endif
        @if ($quote->vat_rate > 0)
            <tr><td class="muted">VAT ({{ $pct($quote->vat_rate) }}%)</td><td class="right">{{ $money($quote->vat_cents) }}</td></tr>
        @endif
        <tr class="grand"><td>Total</td><td class="right">{{ $money($quote->total_cents) }}</td></tr>
        @if ($quote->deposit_percent > 0)
            <tr><td class="muted">Deposit to start ({{ $pct($quote->deposit_percent) }}%)</td><td class="right">{{ $money($quote->depositCents()) }}</td></tr>
        @endif
    </table>

    @if ($quote->terms)
        <h2>Terms</h2>
        <div class="pre">{{ $quote->terms }}</div>
    @endif

    @if ($quote->acceptance)
        <div class="box">
            <strong>Accepted</strong> by {{ $quote->acceptance->accepted_name }} on {{ $quote->acceptance->accepted_at->format('j F Y \a\t H:i') }}
            {{ $quote->acceptance->isRecordedByTeam() ? '('.$quote->acceptance->methodLabel().', recorded by '.$settings['business_name'].').' : 'through the client portal.' }}
        </div>
    @endif
</body>
</html>
