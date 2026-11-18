<x-layouts.app title="Clients">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="h-page">Clients</h1>
        <a href="{{ route('admin.clients.create') }}" class="btn btn-primary">Add a client</a>
    </div>

    <form method="GET" class="mt-6 flex flex-wrap items-center gap-2" role="search">
        <label for="q" class="sr-only">Search clients</label>
        <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Search business, name or email" class="field min-w-0 flex-1 sm:max-w-xs">
        @if ($archived)<input type="hidden" name="archived" value="1">@endif
        <button type="submit" class="btn btn-dark">Search</button>
        @if ($archived)
            <a href="{{ route('admin.clients.index') }}" class="link flex min-h-11 items-center">Back to active clients</a>
        @elseif ($archivedCount)
            <a href="{{ route('admin.clients.index', ['archived' => 1]) }}" class="link flex min-h-11 items-center">Archived ({{ $archivedCount }})</a>
        @endif
    </form>

    <div class="card mt-6 overflow-x-auto p-0 sm:p-0">
        <table class="w-full min-w-[600px] text-left">
            <thead class="text-muted text-sm">
                <tr>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Business</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Contact</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Requests</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-line divide-y">
                @forelse ($clients as $client)
                    <tr>
                        <td class="px-5 py-4"><a href="{{ route('admin.clients.show', $client) }}" class="link font-semibold">{{ $client->business_name }}</a></td>
                        <td class="px-5 py-4">{{ $client->contactName() }}<div class="text-muted text-sm">{{ $client->contactEmail() ?? $client->phone }}</div>@if ($client->users->isEmpty())<div class="text-muted text-xs">No portal login</div>@endif</td>
                        <td class="px-5 py-4">{{ $client->service_requests_count }}</td>
                        <td class="px-5 py-4">{{ $client->created_at->format('j M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted px-5 py-8 text-center">No clients yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $clients->links() }}</div>
</x-layouts.app>
