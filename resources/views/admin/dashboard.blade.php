<x-layouts.app title="Admin dashboard">
    <h1 class="h-page">Agency overview</h1>
    <p class="text-muted mt-1">{{ now()->format('l j F Y') }}</p>

    <section class="mt-8" aria-labelledby="attention">
        <h2 id="attention" class="h-section">Needs your attention</h2>
        @if ($attention->isEmpty())
            <p class="card mt-3 text-center">You are all caught up. Nothing is waiting on you.</p>
        @else
            <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($attention as [$label, $count, $url, $hint])
                    <a href="{{ $url }}" class="card hover:border-line-strong flex items-start gap-4">
                        <span class="bg-brand flex size-11 shrink-0 items-center justify-center rounded-full text-lg font-bold text-white">{{ $count }}</span>
                        <span class="min-w-0">
                            <span class="block font-semibold">{{ $label }}</span>
                            <span class="text-muted block text-sm">{{ $hint }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <h2 class="h-section mt-10">At a glance</h2>
    <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $label => $value)
            <div class="card"><p class="text-muted text-sm">{{ $label }}</p><p class="mt-1 text-3xl font-semibold">{{ $value }}</p></div>
        @endforeach
    </div>

    @foreach ($pendingPayments as $p)
        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-950">
            <p><span class="font-semibold">Proof of payment to check:</span> {{ $p->invoice->client->business_name }}, {{ \App\Support\Money::format($p->amount_cents) }} for {{ $p->invoice->number }}.</p>
            <a href="{{ route('admin.invoices.show', $p->invoice_id) }}" class="btn btn-dark">Review</a>
        </div>
    @endforeach

    <div class="mt-8 grid gap-8 lg:grid-cols-3">
        <section class="lg:col-span-2">
            <div class="flex items-center justify-between gap-3">
                <h2 class="h-section">Requests needing attention</h2>
                <a href="{{ route('admin.requests.index') }}" class="link">View all</a>
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($requests as $r)
                    <a href="{{ route('admin.requests.show', $r) }}" class="card hover:border-line-strong flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <p class="font-semibold">{{ $r->number }} · {{ $r->client->business_name }}
                                @if ($r->isSpecial())
                                    <span class="pill ml-1 bg-pink-100 text-pink-900">Special</span>
                                @endif
                            </p>
                            <p class="text-muted text-sm">
                                {{ $r->package_name ?: trans_choice(':count service|:count services', $r->services_count) }}
                                · {{ $r->submitted_at->diffForHumans() }}
                            </p>
                        </div>
                        <x-status :status="$r->status" :label="$r->statusLabel()" />
                    </a>
                @empty
                    <div class="card text-muted">Nothing waiting. New requests will appear here.</div>
                @endforelse
            </div>
        </section>

        <section>
            <h2 class="h-section">Recent activity</h2>
            <ul class="card mt-4 space-y-4">
                @forelse ($activity as $log)
                    <li>
                        <p class="font-medium">{{ Str::of($log->action)->replace(['.', '_'], ' ')->ucfirst() }}@if (! empty($log->meta['number'])) · {{ $log->meta['number'] }}@endif</p>
                        <p class="text-muted text-sm">{{ $log->user?->name ?? 'System' }} · {{ $log->created_at->diffForHumans() }}</p>
                    </li>
                @empty
                    <li class="text-muted">No activity yet.</li>
                @endforelse
            </ul>
        </section>
    </div>
</x-layouts.app>
