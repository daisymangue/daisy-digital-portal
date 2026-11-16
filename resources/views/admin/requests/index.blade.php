<x-layouts.app title="Requests">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="h-page">Requests</h1>
        <a href="{{ route('admin.requests.create') }}" class="btn btn-primary">New request for a client</a>
    </div>

    <form method="GET" class="mt-6 flex flex-wrap items-end gap-3">
        <div class="w-full sm:w-72">
            <label for="q" class="label">Search</label>
            <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Number, client or email" class="field">
        </div>
        <div class="w-full sm:w-64">
            <label for="status" class="label">Status</label>
            <select id="status" name="status" class="field">
                <option value="">All statuses</option>
                @foreach (\App\Models\ServiceRequest::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-dark">Filter</button>
        @if ($search !== '' || $status)
            <a href="{{ route('admin.requests.index') }}" class="link min-h-11 content-center">Clear</a>
        @endif
    </form>

    <div class="card mt-6 overflow-x-auto p-0 sm:p-0">
        <table class="w-full min-w-[640px] text-left">
            <thead class="text-muted text-sm">
                <tr>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Request</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Client</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Asked for</th>
                    <th scope="col" class="border-line border-b px-5 py-3 font-medium">Status</th>
                </tr>
            </thead>
            <tbody class="divide-line divide-y">
                @forelse ($requests as $r)
                    <tr>
                        <td class="px-5 py-4">
                            <a href="{{ route('admin.requests.show', $r) }}" class="link font-semibold">{{ $r->number }}</a>
                            <div class="text-muted text-sm">{{ $r->submitted_at->format('j M Y, H:i') }}</div>
                        </td>
                        <td class="px-5 py-4">{{ $r->client->business_name }}<div class="text-muted text-sm">{{ $r->user->name }}</div></td>
                        <td class="px-5 py-4">{{ $r->package_name ?: trans_choice(':count service|:count services', $r->services_count) }}</td>
                        <td class="px-5 py-4"><x-status :status="$r->status" :label="$r->statusLabel()" /></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted px-5 py-8 text-center">No requests match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-6">{{ $requests->links() }}</div>
</x-layouts.app>
