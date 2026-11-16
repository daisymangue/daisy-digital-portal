<x-layouts.app title="Invoices">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="h-page">Invoices</h1>
        <p class="text-muted">Outstanding: <span class="text-ink text-xl font-semibold">{{ \App\Support\Money::format($outstanding) }}</span></p>
    </div>

    <div class="mt-8 space-y-3">
        @forelse ($invoices as $inv)
            <a href="{{ route('invoices.show', $inv->id) }}" class="card hover:border-line-strong flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold">{{ $inv->number }} · {{ $inv->title }}</p>
                    <p class="text-muted text-sm">
                        {{ \App\Support\Money::format($inv->total_cents) }}
                        @if ($inv->isOpen()) · Due {{ $inv->due_date->format('j M Y') }} @endif
                    </p>
                </div>
                <x-status :status="$inv->effectiveStatus()" :label="$inv->clientStatusLabel()" />
            </a>
        @empty
            <div class="card text-muted">No invoices yet. When we invoice you, it appears here and we email you.</div>
        @endforelse
    </div>

    <div class="mt-6">{{ $invoices->links() }}</div>
</x-layouts.app>
