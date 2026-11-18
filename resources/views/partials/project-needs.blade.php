{{--
    "What we need" for one step. The client sends each item from here; the team checks them here.
    Needs: $project, $admin, $items (array of ProjectChecklistItem), $step (ProjectMilestone or null)
--}}
@php
    $waiting = collect($items)->where('status', '!=', 'accepted');
    $received = collect($items)->where('status', 'accepted');
@endphp
<div class="rounded-xl border {{ $waiting->isNotEmpty() ? 'border-amber-200 bg-amber-50' : 'border-line bg-sand' }} p-3" x-data="{ asking: false }">
    @if ($waiting->isNotEmpty() || $received->isNotEmpty())
        <p class="text-sm font-semibold {{ $waiting->isNotEmpty() ? 'text-amber-900' : 'text-muted' }}">
            @if ($waiting->isNotEmpty())
                {{ $admin ? 'Waiting on the client' : 'We need from you' }} ({{ $waiting->count() }})
            @else
                {{ $admin ? 'Received from the client' : 'You sent everything for this step' }}
            @endif
        </p>
        <ul class="mt-1 divide-y {{ $waiting->isNotEmpty() ? 'divide-amber-200/70' : 'divide-line' }}">
            @foreach ($waiting->concat($received) as $item)
                <li class="py-2" x-data="{ open: false }">
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                        <div class="min-w-0">
                            <p class="text-sm {{ $item->status === 'accepted' ? 'text-muted line-through decoration-1' : 'font-medium' }}">{{ $item->title }}</p>
                            @if ($item->note)<p class="text-muted text-xs">{{ $item->note }}</p>@endif
                        </div>
                        <div class="flex items-center gap-2">
                            @if ($item->status === 'submitted')
                                <x-status status="submitted" :label="$admin ? 'Sent, check it' : 'Sent, we are checking'" />
                            @elseif ($item->status === 'accepted')
                                <span class="text-xs font-semibold text-green-800">Received</span>
                            @elseif ($admin)
                                <span class="text-muted text-xs">Not sent yet</span>
                            @endif
                            @if (! $admin && $item->status !== 'accepted')
                                <button type="button" class="link min-h-9 cursor-pointer text-sm" @click="open = !open" :aria-expanded="open.toString()" x-text="open ? 'Close' : '{{ $item->status === 'submitted' ? 'Send more' : 'Send' }}'">Send</button>
                            @endif
                        </div>
                    </div>

                    @if ($item->client_reply || $item->files->isNotEmpty())
                        <div class="mt-2 rounded-lg bg-white px-3 py-2 text-sm">
                            @if ($item->client_reply)<p class="break-words whitespace-pre-line">{{ $item->client_reply }}</p>@endif
                            @foreach ($item->files as $f)
                                <a href="{{ route('files.download', $f) }}" class="link mt-1 block break-all">{{ $f->original_name }}</a>
                            @endforeach
                        </div>
                    @endif

                    @if ($admin)
                        <div class="flex flex-wrap gap-x-4 text-sm">
                            @if ($item->status === 'submitted')
                                <form method="POST" action="{{ route('admin.projects.checklist.status', [$project, $item]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="accepted"><button class="link min-h-9 cursor-pointer">Mark received</button></form>
                                <form method="POST" action="{{ route('admin.projects.checklist.status', [$project, $item]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="open"><button class="link min-h-9 cursor-pointer">Ask again</button></form>
                            @elseif ($item->status === 'open')
                                <form method="POST" action="{{ route('admin.projects.checklist.status', [$project, $item]) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="accepted"><button class="text-muted min-h-9 cursor-pointer underline-offset-2 hover:underline">Got it another way</button></form>
                            @endif
                            @if ($item->status !== 'accepted')
                                <form method="POST" action="{{ route('admin.projects.checklist.destroy', [$project, $item]) }}" onsubmit="return confirm('Remove this item?')">@csrf @method('DELETE')<button class="min-h-9 cursor-pointer text-red-800 underline-offset-2 hover:underline">Remove</button></form>
                            @endif
                        </div>
                    @else
                        <form x-show="open" x-cloak method="POST" action="{{ route('projects.checklist.submit', [$project->id, $item->id]) }}" enctype="multipart/form-data" class="mt-2 space-y-3 rounded-lg bg-white p-3">
                            @csrf
                            <div>
                                <label for="reply-{{ $item->id }}" class="label">Note or link <span class="text-muted font-normal">(optional if you add a file)</span></label>
                                <textarea id="reply-{{ $item->id }}" name="client_reply" rows="2" maxlength="3000" class="field" placeholder="For example a Google Drive link or a short note"></textarea>
                            </div>
                            <x-file-drop :id="'item-files-'.$item->id" />
                            <p class="text-muted text-xs">Please do not send passwords here. Ask us in the chat for a safe way to share logins.</p>
                            <button type="submit" class="btn btn-dark min-h-10 px-4">Send "{{ Str::limit($item->title, 30) }}"</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($admin)
        <button type="button" class="link min-h-9 cursor-pointer text-sm" @click="asking = !asking" x-show="!asking">+ Ask the client for something</button>
        <form x-show="asking" x-cloak method="POST" action="{{ route('admin.projects.checklist.store', $project) }}" class="mt-2 space-y-2">
            @csrf
            @if ($step)<input type="hidden" name="milestone_id" value="{{ $step->id }}">@endif
            <label for="items-{{ $step?->id ?? 'x' }}" class="label">What do you need?{{ $step ? ' For "'.Str::limit($step->title, 30).'"' : '' }}</label>
            <textarea id="items-{{ $step?->id ?? 'x' }}" name="items" rows="3" required maxlength="3000" class="field" placeholder="One per line, for example&#10;Logo in high quality&#10;Photos of the shop"></textarea>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-dark min-h-10 px-4 text-sm">Add and email the client</button>
                <button type="button" class="btn btn-light min-h-10 px-4 text-sm" @click="asking = false">Cancel</button>
            </div>
        </form>
    @endif
</div>
