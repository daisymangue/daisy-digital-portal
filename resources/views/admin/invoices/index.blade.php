<x-layouts.app title="Invoices">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <h1 class="h-page">Invoices</h1>
        <p class="text-muted">
            Outstanding: <span class="text-ink text-xl font-semibold">{{ \App\Support\Money::format($outstanding) }}</span>
            @if ($overdueCount > 0) · <a href="{{ route('admin.invoices.index', ['status' => 'overdue']) }}" class="font-semibold text-red-800 underline underline-offset-2">{{ $overdueCount }} overdue</a> @endif
        </p>
    </div>

    <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="w-full sm:w-64">
                <label for="q" class="label">Search</label>
                <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Number, title or client" class="field">
            </div>
            <div class="w-full sm:w-56">
                <label for="status" class="label">Status</label>
                <select id="status" name="status" class="field">
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Invoice::STATUSES as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-dark">Filter</button>
        </form>

        @if ($clients->isNotEmpty())
            <form method="POST" action="{{ route('admin.invoices.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="w-full sm:w-56">
                    <label for="client_id" class="label">New invoice for</label>
                    <select id="client_id" name="client_id" required class="field">
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->business_name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Start invoice</button>
            </form>
        @endif
    </div>
    <p class="text-muted mt-3 text-sm">To invoice a quote, open the accepted quote and use its invoice buttons. That keeps the amounts tied to what the client agreed.</p>

    <div class="card mt-6 overflow-x-auto p-0 sm:p-0">
        <table class="w-full min-w-[720px] text-left">
            <thead class="text-muted text-sm">
                <tr>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Invoice</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Client</th>
                    <th scope="col" class="border-line border-b px-5 py-3 text-right font-medium">Total</th>
                    <th scope="col" class="border-line border-b px-5 py-3 text-right font-medium">Balance</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Due</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-line divide-y">
                @forelse ($invoices as $inv)
                    <tr>
                        <td class="px-5 py-4">
                            <a href="{{ route('admin.invoices.show', $inv) }}" class="link font-semibold">{{ $inv->reference() }}</a>
                            <div class="text-muted text-sm">{{ $inv->title }}</div>
                        </td>
                        <td class="px-5 py-4">{{ $inv->client->business_name }}</td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">{{ \App\Support\Money::format($inv->total_cents) }}</td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">{{ \App\Support\Money::format($inv->balanceCents()) }}</td>
                        <td class="px-5 py-4 whitespace-nowrap">{{ $inv->due_date?->format('j M Y') }}</td>
                        <td class="px-5 py-4"><x-status :status="$inv->effectiveStatus()" :label="$inv->statusLabel()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted px-5 py-8 text-center">No invoices match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $invoices->links() }}</div>
</x-layouts.app>
