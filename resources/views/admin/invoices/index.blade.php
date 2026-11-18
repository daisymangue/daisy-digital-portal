@php
    $kinds = ['all' => 'All bills', 'plan' => 'Subscription bills', 'project' => 'Project bills'];
    $statuses = ['unpaid' => 'Unpaid', 'overdue' => 'Overdue', 'paid' => 'Paid', 'draft' => 'Drafts'.($drafts ? " ({$drafts})" : ''), 'cancelled' => 'Cancelled', 'all' => 'Any status'];
@endphp
<x-layouts.app title="Billing · All bills">
    @include('admin.billing.header', ['tab' => 'bills'])

    <form method="GET" class="mt-6 flex flex-wrap items-center gap-2" role="search">
        <label for="kind" class="sr-only">Type of bill</label>
        <select id="kind" name="kind" class="field w-auto" onchange="this.form.submit()">
            @foreach ($kinds as $k => $l)
                <option value="{{ $k === 'all' ? '' : $k }}" @selected($kind === $k)>{{ $l }}</option>
            @endforeach
        </select>
        <label for="status" class="sr-only">Payment status</label>
        <select id="status" name="status" class="field w-auto" onchange="this.form.submit()">
            @foreach ($statuses as $k => $l)
                <option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>
            @endforeach
        </select>
        <label for="q" class="sr-only">Search bills</label>
        <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Search number, title or client" class="field min-w-0 flex-1 sm:max-w-xs">
        <button type="submit" class="btn btn-dark">Search</button>
        @if ($search !== '' || $kind !== 'all' || $status !== 'unpaid')
            <a href="{{ route('admin.invoices.index') }}" class="link flex min-h-11 items-center px-1">Clear</a>
        @endif
    </form>

    <ul class="card divide-line mt-6 divide-y p-0 md:hidden">
        @forelse ($invoices as $inv)
            <li class="flex items-center gap-2 py-1 pr-1 pl-4">
                <a href="{{ route('admin.invoices.show', $inv) }}" class="min-w-0 flex-1 py-2">
                    <span class="flex items-center justify-between gap-2"><span class="text-brand-dark font-semibold">{{ $inv->reference() }}</span><x-status :status="$inv->effectiveStatus()" :label="$inv->statusLabel()" /></span>
                    <span class="block truncate text-sm">{{ $inv->client->business_name }} · {{ $inv->title }}</span>
                    <span class="text-muted block text-sm">{{ \App\Support\Money::format($inv->balanceCents() ?: $inv->total_cents) }}{{ $inv->isOpen() ? ' owing' : '' }}@if ($inv->isOpen() && $inv->due_date) · due {{ $inv->due_date->format('j M') }}@endif</span>
                </a>
                <a href="{{ route('admin.invoices.pdf', $inv) }}" class="text-muted flex size-11 shrink-0 items-center justify-center rounded-full" aria-label="Download {{ $inv->reference() }} as PDF">
                    <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.6L6.3 8.4a.75.75 0 1 0-1.1 1.03l4.25 4.5a.75.75 0 0 0 1.1 0l4.25-4.5a.75.75 0 0 0-1.1-1.03l-2.95 2.95v-8.6Z"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                </a>
            </li>
        @empty
            <li class="text-muted px-5 py-8 text-center">{{ $status === 'unpaid' && $search === '' ? 'Nothing unpaid. Everyone is up to date.' : 'No bills match.' }}</li>
        @endforelse
    </ul>

    <div class="card mt-6 hidden overflow-x-auto p-0 sm:p-0 md:block">
        <table class="w-full min-w-[720px] text-left">
            <thead class="text-muted text-sm">
                <tr>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Bill</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Client</th>
                    <th scope="col" class="border-line border-b px-5 py-3 text-right font-medium">Total</th>
                    <th scope="col" class="border-line border-b px-5 py-3 text-right font-medium">Owing</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Due</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Status</th>
                    <th scope="col" class="border-line border-b px-3 py-3"><span class="sr-only">Download</span></th>
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
                        <td class="px-3 py-4">
                            <a href="{{ route('admin.invoices.pdf', $inv) }}" class="text-muted hover:text-ink flex size-11 items-center justify-center rounded-full" title="Download PDF" aria-label="Download {{ $inv->reference() }} as PDF">
                                <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.6L6.3 8.4a.75.75 0 1 0-1.1 1.03l4.25 4.5a.75.75 0 0 0 1.1 0l4.25-4.5a.75.75 0 0 0-1.1-1.03l-2.95 2.95v-8.6Z"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted px-5 py-8 text-center">{{ $status === 'unpaid' && $search === '' ? 'Nothing unpaid. Everyone is up to date.' : 'No bills match.' }}</td></tr>
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
