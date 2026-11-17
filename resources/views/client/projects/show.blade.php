@php
    $needed = $project->checklist->where('status', '!=', 'accepted');
    $openCount = $project->checklist->where('status', 'open')->count();
    $tabs = [
        'messages' => ['Messages', $unread, 'Chat'],
        'progress' => ['Progress', 0, 'Progress'],
        'needed' => ['What we need', $openCount, 'Needed'],
        'files' => ['Files', 0, 'Files'],
    ];
    $default = $openCount ? 'needed' : 'messages';
    $toApprove = $project->milestones->where('status', 'awaiting_approval');
@endphp
<x-layouts.app :title="$project->number.' '.$project->title">
    <a href="{{ route('projects.index') }}" class="link text-sm">Back to projects</a>

    <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-muted text-sm">{{ $project->number }}</p>
            <h1 class="h-page">{{ $project->title }}</h1>
            <p class="text-muted mt-1">
                Started {{ $project->start_date?->format('j F Y') ?? $project->created_at->format('j F Y') }}
                @if ($project->due_date) · Aiming to finish by {{ $project->due_date->format('j F Y') }} @endif
            </p>
        </div>
        <x-status :status="$project->status" :label="$project->clientStatusLabel()" />
    </div>
    <div class="mt-5 max-w-xl">@include('partials.project-progress')</div>

    @if ($toApprove->isNotEmpty())
        <section class="mt-8 space-y-4">
            @foreach ($toApprove as $m)
                <div class="rounded-2xl border border-violet-200 bg-violet-50 p-5 sm:p-6" x-data="{ mode: '' }">
                    <p class="text-sm font-semibold text-violet-900">Ready for you to review</p>
                    <h2 class="mt-1 text-lg font-semibold">{{ $m->title }}</h2>
                    <p class="mt-1 text-violet-950">Look at what we sent in messages or files, then approve it or tell us what to change.</p>
                    <div class="mt-4 flex flex-wrap gap-3" x-show="!mode">
                        <button type="button" class="btn btn-primary" @click="mode = 'approve'">Approve</button>
                        <button type="button" class="btn btn-light" @click="mode = 'changes'">Ask for changes</button>
                    </div>
                    <form x-show="mode === 'approve'" x-cloak method="POST" action="{{ route('projects.milestones.approve', [$project->id, $m->id]) }}" class="mt-4 max-w-md space-y-3">
                        @csrf
                        <label for="approve-{{ $m->id }}" class="label">Type your name to approve</label>
                        <input id="approve-{{ $m->id }}" name="approved_name" required maxlength="120" value="{{ auth()->user()->name }}" class="field">
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="btn btn-primary">Approve "{{ Str::limit($m->title, 30) }}"</button>
                            <button type="button" class="btn btn-light" @click="mode = ''">Back</button>
                        </div>
                    </form>
                    <form x-show="mode === 'changes'" x-cloak method="POST" action="{{ route('projects.milestones.changes', [$project->id, $m->id]) }}" class="mt-4 space-y-3">
                        @csrf
                        <label for="changes-{{ $m->id }}" class="label">What would you like changed?</label>
                        <textarea id="changes-{{ $m->id }}" name="client_feedback" rows="4" required minlength="5" maxlength="3000" class="field" placeholder="Be as specific as you can, for example colours, wording or pages"></textarea>
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="btn btn-dark">Send feedback</button>
                            <button type="button" class="btn btn-light" @click="mode = ''">Back</button>
                        </div>
                    </form>
                </div>
            @endforeach
        </section>
    @endif


    <div x-data="{
            tab: '{{ $default }}',
            keys: @js(array_keys($tabs)),
            show(k) {
                this.tab = k;
                try { localStorage.setItem('ptab-{{ $project->id }}', k) } catch (e) {}
                history.replaceState(null, '', '#' + k);
                if (k === 'messages') this.$dispatch('chat-shown');
            },
            move(d) {
                const i = (this.keys.indexOf(this.tab) + d + this.keys.length) % this.keys.length;
                this.show(this.keys[i]);
                this.$nextTick(() => document.getElementById('tab-' + this.tab).focus());
            },
            init() {
                let saved = null;
                try { saved = localStorage.getItem('ptab-{{ $project->id }}') } catch (e) {}
                const h = location.hash.slice(1) === 'chat' ? 'messages' : location.hash.slice(1);
                if (this.keys.includes(h)) this.tab = h; else if (saved && this.keys.includes(saved)) this.tab = saved;
                if (this.tab === 'messages') this.$nextTick(() => this.$dispatch('chat-shown'));
            },
        }">
        @include('partials.project-tabs')

        <div class="mt-6">
            <div id="panel-messages" role="tabpanel" aria-labelledby="tab-messages" x-show="tab === 'messages'">
                @include('partials.project-chat', [
                    'feedUrl' => route('projects.messages', $project->id),
                    'postUrl' => route('projects.messages.store', $project->id),
                    'closed' => $project->status === 'cancelled',
                ])
            </div>

            <div id="panel-progress" role="tabpanel" aria-labelledby="tab-progress" x-show="tab === 'progress'" x-cloak class="grid gap-6 lg:grid-cols-5">
                <section class="card lg:col-span-2">
                    <h2 class="h-section">Steps</h2>
                    @include('partials.project-steps')
                    @if ($project->quote)
                        <div class="border-line mt-5 border-t pt-4 text-sm">
                            <a href="{{ route('quotes.show', $project->quote_id) }}" class="link">Quote {{ $project->quote->reference() }}</a>
                            · <a href="{{ route('invoices.index') }}" class="link">Your invoices</a>
                        </div>
                    @endif
                </section>
                <section class="card lg:col-span-3">
                    <h2 class="h-section">Progress updates</h2>
                    @forelse ($project->updates as $u)
                        <article class="border-line mt-4 border-t pt-4 first-of-type:mt-3 first-of-type:border-0 first-of-type:pt-0">
                            <p class="text-muted text-sm">{{ $u->created_at->format('j F Y') }} · {{ $u->user->name }}</p>
                            <p class="mt-1 whitespace-pre-line">{{ $u->body }}</p>
                        </article>
                    @empty
                        <p class="text-muted mt-2">We will post progress updates here as the work moves along.</p>
                    @endforelse
                </section>
            </div>

            <div id="panel-needed" role="tabpanel" aria-labelledby="tab-needed" x-show="tab === 'needed'" x-cloak>
            @if ($needed->isNotEmpty())
                <section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6">
                    <h2 class="h-section text-amber-950">What we need from you</h2>
                    <p class="mt-1 text-amber-950">We can move faster once we have these. Send each one when it is ready.</p>
                    <ul class="mt-4 space-y-3">
                        @foreach ($needed as $item)
                            <li class="rounded-xl border border-amber-200 bg-white p-4" x-data="{ open: {{ $loop->first && $item->status === 'open' ? 'true' : 'false' }} }">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold">{{ $item->title }}</p>
                                        @if ($item->note)<p class="text-muted text-sm">{{ $item->note }}</p>@endif
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <x-status :status="$item->status" :label="$item->statusLabel()" />
                                        <button type="button" class="link min-h-11 cursor-pointer" @click="open = !open" :aria-expanded="open.toString()">{{ $item->status === 'submitted' ? 'Send more' : 'Send it' }}</button>
                                    </div>
                                </div>
                                @if ($item->client_reply || $item->files->isNotEmpty())
                                    <div class="bg-sand mt-3 rounded-xl p-3 text-sm">
                                        @if ($item->client_reply)<p class="whitespace-pre-line">{{ $item->client_reply }}</p>@endif
                                        @foreach ($item->files as $f)
                                            <a href="{{ route('files.download', $f) }}" class="link mt-1 block break-all">{{ $f->original_name }}</a>
                                        @endforeach
                                    </div>
                                @endif
                                <form x-show="open" x-cloak method="POST" action="{{ route('projects.checklist.submit', [$project->id, $item->id]) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                                    @csrf
                                    <div>
                                        <label for="reply-{{ $item->id }}" class="label">Reply or link <span class="text-muted font-normal">(optional if you add a file)</span></label>
                                        <textarea id="reply-{{ $item->id }}" name="client_reply" rows="2" maxlength="3000" class="field" placeholder="For example a Google Drive link, a login, or a note"></textarea>
                                    </div>
                                    <x-file-drop :id="'item-files-'.$item->id" />
                                    <button type="submit" class="btn btn-dark">Send "{{ Str::limit($item->title, 30) }}"</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-sm text-amber-950">Please do not send passwords in plain text. Ask us for a safe way to share logins.</p>
                </section>
            @else
                <div class="card text-center">
                    <p class="font-medium">Nothing needed from you right now.</p>
                    <p class="text-muted mt-1">When we need content, logins or approvals, they will show here.</p>
                </div>
            @endif
            </div>

            <div id="panel-files" role="tabpanel" aria-labelledby="tab-files" x-show="tab === 'files'" x-cloak class="grid gap-6 lg:grid-cols-5">
                <section class="card lg:col-span-3">
                    <h2 class="h-section">All files</h2>
                    <p class="text-muted mt-1 mb-3 text-sm">Everything shared in messages, in "What we need" and uploaded here.</p>
                    @include('partials.project-files')
                </section>
                @unless ($project->isClosed())
                    <form method="POST" action="{{ route('projects.files', $project->id) }}" enctype="multipart/form-data" class="card space-y-3 lg:col-span-2">
                        @csrf
                        <x-file-drop id="project-files" label="Share files with us" />
                        <button type="submit" class="btn btn-dark w-full">Upload</button>
                    </form>
                @endunless
            </div>
        </div>
    </div>
</x-layouts.app>
