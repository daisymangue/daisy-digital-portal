<x-layouts.app title="Quotes">
    <h1 class="h-page">Quotes</h1>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-2" role="search">
            <label for="status" class="sr-only">Status</label>
            <select id="status" name="status" class="field w-auto" onchange="this.form.submit()">
                <option value="">All statuses</option>
                @foreach (\App\Models\Quote::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <label for="q" class="sr-only">Search quotes</label>
            <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Search number, title or client" class="field min-w-0 flex-1 sm:w-64">
            <button type="submit" class="btn btn-dark">Search</button>
        </form>

        @if ($clients->isNotEmpty())
            <form method="POST" action="{{ route('admin.quotes.store') }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <div class="w-full sm:w-56">
                    <label for="client_id" class="sr-only">New quote for</label>
                    <select id="client_id" name="client_id" required class="field">
                        <option value="" disabled selected>New quote for...</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->business_name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Start quote</button>
            </form>
        @endif
    </div>

    <div class="card mt-6 overflow-x-auto p-0 sm:p-0">
        <table class="w-full min-w-[680px] text-left">
            <thead class="text-muted text-sm">
                <tr>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Quote</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Client</th>
                    <th scope="col" class="border-line border-b px-5 py-3 text-right font-medium">Total</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Valid until</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-line divide-y">
                @forelse ($quotes as $q)
                    <tr>
                        <td class="px-5 py-4">
                            <a href="{{ $q->isDraft() ? route('admin.quotes.edit', $q) : route('admin.quotes.show', $q) }}" class="link font-semibold">{{ $q->reference() }}</a>
                            <div class="text-muted text-sm">{{ $q->title }}</div>
                        </td>
                        <td class="px-5 py-4">{{ $q->client->business_name }}</td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">{{ \App\Support\Money::format($q->total_cents) }}</td>
                        <td class="px-5 py-4 whitespace-nowrap">{{ $q->valid_until?->format('j M Y') }}</td>
                        <td class="px-5 py-4"><x-status :status="$q->effectiveStatus()" :label="$q->statusLabel()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted px-5 py-8 text-center">No quotes yet. Open a request and choose "Create quote".</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $quotes->links() }}</div>
</x-layouts.app>
