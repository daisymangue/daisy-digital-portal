@php
    $client = $project->client;
@endphp
<x-layouts.app :title="$project->number.' '.$project->title">
    <a href="{{ route('admin.projects.index') }}" class="link text-sm">Back to projects</a>

    <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="h-page">{{ $project->number }} · {{ $project->title }}</h1>
            <p class="text-muted mt-1">
                <a href="{{ route('admin.clients.show', $client) }}" class="link">{{ $client->business_name }}</a>
                @if ($project->quote) · Quote <a href="{{ route('admin.quotes.show', $project->quote) }}" class="link">{{ $project->quote->reference() }}</a> @endif
                @if ($project->serviceRequest) · Request <a href="{{ route('admin.requests.show', $project->serviceRequest) }}" class="link">{{ $project->serviceRequest->number }}</a> @endif
                · Started {{ ($project->start_date ?? $project->created_at)->format('j M Y') }}
            </p>
        </div>
        <x-status :status="$project->status" :label="$project->statusLabel()" />
    </div>
    <div class="mt-5 max-w-xl">@include('partials.project-progress')</div>

    @if ($project->start_reason)
        <p class="bg-sand-dark mt-5 rounded-xl px-4 py-3 text-sm">Started by hand by {{ $project->starter?->name ?? 'the team' }}: {{ $project->start_reason }}</p>
    @endif
    @if ($client->users->isEmpty())
        <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
            This client has no portal login, so they will not see messages or approvals. <a href="{{ route('admin.clients.show', $client) }}" class="font-semibold underline">Give them access</a>, or keep notes here for yourself.
        </p>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @include('partials.project-chat', [
                'feedUrl' => route('admin.projects.messages', $project),
                'postUrl' => route('admin.projects.messages.store', $project),
                'closed' => false,
            ])

            <section class="card">
                <h2 class="h-section">Progress updates</h2>
                <form method="POST" action="{{ route('admin.projects.updates.store', $project) }}" class="mt-3 space-y-3">
                    @csrf
                    <label for="update-body" class="sr-only">New update</label>
                    <textarea id="update-body" name="body" rows="3" required minlength="3" maxlength="5000" class="field" placeholder="What got done, what is next, anything the client should know">{{ old('body') }}</textarea>
                    <button type="submit" class="btn btn-dark">Post update and email the client</button>
                </form>
                @foreach ($project->updates as $u)
                    <article class="border-line mt-4 border-t pt-4">
                        <p class="text-muted text-sm">{{ $u->created_at->format('j M Y, H:i') }} · {{ $u->user->name }}</p>
                        <p class="mt-1 whitespace-pre-line">{{ $u->body }}</p>
                    </article>
                @endforeach
            </section>
        </div>

        <aside class="min-w-0 space-y-6">
            <section class="card" x-data="{ edit: false }">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="h-section">Project</h2>
                    <button type="button" class="link min-h-11 cursor-pointer" @click="edit = !edit" x-text="edit ? 'Close' : 'Edit'">Edit</button>
                </div>
                <dl x-show="!edit" class="mt-2 space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-muted">Status</dt><dd>{{ $project->statusLabel() }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-muted">Due</dt><dd>{{ $project->due_date?->format('j M Y') ?? 'Not set' }}</dd></div>
                    @if ($project->description)<div><dt class="text-muted">Scope</dt><dd class="mt-1 line-clamp-6 whitespace-pre-line">{{ $project->description }}</dd></div>@endif
                </dl>
                <form x-show="edit" x-cloak method="POST" action="{{ route('admin.projects.update', $project) }}" class="mt-3 space-y-3">
                    @csrf
                    @method('PUT')
                    <div><label for="p-title" class="label">Name</label><input id="p-title" name="title" required maxlength="200" value="{{ $project->title }}" class="field"></div>
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
                    @else
                        <p class="text-muted text-sm">Progress follows the steps below.</p>
                    @endif
                    <div><label for="p-desc" class="label">Scope</label><textarea id="p-desc" name="description" rows="4" maxlength="5000" class="field">{{ $project->description }}</textarea></div>
                    <button type="submit" class="btn btn-dark w-full">Save</button>
                </form>
            </section>

            <section class="card">
                <h2 class="h-section">Steps</h2>
                <p class="text-muted mt-1 text-sm">Steps marked for approval go to the client when you set them to Done. Only the client can approve.</p>
                <ol class="mt-4 space-y-3">
                    @foreach ($project->milestones as $m)
                        <li class="border-line rounded-xl border p-3" x-data="{ edit: false }">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-medium">{{ $m->title }} @if ($m->needs_approval)<span class="text-xs font-semibold text-violet-800">· approval</span>@endif</p>
                                    <p class="text-muted text-xs">{{ $m->statusLabel() }}@if ($m->due_date) · {{ $m->due_date->format('j M') }}@endif @if ($m->approved_at) · approved by {{ $m->approved_name }}@endif</p>
                                </div>
                                <button type="button" class="link shrink-0 cursor-pointer text-sm" @click="edit = !edit" x-text="edit ? 'Close' : 'Edit'">Edit</button>
                            </div>
                            @if ($m->status === 'changes_requested' && $m->client_feedback)
                                <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950">Client: {{ $m->client_feedback }}</p>
                            @endif
                            <form x-show="edit" x-cloak method="POST" action="{{ route('admin.projects.milestones.update', [$project, $m]) }}" class="mt-3 space-y-2">
                                @csrf
                                @method('PUT')
                                <input name="title" required maxlength="200" value="{{ $m->title }}" class="field" aria-label="Step name">
                                <div class="grid grid-cols-2 gap-2">
                                    <select name="status" class="field" aria-label="Step status">
                                        @foreach (\App\Models\ProjectMilestone::STATUSES as $k => $l)
                                            <option value="{{ $k }}" @selected($m->status === $k)>{{ $l }}</option>
                                        @endforeach
                                    </select>
                                    <input type="date" name="due_date" value="{{ $m->due_date?->format('Y-m-d') }}" class="field" aria-label="Due date">
                                </div>
                                <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="needs_approval" value="1" @checked($m->needs_approval) class="size-5"> Client must approve</label>
                                <div class="flex gap-2">
                                    <button type="submit" class="btn btn-dark flex-1">Save</button>
                                    <button type="submit" form="del-m-{{ $m->id }}" class="btn btn-light text-red-800">Remove</button>
                                </div>
                            </form>
                            <form id="del-m-{{ $m->id }}" method="POST" action="{{ route('admin.projects.milestones.destroy', [$project, $m]) }}" onsubmit="return confirm('Remove this step?')">
                                @csrf
                                @method('DELETE')
                            </form>
                        </li>
                    @endforeach
                </ol>
                <form method="POST" action="{{ route('admin.projects.milestones.store', $project) }}" class="border-line mt-4 space-y-2 border-t pt-4">
                    @csrf
                    <label for="m-title" class="label">Add a step</label>
                    <input id="m-title" name="title" required maxlength="200" class="field" placeholder="For example Homepage design">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="needs_approval" value="1" class="size-5"> Client must approve</label>
                        <button type="submit" class="btn btn-dark">Add</button>
                    </div>
                </form>
                @if ($project->milestones->isEmpty())
                    <form method="POST" action="{{ route('admin.projects.milestones.store', $project) }}" class="mt-2">
                        @csrf
                        <input type="hidden" name="standard" value="1">
                        <button type="submit" class="btn btn-light w-full">Add the standard 5 steps</button>
                    </form>
                @endif
            </section>

            <section class="card">
                <h2 class="h-section">What we need from the client</h2>
                <ul class="mt-3 space-y-3">
                    @foreach ($project->checklist as $item)
                        <li class="border-line rounded-xl border p-3">
                            <div class="flex items-start justify-between gap-2">
                                <p class="min-w-0 font-medium">{{ $item->title }}</p>
                                <x-status :status="$item->status === 'accepted' ? 'done' : $item->status" :label="$item->statusLabel()" />
                            </div>
                            @if ($item->client_reply)<p class="bg-sand mt-2 rounded-lg px-3 py-2 text-sm whitespace-pre-line">{{ $item->client_reply }}</p>@endif
                            @foreach ($item->files as $f)
                                <a href="{{ route('files.download', $f) }}" class="link mt-1 block text-sm break-all">{{ $f->original_name }}</a>
                            @endforeach
                            <div class="mt-2 flex flex-wrap gap-x-4 text-sm">
                                @if ($item->status !== 'accepted')
                                    <form method="POST" action="{{ route('admin.projects.checklist.status', [$project, $item]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="accepted"><button class="link min-h-11 cursor-pointer">Mark received</button></form>
                                @endif
                                @if ($item->status !== 'open')
                                    <form method="POST" action="{{ route('admin.projects.checklist.status', [$project, $item]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="open"><button class="link min-h-11 cursor-pointer">Ask again</button></form>
                                @endif
                                <form method="POST" action="{{ route('admin.projects.checklist.destroy', [$project, $item]) }}" onsubmit="return confirm('Remove this item?')">@csrf @method('DELETE')<button class="min-h-11 cursor-pointer text-red-800 underline-offset-2 hover:underline">Remove</button></form>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('admin.projects.checklist.store', $project) }}" class="border-line mt-4 space-y-2 border-t pt-4">
                    @csrf
                    <label for="c-items" class="label">Ask for something</label>
                    <textarea id="c-items" name="items" rows="3" required maxlength="3000" class="field" placeholder="One per line, for example&#10;Logo in high quality&#10;Photos of the shop&#10;Text for the About page"></textarea>
                    <button type="submit" class="btn btn-dark w-full">Add and email the client</button>
                </form>
            </section>

            <section class="card space-y-4">
                <h2 class="h-section">Files</h2>
                @include('partials.project-files')
                <form method="POST" action="{{ route('admin.projects.files', $project) }}" enctype="multipart/form-data" class="border-line space-y-3 border-t pt-4">
                    @csrf
                    <x-file-drop id="project-files" label="Share files with the client" />
                    <button type="submit" class="btn btn-dark w-full">Upload</button>
                </form>
            </section>
        </aside>
    </div>
</x-layouts.app>
