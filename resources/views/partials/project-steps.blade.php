{{--
    The left half of the project page: the steps, with reviews and "what we need" inside the step
    they belong to, then the handover and any extra work asked for.
    Needs: $project, $admin (bool), and $stepSets when $admin.
--}}
@php
    $current = $project->currentMilestone();
    $needs = $project->checklistByStep();
    $loose = $project->milestones->isEmpty() ? ($needs[0] ?? []) : [];
    $checkIcon = '<svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>';
@endphp
{{-- On a phone only the current step shows; the rest fold away behind "Show all steps". --}}
<section class="card p-0 sm:p-0" x-data="{ adding: false, all: false }">
    <div class="border-line flex min-h-14 items-center justify-between gap-3 border-b px-5">
        <div class="min-w-0">
            <h2 class="h-section">Steps</h2>
            @if ($project->milestones->isNotEmpty())
                <p class="text-muted text-xs lg:hidden">{{ $current ? 'Step '.($project->milestones->search(fn ($m) => $m->id === $current->id) + 1).' of '.$project->milestones->count() : 'All '.$project->milestones->count().' steps done' }}</p>
            @endif
        </div>
        <div class="flex items-center gap-3">
            @if ($project->milestones->count() > 1)
                <button type="button" class="link min-h-11 cursor-pointer text-sm lg:hidden" @click="all = !all" :aria-expanded="all.toString()" x-text="all ? 'Show less' : 'Show all {{ $project->milestones->count() }}'">Show all</button>
            @endif
            @if ($admin && $project->milestones->isNotEmpty())
                <button type="button" class="link min-h-11 cursor-pointer text-sm" @click="adding = !adding" x-text="adding ? 'Close' : '+ Add step'">+ Add step</button>
            @endif
        </div>
    </div>

    @if ($admin)
        <form x-show="adding" x-cloak method="POST" action="{{ route('admin.projects.milestones.store', $project) }}" class="bg-sand border-line space-y-2 border-b px-5 py-4">
            @csrf
            <label for="m-title" class="label">New step</label>
            <input id="m-title" name="title" required maxlength="200" class="field" placeholder="For example Homepage design">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="needs_approval" value="1" class="accent-navy size-5"> Client must approve</label>
                <button type="submit" class="btn btn-dark min-h-10 px-4 text-sm">Add step</button>
            </div>
        </form>
    @endif

    @if ($project->milestones->isEmpty())
        <div class="px-5 py-6">
            @if ($admin)
                <p class="font-medium">No steps yet</p>
                <p class="text-muted mt-1 text-sm">Pick a ready-made set, then rename or remove steps to fit. <a href="{{ route('admin.step-sets.index') }}" class="link">Manage step sets</a></p>
                <form method="POST" action="{{ route('admin.projects.milestones.store', $project) }}" class="mt-3 flex flex-wrap gap-2">
                    @csrf
                    <label for="step-set" class="sr-only">Step set</label>
                    <select id="step-set" name="step_set_id" required class="field w-auto flex-1">
                        @foreach ($stepSets as $set)
                            <option value="{{ $set->id }}">{{ $set->name }} ({{ count($set->steps) }} steps)</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-dark">Use these steps</button>
                </form>
                <button type="button" class="link mt-2 min-h-11 cursor-pointer text-sm" @click="adding = true">Or add your own step</button>
            @else
                <p class="text-muted">We will set out the steps shortly.</p>
            @endif
            @if ($loose)
                <div class="mt-4">@include('partials.project-needs', ['items' => $loose, 'step' => null])</div>
            @endif
        </div>
    @else
        <ol class="px-5">
            @foreach ($project->milestones as $m)
                @php
                    $isCurrent = $current && $current->id === $m->id;
                    $items = $needs[$m->id] ?? [];
                    $label = match ($m->status) {
                        'awaiting_approval' => $admin ? 'Waiting for client' : 'Ready for you to review',
                        'changes_requested' => $admin ? 'Changes asked for' : 'You asked for changes',
                        'in_progress' => 'In progress',
                        default => null,
                    };
                @endphp
                <li class="border-line gap-3 border-b py-4 last:border-b-0 {{ $isCurrent || ! $current ? 'flex' : '' }}" @unless ($isCurrent || ! $current) :class="all ? 'flex' : 'hidden lg:flex'" @endunless x-data="{ edit: false }" id="step-{{ $m->id }}">
                    @if ($m->status === 'done')
                        <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-800">{!! $checkIcon !!}<span class="sr-only">Done</span></span>
                    @elseif ($isCurrent)
                        <span class="bg-brand mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white">{{ $loop->iteration }}</span>
                    @else
                        <span class="border-line-strong text-muted mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-semibold">{{ $loop->iteration }}</span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                            <p class="{{ $isCurrent ? 'font-semibold' : 'font-medium' }} {{ ! $isCurrent && $m->status !== 'done' ? 'text-muted' : '' }}">
                                {{ $m->title }}
                                @if ($admin && $m->needs_approval)<span class="text-xs font-semibold text-violet-800">· client approves</span>@endif
                            </p>
                            <div class="flex items-center gap-2">
                                @if ($label)<x-status :status="$m->status" :label="$label" />@endif
                                @if ($admin)<button type="button" class="link min-h-9 cursor-pointer text-sm" @click="edit = !edit" x-text="edit ? 'Close' : 'Edit'">Edit</button>@endif
                            </div>
                        </div>

                        @if ($m->status === 'done')
                            <p class="text-muted text-sm">
                                Done{{ $m->approved_at ? ', approved by '.$m->approved_name.' '.$m->approved_at->format('j M') : '' }}
                            </p>
                        @elseif ($m->due_date)
                            <p class="text-muted text-sm">By {{ $m->due_date->format('j M') }}</p>
                        @endif

                        @if ($m->status === 'changes_requested' && $m->client_feedback)
                            <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950">{{ $admin ? 'Client' : 'You' }}: "{{ $m->client_feedback }}"</p>
                        @endif

                        {{-- The client reviews right here --}}
                        @if (! $admin && in_array($m->status, ['awaiting_approval', 'changes_requested'], true))
                            <div class="mt-3" x-data="{ mode: '' }">
                                <p class="text-sm" x-show="!mode">{{ $m->status === 'changes_requested' ? 'We are working on your changes. Happy with it as it is after all? You can still approve it.' : 'Have a look at what we shared in the chat or files, then approve it or tell us what to change.' }}</p>
                                <div class="mt-3 flex flex-wrap gap-2" x-show="!mode">
                                    <button type="button" class="btn {{ $m->status === 'changes_requested' ? 'btn-light' : 'btn-primary' }} min-h-10 px-4" @click="mode = 'approve'">{{ $m->status === 'changes_requested' ? 'Approve as it is' : 'Approve' }}</button>
                                    @if ($m->status === 'awaiting_approval')
                                        <button type="button" class="btn btn-light min-h-10 px-4" @click="mode = 'changes'">Ask for changes</button>
                                    @endif
                                </div>
                                <form x-show="mode === 'approve'" x-cloak method="POST" action="{{ route('projects.milestones.approve', [$project->id, $m->id]) }}" class="space-y-3">
                                    @csrf
                                    <label for="approve-{{ $m->id }}" class="label">Type your name to approve</label>
                                    <input id="approve-{{ $m->id }}" name="approved_name" required maxlength="120" value="{{ auth()->user()->name }}" class="field">
                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-primary min-h-10 px-4">Approve</button>
                                        <button type="button" class="btn btn-light min-h-10 px-4" @click="mode = ''">Back</button>
                                    </div>
                                </form>
                                <form x-show="mode === 'changes'" x-cloak method="POST" action="{{ route('projects.milestones.changes', [$project->id, $m->id]) }}" class="space-y-3">
                                    @csrf
                                    <label for="changes-{{ $m->id }}" class="label">What would you like changed?</label>
                                    <textarea id="changes-{{ $m->id }}" name="client_feedback" rows="3" required minlength="5" maxlength="3000" class="field" placeholder="Be as specific as you can, for example colours, wording or pages"></textarea>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="submit" class="btn btn-dark min-h-10 px-4">Send feedback</button>
                                        <button type="button" class="btn btn-light min-h-10 px-4" @click="mode = ''">Back</button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        @if ($items || ($admin && $isCurrent))
                            <div class="mt-3">@include('partials.project-needs', ['items' => $items, 'step' => $m])</div>
                        @endif

                        @if ($admin)
                            {{-- Everyday actions on the current step --}}
                            @if ($isCurrent)
                                <div class="mt-3 flex flex-wrap gap-2" x-data="{ posting: false }">
                                    @if (in_array($m->status, ['pending', 'in_progress', 'changes_requested'], true))
                                        <form method="POST" action="{{ route('admin.projects.milestones.update', [$project, $m]) }}">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="title" value="{{ $m->title }}">
                                            <input type="hidden" name="due_date" value="{{ $m->due_date?->format('Y-m-d') }}">
                                            <input type="hidden" name="needs_approval" value="{{ $m->needs_approval ? 1 : 0 }}">
                                            <input type="hidden" name="status" value="done">
                                            <button type="submit" class="btn btn-dark min-h-10 px-4 text-sm">{{ $m->needs_approval ? 'Send for approval' : 'Mark step done' }}</button>
                                        </form>
                                    @endif
                                    <button type="button" class="btn btn-light min-h-10 px-4 text-sm" @click="posting = !posting">Post an update</button>
                                    <form x-show="posting" x-cloak method="POST" action="{{ route('admin.projects.updates.store', $project) }}" class="basis-full space-y-2">
                                        @csrf
                                        <label for="update-body" class="sr-only">Progress update</label>
                                        <textarea id="update-body" name="body" rows="3" required minlength="3" maxlength="5000" class="field" placeholder="What got done, what is next, anything the client should know">{{ old('body') }}</textarea>
                                        <button type="submit" class="btn btn-dark min-h-10 px-4 text-sm">Post in the chat and email the client</button>
                                    </form>
                                </div>
                            @endif

                            <form x-show="edit" x-cloak method="POST" action="{{ route('admin.projects.milestones.update', [$project, $m]) }}" class="bg-sand mt-3 space-y-2 rounded-xl p-3">
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
                                <label class="flex min-h-11 items-center gap-2 text-sm"><input type="checkbox" name="needs_approval" value="1" @checked($m->needs_approval) class="accent-navy size-5"> Client must approve</label>
                                <div class="flex gap-2">
                                    <button type="submit" class="btn btn-dark min-h-10 flex-1 px-4 text-sm">Save step</button>
                                    <button type="submit" form="del-m-{{ $m->id }}" class="btn btn-light min-h-10 px-4 text-sm text-red-800">Remove</button>
                                </div>
                            </form>
                            <form id="del-m-{{ $m->id }}" method="POST" action="{{ route('admin.projects.milestones.destroy', [$project, $m]) }}" onsubmit="return confirm('Remove this step?')">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    {{-- Handover: the end of the road --}}
    {{-- The handover stays in view on a phone only while the client still has to confirm it. --}}
    @php
        $fold = $project->milestones->count() > 1;
        $handoverNeedsYou = ! $admin && $project->handover_sent_at && ! $project->handover_confirmed_at;
    @endphp
    <div @if ($fold && ! $handoverNeedsYou) :class="all ? '' : 'hidden lg:block'" @endif>
        @if ($admin)
            @include('partials.project-handover-admin')
        @elseif ($project->handover_sent_at)
            @include('partials.project-handover-client')
        @endif
    </div>

    <div @if ($fold) :class="all ? '' : 'hidden lg:block'" @endif>
        @include('partials.project-changes')
    </div>

    @if ($fold)
        <button type="button" x-show="!all" class="border-line text-brand-dark flex min-h-12 w-full cursor-pointer items-center justify-center border-t text-sm font-semibold lg:hidden" @click="all = true">
            Show all steps{{ $project->changeRequests->isNotEmpty() || ! $admin ? ' and extras' : '' }}
        </button>
    @endif
</section>
