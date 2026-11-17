@php
    $money = fn ($cents) => \App\Support\Money::format($cents);
@endphp
<x-layouts.app :title="$client->business_name">
    <a href="{{ route('admin.clients.index') }}" class="link text-sm">Back to clients</a>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="h-page">{{ $client->business_name }}</h1>
            <p class="text-muted mt-1">Client since {{ $client->created_at->format('j F Y') }}</p>
        </div>
        <span class="pill {{ $client->users->isNotEmpty() ? 'bg-green-100 text-green-900' : 'bg-sand-dark text-muted' }}">{{ $client->users->isNotEmpty() ? 'Has portal access' : 'No portal login' }}</span>
    </div>

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

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-8 lg:col-span-2">
            <section>
                <h2 class="h-section">Requests</h2>
                <div class="mt-3 space-y-3">
                    @forelse ($requests as $r)
                        <a href="{{ route('admin.requests.show', $r) }}" class="card hover:border-line-strong flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold">{{ $r->number }}</p>
                                <p class="text-muted text-sm">{{ $r->package_name ?: trans_choice(':count service|:count services', $r->services_count) }} · {{ $r->submitted_at->format('j M Y') }} · {{ $r->sourceLabel() }}</p>
                            </div>
                            <x-status :status="$r->status" :label="$r->statusLabel()" />
                        </a>
                    @empty
                        <div class="card text-muted">No requests yet.</div>
                    @endforelse
                </div>
            </section>

            <section>
                <h2 class="h-section">Quotes</h2>
                <div class="mt-3 space-y-3">
                    @forelse ($quotes as $q)
                        <a href="{{ $q->isDraft() ? route('admin.quotes.edit', $q) : route('admin.quotes.show', $q) }}" class="card hover:border-line-strong flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold">{{ $q->reference() }} · {{ $q->title }}</p>
                                <p class="text-muted text-sm">{{ $money($q->total_cents) }}</p>
                            </div>
                            <x-status :status="$q->effectiveStatus()" :label="$q->statusLabel()" />
                        </a>
                    @empty
                        <div class="card text-muted">No quotes yet.</div>
                    @endforelse
                </div>
            </section>

            <section>
                <h2 class="h-section">Invoices</h2>
                <div class="mt-3 space-y-3">
                    @forelse ($invoices as $inv)
                        <a href="{{ route('admin.invoices.show', $inv) }}" class="card hover:border-line-strong flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-semibold">{{ $inv->reference() }} · {{ $inv->title }}</p>
                                <p class="text-muted text-sm">{{ $money($inv->total_cents) }} · Balance {{ $money($inv->balanceCents()) }}</p>
                            </div>
                            <x-status :status="$inv->effectiveStatus()" :label="$inv->statusLabel()" />
                        </a>
                    @empty
                        <div class="card text-muted">No invoices yet.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="min-w-0 space-y-6">
            <div class="card">
                <h2 class="h-section">Projects</h2>
                @forelse ($projects as $p)
                    <a href="{{ route('admin.projects.show', $p) }}" class="mt-3 flex items-center justify-between gap-3">
                        <span class="link min-w-0 truncate">{{ $p->number }} · {{ $p->title }}</span>
                        <x-status :status="$p->status" :label="$p->statusLabel()" />
                    </a>
                @empty
                    <p class="text-muted mt-2 text-sm">None yet.</p>
                @endforelse
            </div>
            <div class="card">
                <h2 class="h-section">Care plans</h2>
                @forelse ($plans as $plan)
                    <p class="mt-3"><a href="{{ route('admin.care-plans.index', ['edit' => $plan->id]) }}" class="link">{{ $plan->name }}</a>
                        <span class="text-muted block text-sm">{{ $money($plan->amount_cents) }} excl. VAT, {{ strtolower($plan->intervalLabel()) }} · {{ $plan->statusLabel() }}</span></p>
                @empty
                    <p class="text-muted mt-2 text-sm">None. <a href="{{ route('admin.care-plans.index') }}" class="link">Add one</a></p>
                @endforelse
            </div>
            <div class="card space-y-3">
                <h2 class="h-section">Portal access</h2>
                @forelse ($client->users as $person)
                    <div>
                        <p class="font-medium">{{ $person->name }}</p>
                        <p class="text-sm break-all"><a href="mailto:{{ $person->email }}" class="link">{{ $person->email }}</a></p>
                    </div>
                @empty
                    <p class="text-muted text-sm">This client has no login. You can still create requests, quotes and invoices for them. They are not emailed, so send the PDFs yourself.</p>
                    <form method="POST" action="{{ route('admin.clients.access', $client) }}">
                        @csrf
                        <button type="submit" class="btn btn-dark w-full" @disabled(! $client->email)>Give portal access</button>
                    </form>
                    @unless ($client->email)
                        <p class="text-muted text-sm">Add their email below first.</p>
                    @endunless
                @endforelse
            </div>

            <form method="POST" action="{{ route('admin.clients.update', $client) }}" class="card space-y-5">
                @csrf
                @method('PUT')
                <h2 class="h-section">Details</h2>
                @include('admin.clients.fields', ['client' => $client, 'narrow' => true])
                <button type="submit" class="btn btn-light w-full">Save details</button>
            </form>
        </aside>
    </div>
</x-layouts.app>
