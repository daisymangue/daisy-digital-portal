@php
    $plain = fn ($cents) => number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, '.', '');
    [$payKey, $payLabel] = $plan->paymentState();
    $owing = $bills->filter(fn ($b) => $b->isOpen())->sum(fn ($b) => $b->balanceCents());
@endphp
<x-layouts.app :title="$plan->name.' · '.$plan->client->business_name">
    <a href="{{ route('admin.care-plans.index') }}" class="link inline-flex min-h-11 items-center text-sm">Back to subscriptions</a>

    <div class="mt-2 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="h-page">{{ $plan->name }}</h1>
            <p class="text-muted mt-1"><a href="{{ route('admin.clients.show', $plan->client) }}" class="link">{{ $plan->client->business_name }}</a> · {{ $plan->reference }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-status :status="$plan->status === 'active' ? 'active' : ($plan->status === 'paused' ? 'on_hold' : 'cancelled')" :label="$plan->statusLabel()" />
            <x-status :status="$payKey" :label="$payLabel" />
        </div>
    </div>

    <dl class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="card py-4">
            <dt class="text-muted text-sm">Price</dt>
            <dd class="mt-1 text-xl font-semibold">{{ \App\Support\Money::format($plan->amount_cents) }} <span class="text-muted text-sm font-normal">{{ $plan->perLabel() }}</span></dd>
            <dd class="text-muted text-xs">{{ \App\Support\Money::format($plan->vatInclusiveCents()) }} incl. VAT</dd>
        </div>
        <div class="card py-4">
            <dt class="text-muted text-sm">Next bill</dt>
            <dd class="mt-1 text-xl font-semibold">{{ $plan->status === 'active' ? $plan->next_invoice_on->format('j M Y') : ($plan->status === 'paused' ? 'Paused' : 'Ended') }}</dd>
            <dd class="text-muted text-xs">{{ $plan->auto_issue ? 'Sent automatically' : 'Draft for you to check' }}</dd>
        </div>
        <div class="card py-4">
            <dt class="text-muted text-sm">Owing now</dt>
            <dd class="mt-1 text-xl font-semibold {{ $payKey === 'overdue' ? 'text-red-800' : '' }}">{{ \App\Support\Money::format($owing) }}</dd>
            <dd class="text-muted text-xs">{{ trans_choice(':count bill so far|:count bills so far', $bills->count()) }}</dd>
        </div>
        <div class="card py-4">
            <dt class="text-muted text-sm">Payment reference</dt>
            <dd class="mt-1 text-xl font-semibold">{{ $plan->reference }}</dd>
            <dd class="text-muted text-xs">for a repeat EFT or stop order</dd>
        </div>
    </dl>

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-5">
        <section class="min-w-0 lg:col-span-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="h-section">Bills</h2>
                @if ($plan->status === 'active' && $plan->next_invoice_on->lte(today()))
                    <form method="POST" action="{{ route('admin.care-plans.bill', $plan) }}">@csrf<button class="btn btn-dark">Bill now</button></form>
                @endif
            </div>
            <ul class="card divide-line mt-4 divide-y p-0 sm:p-0">
                @forelse ($bills as $bill)
                    <li>
                        <a href="{{ route('admin.invoices.show', $bill) }}" class="hover:bg-sand/60 flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <span class="min-w-0">
                                <span class="font-medium">{{ $bill->reference() }}</span>
                                <span class="text-muted block text-sm">{{ $plan->periodLabel($bill->period_start) }}</span>
                            </span>
                            <span class="flex items-center gap-3">
                                <span class="text-sm whitespace-nowrap">{{ \App\Support\Money::format($bill->total_cents) }}</span>
                                <x-status :status="$bill->effectiveStatus()" :label="$bill->statusLabel()" />
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="text-muted px-5 py-6 text-center">No bills yet. The first one goes out on {{ $plan->next_invoice_on->format('j M Y') }}.</li>
                @endforelse
            </ul>

            @if ($plan->includedItems())
                <h2 class="h-section mt-8">What is included</h2>
                <ul class="card mt-4 space-y-2">
                    @foreach ($plan->includedItems() as $item)
                        <li class="flex gap-2"><svg class="text-brand mt-1 size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>{{ $item }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        <x-fold title="Edit subscription" summary="Price, dates, pause or end" :open="$errors->any()" class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.care-plans.update', $plan) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="name" class="label">Plan name</label>
                    <input id="name" name="name" required maxlength="160" value="{{ old('name', $plan->name) }}" class="field">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="amount" class="label">Price excl. VAT (R)</label>
                        <input id="amount" name="amount" inputmode="decimal" required value="{{ old('amount', $plain($plan->amount_cents)) }}" class="field">
                    </div>
                    <div>
                        <label for="interval" class="label">Billed</label>
                        <select id="interval" name="interval" class="field">
                            @foreach (\App\Models\CarePlan::INTERVALS as $k => $l)
                                <option value="{{ $k }}" @selected(old('interval', $plan->interval) === $k)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="next_invoice_on" class="label">Next bill on</label>
                        <input id="next_invoice_on" name="next_invoice_on" type="date" required value="{{ old('next_invoice_on', $plan->next_invoice_on->format('Y-m-d')) }}" class="field">
                    </div>
                    <div>
                        <label for="ends_on" class="label">Ends on <span class="text-muted font-normal">(optional)</span></label>
                        <input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on', $plan->ends_on?->format('Y-m-d')) }}" class="field">
                    </div>
                </div>
                <div>
                    <label for="description" class="label">What is included <span class="text-muted font-normal">(one per line)</span></label>
                    <textarea id="description" name="description" rows="4" maxlength="1000" class="field">{{ old('description', $plan->description) }}</textarea>
                </div>
                <div>
                    <label for="status" class="label">Status</label>
                    <select id="status" name="status" class="field">
                        @foreach (\App\Models\CarePlan::STATUSES as $k => $l)
                            <option value="{{ $k }}" @selected(old('status', $plan->status) === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                    <p class="text-muted mt-1 text-sm">Paused plans skip billing until you set them active again. Cancelled plans stop for good.</p>
                </div>
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="auto_issue" value="1" @checked(old('auto_issue', $plan->auto_issue)) class="accent-navy mt-0.5 size-5 shrink-0">
                    <span class="text-sm"><span class="font-medium">Send bills automatically</span><br><span class="text-muted">Untick to get a draft each period that you check and send yourself.</span></span>
                </label>
                <button type="submit" class="btn btn-primary">Save subscription</button>
            </form>
        </x-fold>
    </div>
</x-layouts.app>
