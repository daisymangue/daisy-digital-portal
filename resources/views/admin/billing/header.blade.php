{{-- Shared top of the three Billing tabs. Needs: $tab (plans | bills | menu) --}}
@php
    $sum = \App\Support\BillingSummary::admin();
    $planCount = \App\Models\CarePlan::where('status', '!=', 'cancelled')->count();
    $tabs = [
        'plans' => ['Subscriptions', route('admin.care-plans.index'), $planCount],
        'bills' => ['All bills', route('admin.invoices.index'), null],
        'menu' => ['Plan menu', route('admin.plan-templates.index'), null],
    ];
@endphp
<h1 class="h-page">Billing</h1>
<p class="text-muted mt-1">Subscriptions, project invoices and what is owed, in one place.</p>

<dl class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="card py-4">
        <dt class="text-muted text-sm">Monthly recurring</dt>
        <dd class="mt-1 text-2xl font-semibold">{{ \App\Support\Money::format($sum['monthly']) }}</dd>
        <dd class="text-muted text-xs">excl. VAT</dd>
    </div>
    <div class="card py-4">
        <dt class="text-muted text-sm">Billing next 30 days</dt>
        <dd class="mt-1 text-2xl font-semibold">{{ \App\Support\Money::format($sum['upcoming']) }}</dd>
        <dd class="text-muted text-xs">from subscriptions, excl. VAT</dd>
    </div>
    <a href="{{ route('admin.invoices.index', ['status' => 'overdue']) }}" class="card block py-4 {{ $sum['overdue'] > 0 ? 'border-red-200 bg-red-50 hover:border-red-300' : 'hover:border-line-strong' }}">
        <dt class="text-sm {{ $sum['overdue'] > 0 ? 'text-red-900' : 'text-muted' }}">Overdue</dt>
        <dd class="mt-1 text-2xl font-semibold {{ $sum['overdue'] > 0 ? 'text-red-900' : '' }}">{{ \App\Support\Money::format($sum['overdue']) }}</dd>
        <dd class="text-xs {{ $sum['overdue'] > 0 ? 'text-red-800' : 'text-muted' }}">{{ trans_choice(':count bill|:count bills', $sum['overdueCount']) }}</dd>
    </a>
    <div class="card py-4">
        <dt class="text-muted text-sm">Paid this month</dt>
        <dd class="mt-1 text-2xl font-semibold">{{ \App\Support\Money::format($sum['paidThisMonth']) }}</dd>
        <dd class="text-muted text-xs">confirmed payments</dd>
    </div>
</dl>

<nav aria-label="Billing sections" class="border-line mt-8 border-b">
    <div class="-mb-px flex gap-1 overflow-x-auto">
        @foreach ($tabs as $key => [$label, $url, $count])
            <a href="{{ $url }}" @if ($tab === $key) aria-current="page" @endif
               class="flex min-h-12 items-center gap-2 border-b-2 px-4 font-medium whitespace-nowrap {{ $tab === $key ? 'border-brand text-ink' : 'border-transparent text-muted hover:text-ink' }}">
                {{ $label }}
                @if ($count)<span class="bg-sand-dark text-muted rounded-full px-2 py-0.5 text-xs font-semibold">{{ $count }}</span>@endif
            </a>
        @endforeach
    </div>
</nav>
