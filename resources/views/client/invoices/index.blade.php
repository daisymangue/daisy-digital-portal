<x-layouts.app title="Billing">
    <h1 class="h-page">Billing</h1>
    <p class="text-muted mt-1">Your subscriptions and bills in one place.</p>

    <dl class="mt-6 grid grid-cols-2 gap-3 lg:max-w-2xl">
        <div class="card py-4 {{ $overdue ? 'border-red-200 bg-red-50' : '' }}">
            <dt class="text-sm {{ $overdue ? 'text-red-900' : 'text-muted' }}">To pay now</dt>
            <dd class="mt-1 text-2xl font-semibold {{ $overdue ? 'text-red-900' : '' }}">{{ \App\Support\Money::format($outstanding) }}</dd>
            @if ($overdue)<dd class="text-sm text-red-800">{{ trans_choice(':count bill is overdue|:count bills are overdue', $overdue) }}</dd>@endif
        </div>
        @if ($next)
            <div class="card py-4">
                <dt class="text-muted text-sm">Next bill</dt>
                <dd class="mt-1 text-2xl font-semibold">{{ \App\Support\Money::format($next->vatInclusiveCents()) }}</dd>
                <dd class="text-muted text-sm">on {{ $next->next_invoice_on->format('j M Y') }}, {{ $next->name }}</dd>
            </div>
        @endif
    </dl>

    @if ($plans->isNotEmpty())
        <h2 class="h-section mt-10">Your subscriptions</h2>
        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            @foreach ($plans as $plan)
                @php [$payKey, $payLabel] = $plan->paymentState(); @endphp
                <div class="card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-semibold">{{ $plan->name }}</p>
                            <p class="text-muted text-sm">{{ \App\Support\Money::format($plan->vatInclusiveCents()) }} incl. VAT {{ $plan->perLabel() }}</p>
                        </div>
                        @if ($plan->status === 'paused')
                            <x-status status="on_hold" label="Paused" />
                        @else
                            <x-status :status="$payKey" :label="$payLabel" />
                        @endif
                    </div>
                    @if ($plan->includedItems())
                        <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                            @foreach ($plan->includedItems() as $item)
                                <li class="flex items-center gap-1.5"><svg class="text-brand size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <p class="text-muted border-line mt-4 border-t pt-3 text-sm">
                        @if ($plan->status === 'active') Next bill {{ $plan->next_invoice_on->format('j M Y') }} · @endif
                        Pay with reference <span class="text-ink font-semibold whitespace-nowrap">{{ $plan->reference }}</span>
                    </p>
                </div>
            @endforeach
        </div>
        <p class="text-muted mt-3 text-sm">Using the same reference every time lets you set up a repeat EFT or stop order. Want to change or stop a plan? Send us a message from any project, or <a href="{{ route('requests.create') }}" class="link">start a request</a>.</p>
    @endif

    <h2 class="h-section mt-10">Your bills</h2>
    <div class="mt-4 space-y-3">
        @forelse ($invoices as $inv)
            <div class="card hover:border-line-strong relative flex flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <a href="{{ route('invoices.show', $inv->id) }}" class="font-semibold after:absolute after:inset-0 after:content-['']">{{ $inv->number }}</a>
                    <p class="text-muted flex items-center gap-1.5 text-sm">
                        @if ($inv->care_plan_id)
                            <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-label="Subscription bill" role="img"><path fill-rule="evenodd" d="M15.3 5.2A7 7 0 0 0 3.1 9a.75.75 0 0 0 1.48.24 5.5 5.5 0 0 1 9.6-2.98l-1.7.1a.75.75 0 0 0 .08 1.5l3.5-.2a.75.75 0 0 0 .7-.8l-.2-3.5a.75.75 0 0 0-1.5.08l.1 1.76ZM4.7 14.8A7 7 0 0 0 16.9 11a.75.75 0 0 0-1.48-.24 5.5 5.5 0 0 1-9.6 2.98l1.7-.1a.75.75 0 0 0-.08-1.5l-3.5.2a.75.75 0 0 0-.7.8l.2 3.5a.75.75 0 0 0 1.5-.08l-.1-1.76Z" clip-rule="evenodd"/></svg>
                        @endif
                        <span class="truncate">{{ $inv->title }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-sm whitespace-nowrap">
                        {{ \App\Support\Money::format($inv->total_cents) }}
                        @if ($inv->isOpen())<span class="text-muted"> · due {{ $inv->due_date->format('j M') }}</span>@endif
                    </span>
                    <x-status :status="$inv->effectiveStatus()" :label="$inv->clientStatusLabel()" />
                    <a href="{{ route('invoices.pdf', $inv->id) }}" class="text-muted hover:text-ink hover:bg-sand relative z-10 -my-2 flex size-11 items-center justify-center rounded-full" title="Download PDF" aria-label="Download {{ $inv->number }} as PDF">
                        <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.6L6.3 8.4a.75.75 0 1 0-1.1 1.03l4.25 4.5a.75.75 0 0 0 1.1 0l4.25-4.5a.75.75 0 0 0-1.1-1.03l-2.95 2.95v-8.6Z"/><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z"/></svg>
                    </a>
                </div>
            </div>
        @empty
            <div class="card text-muted">No bills yet. When we bill you, it appears here and we email you.</div>
        @endforelse
    </div>

    <div class="mt-6">{{ $invoices->links() }}</div>
</x-layouts.app>
