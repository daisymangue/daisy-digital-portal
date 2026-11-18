<x-layouts.app title="Projects">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="h-page">Projects</h1>
            <p class="text-muted mt-1">A project opens by itself when a deposit or full invoice is paid. You can also start one by hand. <a href="{{ route('admin.step-sets.index') }}" class="link">Step sets</a></p>
        </div>
        <details class="w-full sm:w-auto">
            <summary class="btn btn-primary cursor-pointer list-none">Start a project without a quote</summary>
            <form method="POST" action="{{ route('admin.projects.store') }}" class="card mt-3 space-y-4 sm:w-96">
                @csrf
                <div>
                    <label for="new-client" class="label">Client</label>
                    <select id="new-client" name="client_id" required class="field">
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}">{{ $c->business_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="new-title" class="label">Project name</label>
                    <input id="new-title" name="title" required maxlength="200" class="field" placeholder="For example Website care, March">
                </div>
                <div>
                    <label for="new-reason" class="label">Why there is no quote or paid deposit</label>
                    <input id="new-reason" name="start_reason" required maxlength="500" class="field" placeholder="For example agreed by phone, deposit waived">
                </div>
                <button type="submit" class="btn btn-dark w-full">Start project</button>
                <p class="text-muted text-sm">For quoted work, open the accepted quote instead so the project links to it.</p>
            </form>
        </details>
    </div>

    <form method="GET" class="mt-6 flex flex-wrap items-center gap-2" role="search">
        <label for="status" class="sr-only">Show</label>
        <select id="status" name="status" class="field w-auto" onchange="this.form.submit()">
            <option value="open" @selected($status === 'open')>Open projects</option>
            @foreach (\App\Models\Project::STATUSES as $key => $label)
                <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
            @endforeach
            <option value="all" @selected($status === 'all')>All projects</option>
        </select>
        <label for="q" class="sr-only">Search projects</label>
        <input id="q" name="q" type="search" value="{{ $search }}" placeholder="Search number, name or client" class="field min-w-0 flex-1 sm:max-w-xs">
        <button type="submit" class="btn btn-dark">Search</button>
    </form>

    <div class="mt-6 space-y-3">
        @forelse ($projects as $p)
            <a href="{{ route('admin.projects.show', $p) }}" class="card hover:border-line-strong grid items-center gap-4 sm:grid-cols-[1fr_12rem_auto]">
                <div class="min-w-0">
                    <p class="font-semibold">{{ $p->number }} · {{ $p->title }}</p>
                    <p class="text-muted text-sm">{{ $p->client->business_name }}@if ($p->due_date) · due {{ $p->due_date->format('j M Y') }}@endif</p>
                    @if ($p->unread_count || $p->submitted_count || $p->needed_count)
                        <div class="mt-2 flex flex-wrap gap-2 text-xs">
                            @if ($p->unread_count)<span class="bg-brand rounded-full px-2.5 py-1 font-semibold text-white">{{ trans_choice(':count unread message|:count unread messages', $p->unread_count) }}</span>@endif
                            @if ($p->submitted_count)<span class="rounded-full bg-blue-100 px-2.5 py-1 font-semibold text-blue-900">{{ $p->submitted_count }} sent by client to check</span>@endif
                            @if ($p->needed_count)<span class="rounded-full bg-amber-100 px-2.5 py-1 font-semibold text-amber-900">waiting on {{ $p->needed_count }} from client</span>@endif
                        </div>
                    @endif
                </div>
                @include('partials.project-progress', ['project' => $p])
                <x-status :status="$p->status" :label="$p->statusLabel()" />
            </a>
        @empty
            <div class="card text-center">
                <p class="font-medium">No projects here.</p>
                <p class="text-muted mt-1">When a client pays a deposit, their project shows up here.</p>
            </div>
        @endforelse
    </div>
    <div class="mt-6">{{ $projects->links() }}</div>
</x-layouts.app>
