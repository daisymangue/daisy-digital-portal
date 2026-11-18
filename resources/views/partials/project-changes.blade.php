{{-- Extra work asked for during the project. The client asks here; the team quotes, includes or declines it. --}}
@php $changes = $project->changeRequests; @endphp
@if ($changes->isNotEmpty() || (! $admin && $project->status !== 'cancelled'))
<div class="border-line border-t px-5 py-4" x-data="{ asking: {{ $errors->has('details') || $errors->has('title') ? 'true' : 'false' }} }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-semibold">{{ $admin ? 'Extra work asked for' : 'Need something extra?' }}</h3>
        @if (! $admin && $project->status !== 'cancelled')
            <button type="button" class="link min-h-11 cursor-pointer text-sm" @click="asking = !asking" x-text="asking ? 'Close' : 'Ask for a change'">Ask for a change</button>
        @endif
    </div>
    @unless ($admin)
        <p class="text-muted text-sm" x-show="!asking">Something that was not in the quote? Ask and we will quote it, or tell you if it is included.</p>
        <form x-show="asking" x-cloak method="POST" action="{{ route('projects.changes.store', $project->id) }}" enctype="multipart/form-data" class="bg-sand mt-3 space-y-3 rounded-xl p-4">
            @csrf
            <div>
                <label for="change-title" class="label">What is the change?</label>
                <input id="change-title" name="title" required maxlength="200" value="{{ old('title') }}" class="field" placeholder="For example Add a booking page">
            </div>
            <div>
                <label for="change-details" class="label">Details</label>
                <textarea id="change-details" name="details" rows="3" required minlength="10" maxlength="5000" class="field" placeholder="What should it do, and is there a deadline?">{{ old('details') }}</textarea>
            </div>
            <x-file-drop id="change-files" label="Examples or files" />
            <button type="submit" class="btn btn-dark min-h-10 px-4">Send change request</button>
        </form>
    @endunless

    @if ($changes->isNotEmpty())
        <ul class="divide-line mt-2 divide-y">
            @foreach ($changes as $c)
                <li class="py-3" x-data="{ reply: false }">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="min-w-0 text-sm font-medium">{{ $c->title }} <span class="text-muted font-normal">· {{ $c->created_at->format('j M') }}</span></p>
                        <x-status :status="$c->status === 'new' ? ($admin ? 'new' : 'reviewing') : ($c->status === 'included' ? 'accepted' : $c->status)" :label="$admin ? $c->statusLabel() : $c->clientStatusLabel()" />
                    </div>
                    @if ($admin)
                        <p class="mt-1 text-sm whitespace-pre-line">{{ $c->details }}</p>
                        @foreach ($c->files as $f)
                            <a href="{{ route('files.download', $f) }}" class="link mt-1 block text-sm break-all">{{ $f->original_name }}</a>
                        @endforeach
                    @endif
                    @if ($c->team_reply)<p class="text-muted mt-1 text-sm">{{ $admin ? 'Your note' : 'Our note' }}: {{ $c->team_reply }}</p>@endif
                    @if ($c->quote && ($admin || $c->quote->status !== 'draft'))
                        <a href="{{ $admin ? route('admin.quotes.show', $c->quote) : route('quotes.show', $c->quote_id) }}" class="link mt-1 inline-block text-sm">Quote {{ $c->quote->reference() }}{{ $admin ? ' · '.$c->quote->statusLabel() : '' }}</a>
                    @endif
                    @if ($admin)
                        <form method="POST" action="{{ route('admin.projects.changes.answer', [$project, $c]) }}" class="mt-2 space-y-2">
                            @csrf
                            <div x-show="reply" x-cloak>
                                <label for="reply-c-{{ $c->id }}" class="label">Note to the client <span class="text-muted font-normal">(needed when declining)</span></label>
                                <textarea id="reply-c-{{ $c->id }}" name="team_reply" rows="2" maxlength="3000" class="field">{{ $c->team_reply }}</textarea>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @if (! $c->quote_id && $c->status === 'new')
                                    <button name="action" value="quote" class="btn btn-primary min-h-9 px-4 text-sm">Quote it</button>
                                @elseif ($c->quote && $c->quote->status === 'draft')
                                    <a href="{{ route('admin.quotes.edit', $c->quote) }}" class="btn btn-primary min-h-9 px-4 text-sm">Finish the quote</a>
                                @endif
                                @if (in_array($c->status, ['new', 'quoted'], true))
                                    <button name="action" value="included" class="btn btn-light min-h-9 px-4 text-sm" @click="if (!reply) { reply = true; $event.preventDefault() }">Include free</button>
                                    <button name="action" value="declined" class="btn btn-light min-h-9 px-4 text-sm text-red-800" @click="if (!reply) { reply = true; $event.preventDefault() }">Decline</button>
                                @endif
                                @if (in_array($c->status, ['included', 'quoted'], true))
                                    <button name="action" value="done" class="btn btn-light min-h-9 px-4 text-sm">Mark done</button>
                                @endif
                            </div>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endif
