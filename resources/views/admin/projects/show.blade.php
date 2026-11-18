@php
    $client = $project->client;
    $total = $project->milestones->count();
    $done = $project->milestones->where('status', 'done')->count();
@endphp
<x-layouts.app :title="$project->number.' '.$project->title">
    <a href="{{ route('admin.projects.index') }}" class="link inline-flex min-h-11 items-center text-sm">Back to projects</a>

    <div x-data="{ editing: {{ $errors->has('title') || $errors->has('due_date') ? 'true' : 'false' }} }">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-muted text-sm">
                    {{ $project->number }} · <a href="{{ route('admin.clients.show', $client) }}" class="link">{{ $client->business_name }}</a>
                    @if ($project->quote) · Quote <a href="{{ route('admin.quotes.show', $project->quote) }}" class="link">{{ $project->quote->reference() }}</a>@endif
                    @if ($project->serviceRequest) · <a href="{{ route('admin.requests.show', $project->serviceRequest) }}" class="link">{{ $project->serviceRequest->number }}</a>@endif
                </p>
                <h1 class="h-page">{{ $project->title }}</h1>
                <p class="text-muted mt-1">
                    {{ $project->due_date ? 'Due '.$project->due_date->format('j M Y') : 'No due date' }}
                    @if ($total) · {{ $done }} of {{ $total }} steps done @endif
                </p>
            </div>
            <div class="flex items-center gap-2">
                <x-status :status="$project->status" :label="$project->statusLabel()" />
                <button type="button" class="btn btn-light min-h-10 px-4 text-sm" @click="editing = !editing" :aria-expanded="editing.toString()" x-text="editing ? 'Close' : 'Edit'">Edit</button>
            </div>
        </div>

        <form x-show="editing" x-cloak method="POST" action="{{ route('admin.projects.update', $project) }}" class="card mt-4 grid gap-4 sm:grid-cols-2">
            @csrf
            @method('PUT')
            <div class="sm:col-span-2"><label for="p-title" class="label">Name</label><input id="p-title" name="title" required maxlength="200" value="{{ $project->title }}" class="field"></div>
            <div>
                <label for="p-status" class="label">Status</label>
                <select id="p-status" name="status" class="field">
                    @foreach (\App\Models\Project::STATUSES as $k => $l)
                        <option value="{{ $k }}" @selected($project->status === $k)>{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div><label for="p-due" class="label">Due date</label><input id="p-due" type="date" name="due_date" value="{{ $project->due_date?->format('Y-m-d') }}" class="field"></div>
            @if ($project->milestones->isEmpty())
                <div><label for="p-progress" class="label">Progress (%)</label><input id="p-progress" type="number" min="0" max="100" name="progress" value="{{ $project->progress }}" class="field"></div>
            @endif
            <div class="sm:col-span-2"><label for="p-desc" class="label">Scope <span class="text-muted font-normal">(only you see this)</span></label><textarea id="p-desc" name="description" rows="3" maxlength="5000" class="field">{{ $project->description }}</textarea></div>
            <div class="flex gap-2 sm:col-span-2">
                <button type="submit" class="btn btn-dark">Save</button>
                <button type="button" class="btn btn-light" @click="editing = false">Cancel</button>
            </div>
        </form>
    </div>

    <div class="bg-sand-dark mt-4 h-2 overflow-hidden rounded-full" role="progressbar" aria-label="Progress" aria-valuenow="{{ $project->progress }}" aria-valuemin="0" aria-valuemax="100">
        <div class="bg-brand h-2 rounded-full" style="width: {{ $project->progress }}%"></div>
    </div>

    @if ($project->start_reason)
        <p class="bg-sand-dark mt-4 rounded-xl px-4 py-3 text-sm">Started by hand by {{ $project->starter?->name ?? 'the team' }}: {{ $project->start_reason }}</p>
    @endif
    @if ($client->users->isEmpty())
        <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            This client has no portal login, so they will not see messages or approvals. <a href="{{ route('admin.clients.show', $client) }}" class="font-semibold underline">Give them access</a>, or keep notes here for yourself.
        </p>
    @endif

    @include('partials.project-layout', [
        'admin' => true,
        'feedUrl' => route('admin.projects.messages', $project),
        'postUrl' => route('admin.projects.messages.store', $project),
        'uploadUrl' => route('admin.projects.files', $project),
        'closed' => false,
    ])
</x-layouts.app>
