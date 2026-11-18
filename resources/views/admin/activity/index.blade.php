<x-layouts.app title="Activity log">
    <a href="{{ route('admin.dashboard') }}" class="link text-sm">Back to dashboard</a>
    <h1 class="h-page mt-3">Activity log</h1>
    <p class="text-muted mt-1">Who did what, and when. Kept for 3 years.</p>

    <form method="GET" class="mt-6 flex flex-wrap items-center gap-2" role="search">
        <label for="group" class="sr-only">Show</label>
        <select id="group" name="group" class="field w-auto" onchange="this.form.submit()">
            <option value="">Everything</option>
            @foreach (\App\Http\Controllers\Admin\ActivityController::GROUPS as $key => [$label])
                <option value="{{ $key }}" @selected($group === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <label for="who" class="sr-only">By</label>
        <select id="who" name="who" class="field w-auto" onchange="this.form.submit()">
            <option value="">By anyone</option>
            @foreach ($people as $person)
                <option value="{{ $person->id }}" @selected((string) $who === (string) $person->id)>{{ $person->name }}{{ $person->isAdmin() ? ' (team)' : '' }}</option>
            @endforeach
            <option value="system" @selected($who === 'system')>Automatic (system)</option>
        </select>
        <label for="q" class="sr-only">Search activity</label>
        <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Search, for example INV-0004" class="field min-w-0 flex-1 sm:max-w-xs">
        <button type="submit" class="btn btn-dark">Search</button>
        @if ($group || $who || $search)
            <a href="{{ route('admin.activity') }}" class="link flex min-h-11 items-center px-1">Clear</a>
        @endif
    </form>

    <div class="card mt-6 overflow-x-auto p-0 sm:p-0">
        <table class="w-full min-w-[640px] text-left">
            <thead class="text-muted text-sm">
                <tr>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">When</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">What</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Who</th>
                </tr>
            </thead>
            <tbody class="divide-line divide-y">
                @forelse ($logs as $log)
                    <tr>
                        <td class="text-muted px-5 py-3 text-sm whitespace-nowrap" title="{{ $log->created_at->format('j M Y, H:i:s') }}">{{ $log->created_at->isToday() ? 'Today '.$log->created_at->format('H:i') : $log->created_at->format('j M Y, H:i') }}</td>
                        <td class="px-5 py-3">
                            {{ $log->label() }}
                            @if ($log->reference())
                                · @if ($log->url())<a href="{{ $log->url() }}" class="link">{{ $log->reference() }}</a>@else{{ $log->reference() }}@endif
                            @elseif ($log->url())
                                · <a href="{{ $log->url() }}" class="link">Open</a>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-sm">{{ $log->user?->name ?? 'Automatic' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted px-5 py-8 text-center">Nothing matches.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-6">{{ $logs->links() }}</div>
</x-layouts.app>
