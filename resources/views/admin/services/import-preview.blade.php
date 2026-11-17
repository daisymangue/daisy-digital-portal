@php
    $changes = count($diff['new']) + count($diff['changed']) + count($diff['shown_again']) + count($diff['missing']);
@endphp
<x-layouts.app title="Check the import">
    <a href="{{ route('admin.services.import') }}" class="link text-sm">Upload a different file</a>
    <h1 class="h-page mt-3">Check the changes</h1>
    <p class="text-muted mt-1">From {{ $fileName }}. Nothing has changed yet.</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <div class="card"><p class="text-muted text-sm">New services</p><p class="mt-1 text-3xl font-semibold">{{ count($diff['new']) }}</p></div>
        <div class="card"><p class="text-muted text-sm">Price changes</p><p class="mt-1 text-3xl font-semibold">{{ count($diff['changed']) }}</p></div>
        <div class="card"><p class="text-muted text-sm">Shown again</p><p class="mt-1 text-3xl font-semibold">{{ count($diff['shown_again']) }}</p></div>
        <div class="card"><p class="text-muted text-sm">Not in the sheet</p><p class="mt-1 text-3xl font-semibold">{{ count($diff['missing']) }}</p></div>
        <div class="card"><p class="text-muted text-sm">Unchanged</p><p class="mt-1 text-3xl font-semibold">{{ $diff['unchanged'] }}</p></div>
    </div>

    @if ($errors_in_sheet)
        <section class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-950">
            <h2 class="h-section">{{ count($errors_in_sheet) }} {{ Str::plural('row', count($errors_in_sheet)) }} will be skipped</h2>
            <ul class="mt-3 space-y-1">
                @foreach ($errors_in_sheet as $e)
                    <li>Sheet "{{ $e['sheet'] }}", row {{ $e['row'] }}: {{ $e['message'] }}</li>
                @endforeach
            </ul>
            <p class="mt-3 text-sm">Fix them in Excel and upload again, or carry on without them.</p>
        </section>
    @endif

    <div class="mt-6 space-y-6">
        @if ($diff['new_categories'])
            <section class="card">
                <h2 class="h-section">New categories</h2>
                <p class="mt-2">{{ implode(', ', $diff['new_categories']) }}</p>
            </section>
        @endif

        @if ($diff['changed'])
            <section class="card overflow-x-auto">
                <h2 class="h-section">Price changes</h2>
                <table class="mt-3 w-full min-w-[520px] text-left">
                    <thead class="text-muted text-sm"><tr><th scope="col" class="py-2 font-medium">Service</th><th scope="col" class="py-2 font-medium">Now</th><th scope="col" class="py-2 font-medium">After import</th></tr></thead>
                    <tbody class="divide-line divide-y">
                        @foreach ($diff['changed'] as $row)
                            <tr><td class="py-2.5 pr-3">{{ $row['name'] }}<div class="text-muted text-sm">{{ $row['category'] }}</div></td><td class="text-muted py-2.5 pr-3">{{ $row['old_label'] }}</td><td class="py-2.5 font-semibold">{{ $row['new_label'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if ($diff['new'])
            <section class="card">
                <h2 class="h-section">New services</h2>
                <ul class="divide-line mt-2 divide-y">
                    @foreach ($diff['new'] as $row)
                        <li class="flex flex-wrap justify-between gap-3 py-2.5"><span>{{ $row['name'] }} <span class="text-muted text-sm">· {{ $row['category'] }}</span></span><span class="text-muted">{{ \App\Support\Money::label($row['price_cents'], $row['is_from'], $row['unit']) }}</span></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($diff['shown_again'])
            <section class="card">
                <h2 class="h-section">Hidden now, shown again after import</h2>
                <p class="mt-2">{{ collect($diff['shown_again'])->pluck('name')->implode(', ') }}</p>
            </section>
        @endif

        @if ($diff['missing'])
            <section class="card">
                <h2 class="h-section">Live now but not in the sheet</h2>
                <p class="mt-2">{{ collect($diff['missing'])->map(fn ($m) => $m['name'].' ('.$m['category'].')')->implode(', ') }}</p>
            </section>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.services.import.apply') }}" class="card mt-6 space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @if ($diff['missing'])
            <label class="flex items-start gap-3">
                <input type="checkbox" name="hide_missing" value="1" class="accent-navy mt-0.5 size-5 shrink-0" checked>
                <span>Hide the {{ count($diff['missing']) }} {{ Str::plural('service', count($diff['missing'])) }} that are not in the sheet. <span class="text-muted">Untick to leave them as they are.</span></span>
            </label>
        @endif
        @if ($changes === 0)
            <p>Everything in the sheet already matches the portal. There is nothing to apply.</p>
            <a href="{{ route('admin.services.index') }}" class="btn btn-light">Back to services</a>
        @else
            <button type="submit" class="btn btn-primary">Apply these changes</button>
        @endif
    </form>
</x-layouts.app>
