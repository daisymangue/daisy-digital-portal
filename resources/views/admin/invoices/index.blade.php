@php
    $kinds = ['all' => 'All', 'plan' => 'Subscription bills', 'project' => 'Project bills'];
    $statuses = ['unpaid' => 'Unpaid', 'overdue' => 'Overdue', 'paid' => 'Paid', 'draft' => 'Drafts'.($drafts ? " ({$drafts})" : ''), 'all' => 'Any status'];
    $chip = fn ($on) => 'inline-flex min-h-10 items-center rounded-full border px-4 text-sm font-medium '.($on ? 'border-navy bg-navy text-white' : 'border-line-strong bg-white hover:border-navy');
@endphp
<x-layouts.app title="Billing · All bills">
    @include('admin.billing.header', ['tab' => 'bills'])

    <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap gap-2" role="group" aria-label="Type of bill">
            @foreach ($kinds as $k => $l)
                <a href="{{ route('admin.invoices.index', array_filter(['kind' => $k === 'all' ? null : $k, 'status' => $status, 'q' => $search])) }}" class="{{ $chip($kind === $k) }}" @if ($kind === $k) aria-current="true" @endif>{{ $l }}</a>
            @endforeach
        </div>
        <form method="GET" class="flex w-full gap-2 sm:w-72">
            <input type="hidden" name="kind" value="{{ $kind === 'all' ? '' : $kind }}">
            <label for="q" class="sr-only">Search bills</label>
            <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Number, title or client" class="field">
            <button type="submit" class="btn btn-dark">Search</button>
        </form>
    </div>

    <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Payment status">
        @foreach ($statuses as $k => $l)
            <a href="{{ route('admin.invoices.index', array_filter(['kind' => $kind === 'all' ? null : $kind, 'status' => $k, 'q' => $search])) }}" class="{{ $chip($status === $k) }}" @if ($status === $k) aria-current="true" @endif>{{ $l }}</a>
        @endforeach
    </div>

    <div class="card mt-6 overflow-x-auto p-0 sm:p-0">
        <table class="w-full min-w-[720px] text-left">
            <thead class="text-muted text-sm">
                <tr>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Bill</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Client</th>
                    <th scope="col" class="border-line border-b px-5 py-3 text-right font-medium">Total</th>
                    <th scope="col" class="border-line border-b px-5 py-3 text-right font-medium">Owing</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Due</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-line divide-y">
                @forelse ($invoices as $inv)
                    <tr>
                        <td class="px-5 py-4">
                            <a href="{{ route('admin.invoices.show', $inv) }}" class="link font-semibold">{{ $inv->reference() }}</a>
                            <div class="text-muted flex items-center gap-1.5 text-sm">
                                @if ($inv->care_plan_id)
                                    <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-label="Subscription bill" role="img"><path fill-rule="evenodd" d="M15.3 5.2A7 7 0 0 0 3.1 9a.75.75 0 0 0 1.48.24 5.5 5.5 0 0 1 9.6-2.98l-1.7.1a.75.75 0 0 0 .08 1.5l3.5-.2a.75.75 0 0 0 .7-.8l-.2-3.5a.75.75 0 0 0-1.5.08l.1 1.76ZM4.7 14.8A7 7 0 0 0 16.9 11a.75.75 0 0 0-1.48-.24 5.5 5.5 0 0 1-9.6 2.98l1.7-.1a.75.75 0 0 0-.08-1.5l-3.5.2a.75.75 0 0 0-.7.8l.2 3.5a.75.75 0 0 0 1.5-.08l-.1-1.76Z" clip-rule="evenodd"/></svg>
                                @else
                                    <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-label="Project bill" role="img"><path fill-rule="evenodd" d="M6 3.75A2.75 2.75 0 0 1 8.75 1h2.5A2.75 2.75 0 0 1 14 3.75V4h1.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H6v-.25ZM7.5 4h5v-.25c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25V4Z" clip-rule="evenodd"/></svg>
                                @endif
                                <span class="truncate">{{ $inv->title }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-4">{{ $inv->client->business_name }}</td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">{{ \App\Support\Money::format($inv->total_cents) }}</td>
                        <td class="px-5 py-4 text-right whitespace-nowrap">{{ \App\Support\Money::format($inv->balanceCents()) }}</td>
                        <td class="px-5 py-4 whitespace-nowrap">{{ $inv->due_date?->format('j M Y') }}</td>
                        <td class="px-5 py-4"><x-status :status="$inv->effectiveStatus()" :label="$inv->statusLabel()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted px-5 py-8 text-center">{{ $status === 'unpaid' && $search === '' ? 'Nothing unpaid. Everyone is up to date.' : 'No bills match.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $invoices->links() }}</div>

    @if ($clients->isNotEmpty())
        <x-fold title="New one-off invoice" summary="For work without a quote" class="mt-8 max-w-xl">
            <form method="POST" action="{{ route('admin.invoices.store') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="w-full sm:w-64">
                    <label for="client_id" class="label">Client</label>
                    <select id="client_id" name="client_id" required class="field">
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->business_name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Start invoice</button>
            </form>
            <p class="text-muted mt-3 text-sm">To bill a quote, open the accepted quote and use its invoice buttons. That keeps the amounts tied to what the client agreed.</p>
        </x-fold>
    @endif
</x-layouts.app>
