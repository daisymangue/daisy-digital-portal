@php
    $plain = fn ($cents) => number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, '.', '');
    $menu = $templates->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'amount' => $plain($t->amount_cents), 'interval' => $t->interval, 'description' => (string) $t->description])->values();
    $current = $plans->where('status', '!=', 'cancelled');
    $ended = $plans->where('status', 'cancelled');
@endphp
<x-layouts.app title="Billing · Subscriptions">
    @include('admin.billing.header', ['tab' => 'plans'])

    <div class="mt-6 grid items-start gap-6 lg:grid-cols-5">
        <div class="min-w-0 lg:col-span-3">
            @if ($current->isEmpty())
                <div class="card text-center">
                    <p class="font-medium">No subscriptions yet.</p>
                    <p class="text-muted mt-1">Add one and the bills go out by themselves every period.</p>
                </div>
            @else
                <ul class="card divide-line divide-y p-0 sm:p-0">
                    @foreach ($current as $plan)
                        @php [$payKey, $payLabel] = $plan->paymentState(); @endphp
                        <li>
                            <a href="{{ route('admin.care-plans.show', $plan) }}" class="hover:bg-sand/60 flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4">
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold">{{ $plan->client->business_name }}</p>
                                    <p class="text-muted text-sm">{{ $plan->name }} · {{ $plan->reference }}</p>
                                </div>
                                <div class="text-sm sm:w-36">
                                    <p class="font-medium whitespace-nowrap">{{ \App\Support\Money::format($plan->amount_cents) }} {{ $plan->perLabel() }}</p>
                                    <p class="text-muted">{{ $plan->status === 'active' ? 'Next bill '.$plan->next_invoice_on->format('j M') : 'Paused' }}</p>
                                </div>
                                <div class="flex w-28 justify-end">
                                    @if ($plan->status === 'paused' && $payKey === 'paid')
                                        <x-status status="on_hold" label="Paused" />
                                    @else
                                        <x-status :status="$payKey" :label="$payLabel" />
                                    @endif
                                </div>
                                <svg class="text-muted hidden size-5 sm:block" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M7.2 14.8a.75.75 0 0 1 0-1.06L10.94 10 7.2 6.26a.75.75 0 1 1 1.06-1.06l4.27 4.27a.75.75 0 0 1 0 1.06l-4.27 4.27a.75.75 0 0 1-1.06 0Z" clip-rule="evenodd"/></svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($ended->isNotEmpty())
                <details class="mt-4">
                    <summary class="text-muted min-h-11 cursor-pointer py-2 text-sm">{{ trans_choice(':count ended subscription|:count ended subscriptions', $ended->count()) }}</summary>
                    <ul class="card divide-line mt-2 divide-y p-0 opacity-70 sm:p-0">
                        @foreach ($ended as $plan)
                            <li><a href="{{ route('admin.care-plans.show', $plan) }}" class="flex justify-between gap-3 px-5 py-3 text-sm"><span>{{ $plan->client->business_name }} · {{ $plan->name }}</span><span class="text-muted">Ended</span></a></li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>

        <x-fold title="Add a subscription" summary="Pick from your plan menu, then adjust" :open="$current->isEmpty() || $errors->any()" class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.care-plans.store') }}" class="space-y-4"
                  x-data="{ menu: @js($menu), pick: '', name: @js(old('name', '')), amount: @js(old('amount', '')), interval: @js(old('interval', 'monthly')), description: @js(old('description', '')),
                            use() { const t = this.menu.find(m => String(m.id) === this.pick); if (t) { this.name = t.name; this.amount = t.amount; this.interval = t.interval; this.description = t.description } } }">
                @csrf
                <div>
                    <label for="client_id" class="label">Client</label>
                    <select id="client_id" name="client_id" required class="field">
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected(old('client_id') === $c->id)>{{ $c->business_name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($templates->isNotEmpty())
                    <div>
                        <label for="template" class="label">Start from</label>
                        <select id="template" x-model="pick" @change="use()" class="field">
                            <option value="">Blank, I will fill it in</option>
                            @foreach ($templates as $t)
                                <option value="{{ $t->id }}">{{ $t->name }} · {{ \App\Support\Money::format($t->amount_cents) }} {{ strtolower($t->intervalLabel()) }}</option>
                            @endforeach
                        </select>
                        <p class="text-muted mt-1 text-sm">Fills in the details below. You can still change them for this client. <a href="{{ route('admin.plan-templates.index') }}" class="link">Edit the menu</a></p>
                    </div>
                @endif
                <div>
                    <label for="name" class="label">Plan name</label>
                    <input id="name" name="name" required maxlength="160" x-model="name" class="field" placeholder="For example Website care">
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="amount" class="label">Price excl. VAT (R)</label>
                        <input id="amount" name="amount" inputmode="decimal" required x-model="amount" class="field" placeholder="950">
                    </div>
                    <div>
                        <label for="interval" class="label">Billed</label>
                        <select id="interval" name="interval" x-model="interval" class="field">
                            @foreach (\App\Models\CarePlan::INTERVALS as $k => $l)
                                <option value="{{ $k }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="next_invoice_on" class="label">First bill on</label>
                        <input id="next_invoice_on" name="next_invoice_on" type="date" required value="{{ old('next_invoice_on', today()->addMonthNoOverflow()->startOfMonth()->format('Y-m-d')) }}" class="field">
                    </div>
                    <div>
                        <label for="ends_on" class="label">Ends on <span class="text-muted font-normal">(optional)</span></label>
                        <input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on') }}" class="field">
                    </div>
                </div>
                <div>
                    <label for="description" class="label">What is included <span class="text-muted font-normal">(one per line)</span></label>
                    <textarea id="description" name="description" rows="4" maxlength="1000" x-model="description" class="field" placeholder="Hosting and SSL&#10;Daily backups&#10;Up to 3 small changes a month"></textarea>
                    <p class="text-muted mt-1 text-sm">The client sees this list, and it is printed on each bill.</p>
                </div>
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="auto_issue" value="1" @checked(old('auto_issue', true)) class="accent-navy mt-0.5 size-5 shrink-0">
                    <span class="text-sm"><span class="font-medium">Send bills automatically</span><br><span class="text-muted">Untick to get a draft each period that you check and send yourself.</span></span>
                </label>
                <button type="submit" class="btn btn-primary">Add subscription</button>
            </form>
        </x-fold>
    </div>
</x-layouts.app>
