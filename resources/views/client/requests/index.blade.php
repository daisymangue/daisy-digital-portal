<x-layouts.app title="My requests">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="h-page">My requests</h1>
        <a href="{{ route('requests.create') }}" class="btn btn-primary">Start a new request</a>
    </div>

    <div class="mt-8 space-y-3">
        @forelse ($requests as $r)
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
                <p class="font-medium">No requests yet.</p>
                <a href="{{ route('requests.create') }}" class="btn btn-primary mt-4">Start a request</a>
            </div>
        @endforelse
    </div>

    <div class="mt-6">{{ $requests->links() }}</div>
</x-layouts.app>
