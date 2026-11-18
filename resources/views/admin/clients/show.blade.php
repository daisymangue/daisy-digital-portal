@php
    $money = fn ($cents) => \App\Support\Money::format($cents);
    $owing = $invoices->filter(fn ($i) => $i->isOpen())->sum(fn ($i) => $i->balanceCents());
    $login = $client->users->first();
    $tabs = [
        'requests' => ['Requests', $requests->count(), 'Requests'],
        'quotes' => ['Quotes', $quotes->count(), 'Quotes'],
        'invoices' => ['Invoices', $invoices->count(), 'Invoices'],
        'work' => ['Projects and care plans', $projects->count() + $plans->count(), 'Projects'],
        'details' => ['Details and records', 0, 'Details'],
    ];
    $default = $errors->any() ? 'details' : 'requests';
@endphp
<x-layouts.app :title="$client->business_name">
    <a href="{{ route('admin.clients.index') }}" class="link text-sm">Back to clients</a>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="h-page">{{ $client->business_name }}</h1>
            <p class="text-muted mt-1">Client since {{ $client->created_at->format('j F Y') }}</p>
        </div>
        @if ($client->isArchived())
            <span class="pill bg-sand-dark text-ink">Archived</span>
        @endif
        <span class="pill {{ $client->users->isNotEmpty() ? 'bg-green-100 text-green-900' : 'bg-sand-dark text-muted' }}">{{ $client->users->isNotEmpty() ? 'Has portal access' : 'No portal login' }}</span>
    </div>

    @if ($client->isArchived())
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-950">
            <p>Archived {{ $client->archived_at->format('j M Y') }}@if ($client->archived_reason): {{ $client->archived_reason }}@endif. They cannot sign in.</p>
            <form method="POST" action="{{ route('admin.clients.restore', $client) }}">@csrf<button class="btn btn-dark">Restore client</button></form>
        </div>
    @endif

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('admin.requests.create', ['client' => $client->id]) }}" class="btn btn-primary">New request</a>
        <form method="POST" action="{{ route('admin.quotes.store') }}">
            @csrf
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <button type="submit" class="btn btn-light">New quote</button>
        </form>
        <form method="POST" action="{{ route('admin.invoices.store') }}">
            @csrf
            <input type="hidden" name="client_id" value="{{ $client->id }}">
            <button type="submit" class="btn btn-light">New invoice</button>
        </form>
    </div>

    <section class="card mt-6 grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="min-w-0">
            <p class="text-muted text-sm">Contact</p>
            <p class="truncate font-medium">{{ $client->contactName() ?: 'Not added' }}</p>
        </div>
        <div class="min-w-0">
            <p class="text-muted text-sm">Phone or WhatsApp</p>
            @if ($client->phone)
                <p><a href="tel:{{ preg_replace('/[^0-9+]/', '', $client->phone) }}" class="link">{{ $client->phone }}</a></p>
            @else
                <p class="text-muted">Not added</p>
            @endif
        </div>
        <div class="min-w-0">
            <p class="text-muted text-sm">Email</p>
            @if ($client->contactEmail())
                <p class="truncate"><a href="mailto:{{ $client->contactEmail() }}" class="link">{{ $client->contactEmail() }}</a></p>
            @else
                <p class="text-muted">Not added</p>
            @endif
        </div>
        <div class="min-w-0">
            <p class="text-muted text-sm">Owing</p>
            <p class="font-semibold {{ $owing > 0 ? 'text-red-800' : '' }}">{{ $money($owing) }}</p>
        </div>
    </section>

    <div x-data="{
            tab: '{{ $default }}',
            keys: @js(array_keys($tabs)),
            show(k) {
                this.tab = k;
                try { localStorage.setItem('ctab-{{ $client->id }}', k) } catch (e) {}
                history.replaceState(null, '', '#' + k);
            },
            move(d) {
                const i = (this.keys.indexOf(this.tab) + d + this.keys.length) % this.keys.length;
                this.show(this.keys[i]);
                this.$nextTick(() => document.getElementById('tab-' + this.tab).focus());
            },
            init() {
                if ({{ $errors->any() ? 'true' : 'false' }}) return;
                let saved = null;
                try { saved = localStorage.getItem('ctab-{{ $client->id }}') } catch (e) {}
                const h = location.hash.slice(1);
                if (this.keys.includes(h)) this.tab = h; else if (saved && this.keys.includes(saved)) this.tab = saved;
            },
        }">
        @include('partials.project-tabs')

        <div class="mt-6">
            <div id="panel-requests" role="tabpanel" aria-labelledby="tab-requests" x-show="tab === 'requests'" class="space-y-3">
                @forelse ($requests as $r)
                    <a href="{{ route('admin.requests.show', $r) }}" class="card hover:border-line-strong flex flex-wrap items-center justify-between gap-3 py-4 sm:py-4">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $r->number }}</p>
                            <p class="text-muted text-sm">{{ $r->package_name ?: ($r->isSpecial() ? 'Special request' : trans_choice(':count service|:count services', $r->services_count)) }} · {{ $r->submitted_at->format('j M Y') }} · {{ $r->sourceLabel() }}</p>
                        </div>
                        <x-status :status="$r->status" :label="$r->statusLabel()" />
                    </a>
                @empty
                    <div class="card text-center">
                        <p class="text-muted">No requests yet.</p>
                        <a href="{{ route('admin.requests.create', ['client' => $client->id]) }}" class="btn btn-primary mt-3">Log their first request</a>
                    </div>
                @endforelse
            </div>

            <div id="panel-quotes" role="tabpanel" aria-labelledby="tab-quotes" x-show="tab === 'quotes'" x-cloak class="space-y-3">
                @forelse ($quotes as $q)
                    <a href="{{ $q->isDraft() ? route('admin.quotes.edit', $q) : route('admin.quotes.show', $q) }}" class="card hover:border-line-strong flex flex-wrap items-center justify-between gap-3 py-4 sm:py-4">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $q->reference() }} · {{ $q->title }}</p>
                            <p class="text-muted text-sm">{{ $money($q->total_cents) }}</p>
                        </div>
                        <x-status :status="$q->effectiveStatus()" :label="$q->statusLabel()" />
                    </a>
                @empty
                    <div class="card text-muted text-center">No quotes yet. Quotes are usually made from a request.</div>
                @endforelse
            </div>

            <div id="panel-invoices" role="tabpanel" aria-labelledby="tab-invoices" x-show="tab === 'invoices'" x-cloak class="space-y-3">
                @forelse ($invoices as $inv)
                    <a href="{{ route('admin.invoices.show', $inv) }}" class="card hover:border-line-strong flex flex-wrap items-center justify-between gap-3 py-4 sm:py-4">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $inv->reference() }} · {{ $inv->title }}</p>
                            <p class="text-muted text-sm">{{ $money($inv->total_cents) }} · Balance {{ $money($inv->balanceCents()) }}</p>
                        </div>
                        <x-status :status="$inv->effectiveStatus()" :label="$inv->statusLabel()" />
                    </a>
                @empty
                    <div class="card text-muted text-center">No invoices yet.</div>
                @endforelse
            </div>

            <div id="panel-work" role="tabpanel" aria-labelledby="tab-work" x-show="tab === 'work'" x-cloak class="grid items-start gap-6 lg:grid-cols-2">
                <section class="card">
                    <h2 class="h-section">Projects</h2>
                    @forelse ($projects as $p)
                        <a href="{{ route('admin.projects.show', $p) }}" class="border-line mt-3 flex items-center justify-between gap-3 border-t pt-3 first-of-type:border-0 first-of-type:pt-0">
                            <span class="link min-w-0 truncate">{{ $p->number }} · {{ $p->title }}</span>
                            <x-status :status="$p->status" :label="$p->statusLabel()" />
                        </a>
                    @empty
                        <p class="text-muted mt-2 text-sm">None yet. A project opens when a deposit is paid.</p>
                    @endforelse
                </section>
                <section class="card">
                    <h2 class="h-section">Care plans</h2>
                    @forelse ($plans as $plan)
                        <p class="mt-3"><a href="{{ route('admin.care-plans.show', $plan) }}" class="link">{{ $plan->name }}</a>
                            <span class="text-muted block text-sm">{{ $money($plan->amount_cents) }} excl. VAT, {{ strtolower($plan->intervalLabel()) }} · {{ $plan->statusLabel() }}</span></p>
                    @empty
                        <p class="text-muted mt-2 text-sm">None. <a href="{{ route('admin.care-plans.index') }}" class="link">Add one</a></p>
                    @endforelse
                </section>
            </div>

            <div id="panel-details" role="tabpanel" aria-labelledby="tab-details" x-show="tab === 'details'" x-cloak class="grid items-start gap-6 lg:grid-cols-5">
                <form method="POST" action="{{ route('admin.clients.update', $client) }}" class="card space-y-5 lg:col-span-3">
                    @csrf
                    @method('PUT')
                    <h2 class="h-section">Details</h2>
                    @include('admin.clients.fields', ['client' => $client])
                    <button type="submit" class="btn btn-dark">Save details</button>
                </form>

                <div class="space-y-6 lg:col-span-2">
                    <section class="card space-y-3">
                        <h2 class="h-section">Portal access</h2>
                        @if ($login)
                            <p>{{ $login->name }} signs in with <a href="mailto:{{ $login->email }}" class="link break-all">{{ $login->email }}</a>.</p>
                        @else
                            <p class="text-muted text-sm">No login yet. You can still do everything for them; they are not emailed, so send the PDFs yourself.</p>
                            <form method="POST" action="{{ route('admin.clients.access', $client) }}">
                                @csrf
                                <button type="submit" class="btn btn-dark w-full" @disabled(! $client->email)>Give portal access</button>
                            </form>
                            @if ($client->email)
                                <p class="text-muted text-sm">They get a sign-in link by email, valid for 7 days.</p>
                            @else
                                <p class="text-muted text-sm">Add their email in Details first.</p>
                            @endif
                            @error('email')<p class="text-sm text-red-800">{{ $message }}</p>@enderror
                        @endif
                    </section>

                    <section class="card space-y-3">
                        <h2 class="h-section">Records</h2>
                        <a href="{{ route('admin.clients.export', $client) }}" class="btn btn-light w-full">Download all their records (zip)</a>
                        <p class="text-muted text-sm">Requests, quotes, invoices, projects, messages and files. For a POPIA request or when a client leaves.</p>
                        @unless ($client->isArchived())
                            <details class="border-line border-t pt-3">
                                <summary class="link min-h-11 cursor-pointer py-2">Archive this client</summary>
                                <form method="POST" action="{{ route('admin.clients.archive', $client) }}" class="mt-2 space-y-3" onsubmit="return confirm('Archive {{ addslashes($client->business_name) }}? They will not be able to sign in.')">
                                    @csrf
                                    <label for="archived_reason" class="label">Reason <span class="text-muted font-normal">(for your records)</span></label>
                                    <input id="archived_reason" name="archived_reason" maxlength="300" class="field" placeholder="For example project finished">
                                    <button type="submit" class="btn btn-light w-full text-red-800">Archive client</button>
                                    <p class="text-muted text-sm">Nothing is deleted. Care plans pause, and you can restore them any time.</p>
                                </form>
                            </details>
                        @endunless
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
