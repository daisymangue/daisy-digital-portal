@php
    $plain = fn ($cents) => number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, '.', '');
    $form = $editing;
@endphp
<x-layouts.app title="Care plans">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="h-page">Care plans</h1>
            <p class="text-muted mt-1">Repeat work billed on a schedule, like hosting, website care or social media management.</p>
        </div>
        <p class="text-muted">Monthly recurring: <span class="text-ink text-xl font-semibold">{{ \App\Support\Money::format($monthly) }}</span> <span class="text-sm">excl. VAT</span></p>
    </div>

    <div class="mt-8 grid items-start gap-6 lg:grid-cols-5">
        <div class="min-w-0 space-y-3 lg:col-span-3">
            @forelse ($plans as $plan)
                <div class="card {{ $plan->status === 'cancelled' ? 'opacity-60' : '' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $plan->name }}</p>
                            <p class="text-muted text-sm">
                                <a href="{{ route('admin.clients.show', $plan->client) }}" class="link">{{ $plan->client->business_name }}</a>
                                · {{ \App\Support\Money::format($plan->amount_cents) }} excl. VAT, {{ strtolower($plan->intervalLabel()) }}
                            </p>
                        </div>
                        <x-status :status="$plan->status === 'active' ? 'active' : ($plan->status === 'paused' ? 'on_hold' : 'cancelled')" :label="$plan->statusLabel()" />
                    </div>
                    <div class="mt-3 flex flex-wrap items-center justify-between gap-3 text-sm">
                        <p class="text-muted">
                            @if ($plan->status === 'active') Next invoice {{ $plan->next_invoice_on->format('j M Y') }} · @endif
                            {{ $plan->auto_issue ? 'Sent automatically' : 'Drafts for you to check' }}
                            · {{ trans_choice(':count invoice so far|:count invoices so far', $plan->invoices_count) }}
                            @if ($plan->ends_on) · ends {{ $plan->ends_on->format('j M Y') }} @endif
                        </p>
                        <div class="flex gap-3">
                            @if ($plan->status === 'active' && $plan->next_invoice_on->lte(today()))
                                <form method="POST" action="{{ route('admin.care-plans.bill', $plan) }}">@csrf<button class="link min-h-11 cursor-pointer">Bill now</button></form>
                            @endif
                            <a href="{{ route('admin.care-plans.index', ['edit' => $plan->id]) }}" class="link flex min-h-11 items-center">Edit</a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="card text-center">
                    <p class="font-medium">No care plans yet.</p>
                    <p class="text-muted mt-1">Add one and the invoices go out by themselves every month.</p>
                </div>
            @endforelse
        </div>

        <x-fold :title="$form ? 'Edit care plan' : 'Add a care plan'" :summary="$form ? $form->name : 'Hosting, website care, social media'" :open="$form || $plans->isEmpty() || $errors->any()" class="lg:col-span-2">
        <form method="POST" action="{{ $form ? route('admin.care-plans.update', $form) : route('admin.care-plans.store') }}" class="space-y-4">
            @csrf
            @if ($form) @method('PUT') @endif
            @unless ($form)
                <div>
                    <label for="client_id" class="label">Client</label>
                    <select id="client_id" name="client_id" required class="field">
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected(old('client_id') === $c->id)>{{ $c->business_name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <p class="text-muted text-sm">For {{ $form->client->business_name }}</p>
            @endunless
            <div>
                <label for="name" class="label">Plan name</label>
                <input id="name" name="name" required maxlength="160" value="{{ old('name', $form?->name) }}" class="field" placeholder="For example Website care, Basic">
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="amount" class="label">Price excl. VAT (R)</label>
                    <input id="amount" name="amount" inputmode="decimal" required value="{{ old('amount', $form ? $plain($form->amount_cents) : '') }}" class="field" placeholder="950">
                </div>
                <div>
                    <label for="interval" class="label">Billed</label>
                    <select id="interval" name="interval" class="field">
                        @foreach (\App\Models\CarePlan::INTERVALS as $k => $l)
                            <option value="{{ $k }}" @selected(old('interval', $form?->interval ?? 'monthly') === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="next_invoice_on" class="label">{{ $form ? 'Next invoice on' : 'First invoice on' }}</label>
                    <input id="next_invoice_on" name="next_invoice_on" type="date" required value="{{ old('next_invoice_on', $form?->next_invoice_on->format('Y-m-d') ?? today()->addMonthNoOverflow()->startOfMonth()->format('Y-m-d')) }}" class="field">
                </div>
                <div>
                    <label for="ends_on" class="label">Ends on <span class="text-muted font-normal">(optional)</span></label>
                    <input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on', $form?->ends_on?->format('Y-m-d')) }}" class="field">
                </div>
            </div>
            <div>
                <label for="description" class="label">What is included <span class="text-muted font-normal">(printed on each invoice)</span></label>
                <textarea id="description" name="description" rows="3" maxlength="1000" class="field" placeholder="Hosting, updates, backups and up to 1 hour of changes a month">{{ old('description', $form?->description) }}</textarea>
            </div>
            @if ($form)
                <div>
                    <label for="status" class="label">Status</label>
                    <select id="status" name="status" class="field">
                        @foreach (\App\Models\CarePlan::STATUSES as $k => $l)
                            <option value="{{ $k }}" @selected(old('status', $form->status) === $k)>{{ $l }}</option>
                        @endforeach
                    </select>
                    <p class="text-muted mt-1 text-sm">Paused plans skip billing until you set them active again.</p>
                </div>
            @endif
            <label class="flex items-start gap-3">
                <input type="checkbox" name="auto_issue" value="1" @checked(old('auto_issue', $form?->auto_issue ?? true)) class="accent-navy mt-0.5 size-5 shrink-0">
                <span class="text-sm"><span class="font-medium">Send invoices automatically</span><br><span class="text-muted">Untick to get a draft each period that you check and issue yourself.</span></span>
            </label>
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary">{{ $form ? 'Save plan' : 'Add care plan' }}</button>
                @if ($form)<a href="{{ route('admin.care-plans.index') }}" class="btn btn-light">Cancel</a>@endif
            </div>
        </form>
        </x-fold>
    </div>
</x-layouts.app>
