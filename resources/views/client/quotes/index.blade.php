<x-layouts.app title="Quotes">
    <h1 class="h-page">Quotes</h1>

    <div class="mt-8 space-y-3">
        @forelse ($quotes as $q)
            <a href="{{ route('quotes.show', $q->id) }}" class="card hover:border-line-strong flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ $q->reference() }} · {{ $q->title }}</p>
                    <p class="text-muted text-sm">{{ \App\Support\Money::format($q->total_cents) }} · Sent {{ $q->sent_at?->format('j M Y') }}</p>
                </div>
                <x-status :status="$q->effectiveStatus()" :label="$q->clientStatusLabel()" />
            </a>
        @empty
            <div class="card text-muted">No quotes yet. When we quote on a request, it appears here and we email you.</div>
        @endforelse
    </div>

    <div class="mt-6">{{ $quotes->links() }}</div>
</x-layouts.app>
