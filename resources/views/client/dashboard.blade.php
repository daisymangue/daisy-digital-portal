<x-layouts.app title="Dashboard">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="h-page">Welcome, {{ Str::before(auth()->user()->name, ' ') }}</h1>
            <p class="text-muted mt-1">Here is where things stand for {{ $client->business_name }}.</p>
        </div>
        <a href="{{ route('requests.create') }}" class="btn btn-primary">Start a new request</a>
    </div>

    @foreach ($projects->filter(fn ($p) => $p->approve_count || $p->needed_count) as $p)
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-violet-200 bg-violet-50 px-5 py-4 text-violet-950">
            <p><span class="font-semibold">{{ $p->title }}:</span>
                {{ collect([$p->approve_count ? trans_choice(':count step to approve|:count steps to approve', $p->approve_count) : null, $p->needed_count ? trans_choice(':count thing we need from you|:count things we need from you', $p->needed_count) : null])->filter()->implode(' and ') }}.</p>
            <a href="{{ route('projects.show', $p->id) }}" class="btn btn-dark">Open project</a>
        </div>
    @endforeach

    @foreach ($quotesWaiting as $q)
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-blue-950">
            <p><span class="font-semibold">Quote {{ $q->reference() }} is ready.</span> {{ \App\Support\Money::format($q->total_cents) }}, valid until {{ $q->valid_until->format('j M Y') }}.</p>
            <a href="{{ route('quotes.show', $q->id) }}" class="btn btn-dark">View quote</a>
        </div>
    @endforeach

    @foreach ($invoicesDue as $inv)
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border px-5 py-4 {{ $inv->effectiveStatus() === 'overdue' ? 'border-red-200 bg-red-50 text-red-950' : 'border-amber-200 bg-amber-50 text-amber-950' }}">
            <p><span class="font-semibold">Invoice {{ $inv->number }}:</span> {{ \App\Support\Money::format($inv->balanceCents()) }} {{ $inv->effectiveStatus() === 'overdue' ? 'was due' : 'due by' }} {{ $inv->due_date->format('j M Y') }}.</p>
            <a href="{{ route('invoices.show', $inv->id) }}" class="btn btn-dark">View and pay</a>
        </div>
    @endforeach

    <div class="mt-8 grid gap-4 sm:grid-cols-3">
        <div class="card"><p class="text-muted text-sm">Open requests</p><p class="mt-1 text-3xl font-semibold">{{ $openCount }}</p></div>
        <div class="card"><p class="text-muted text-sm">Waiting on you</p><p class="mt-1 text-3xl font-semibold">{{ $waitingCount }}</p></div>
        <div class="card"><p class="text-muted text-sm">Outstanding balance</p><p class="mt-1 text-3xl font-semibold">{{ \App\Support\Money::format($invoicesDue->sum(fn ($i) => $i->balanceCents())) }}</p></div>
    </div>

    <div class="mt-8 grid gap-8 lg:grid-cols-3">
        <section class="lg:col-span-2">
            @if ($projects->isNotEmpty())
                <h2 class="h-section">Your projects</h2>
                <div class="mt-4 mb-8 grid gap-3 sm:grid-cols-2">
                    @foreach ($projects as $p)
                        <a href="{{ route('projects.show', $p->id) }}" class="card hover:border-line-strong space-y-3">
                            <div class="flex items-start justify-between gap-2">
                                <p class="min-w-0 font-semibold">{{ $p->title }}</p>
                                @if ($p->unread_count)<span class="bg-brand shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold text-white">{{ $p->unread_count }} new</span>@endif
                            </div>
                            @include('partials.project-progress', ['project' => $p])
                        </a>
                    @endforeach
                </div>
            @endif
            <div class="flex items-center justify-between gap-3">
                <h2 class="h-section">Recent requests</h2>
                @if ($totalCount > 0)
                    <a href="{{ route('requests.index') }}" class="link">View all</a>
                @endif
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($recent as $r)
                    <a href="{{ route('requests.show', $r->id) }}" class="card hover:border-line-strong flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ $r->number }}</p>
                            <p class="text-muted text-sm">
                                {{ $r->package_name ?: ($r->services_count ? trans_choice(':count service|:count services', $r->services_count) : ($r->isSpecial() ? 'Special request' : 'Your message')) }}
                                · Sent {{ $r->submitted_at->format('j M Y') }}
                            </p>
                        </div>
                        <x-status :status="$r->status" :label="$r->clientStatusLabel()" />
                    </a>
                @empty
                    <div class="card text-center">
                        <p class="font-medium">You have not sent a request yet.</p>
                        <p class="text-muted mt-1">Pick the services you need and we will reply with a quote.</p>
                        <a href="{{ route('requests.create') }}" class="btn btn-primary mt-4">Start a request</a>
                    </div>
                @endforelse
            </div>
        </section>

        <section>
            @if ($plans->isNotEmpty())
                <h2 class="h-section">Your care plans</h2>
                <ul class="card mt-4 mb-8 space-y-3">
                    @foreach ($plans as $plan)
                        <li>
                            <p class="font-medium">{{ $plan->name }}</p>
                            <p class="text-muted text-sm">{{ \App\Support\Money::format($plan->vatInclusiveCents()) }} incl. VAT, {{ strtolower($plan->intervalLabel()) }} · next invoice {{ $plan->next_invoice_on->format('j M Y') }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
            <h2 class="h-section">Updates</h2>
            <ul class="card mt-4 space-y-4">
                @forelse ($notifications as $n)
                    <li>
                        <a href="{{ $n->data['url'] ?? '#' }}" class="font-medium hover:underline">{{ $n->data['title'] ?? 'Update' }}</a>
                        <p class="text-muted text-sm">{{ $n->created_at->diffForHumans() }}</p>
                    </li>
                @empty
                    <li class="text-muted">No updates yet. We will let you know here and by email when something changes.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.app>
