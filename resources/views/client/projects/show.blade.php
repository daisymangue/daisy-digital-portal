@php
    $needed = $project->checklist->where('status', '!=', 'accepted');
    $openCount = $project->checklist->where('status', 'open')->count();
    $tabs = [
        'messages' => ['Messages', $unread, 'Chat'],
        'progress' => ['Progress', 0, 'Progress'],
        'needed' => ['What we need', $openCount, 'Needed'],
        'files' => ['Files', 0, 'Files'],
    ];
    $handoverWaiting = $project->handover_sent_at && (! $project->handover_confirmed_at || $project->testimonial?->status === 'requested');
    if ($project->handover_sent_at) {
        if (! $openCount && $needed->isEmpty()) {
            unset($tabs['needed']);
        }
        $tabs['handover'] = ['Handover', $handoverWaiting ? 1 : 0, 'Handover'];
    }
    $default = $handoverWaiting ? 'handover' : ($openCount ? 'needed' : 'messages');
    $toApprove = $project->milestones->whereIn('status', ['awaiting_approval', 'changes_requested']);
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
                    @if ($m->status === 'changes_requested')
                        <p class="text-sm font-semibold text-violet-900">You asked for changes</p>
                        <h2 class="mt-1 text-lg font-semibold">{{ $m->title }}</h2>
                        <p class="mt-1 text-violet-950">"{{ $m->client_feedback }}" We are working on it. Happy with it as it is after all? You can still approve it.</p>
                        <div class="mt-4 flex flex-wrap gap-3" x-show="!mode">
                            <button type="button" class="btn btn-light" @click="mode = 'approve'">Approve as it is</button>
                        </div>
                    @else
                        <p class="text-sm font-semibold text-violet-900">Ready for you to review</p>
                        <h2 class="mt-1 text-lg font-semibold">{{ $m->title }}</h2>
                        <p class="mt-1 text-violet-950">Look at what we sent in messages or files, then approve it or tell us what to change.</p>
                        <div class="mt-4 flex flex-wrap gap-3" x-show="!mode">
                            <button type="button" class="btn btn-primary" @click="mode = 'approve'">Approve</button>
                            <button type="button" class="btn btn-light" @click="mode = 'changes'">Ask for changes</button>
                        </div>
                    @endif
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

                <section class="card lg:col-span-5" x-data="{ asking: {{ $errors->has('details') || $errors->has('title') ? 'true' : 'false' }} }">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="h-section">Changes and extras</h2>
                            <p class="text-muted text-sm">Need something that was not in the original quote? Ask here and we will quote it, or tell you if it is included.</p>
                        </div>
                        @unless ($project->status === 'cancelled')
                            <button type="button" class="btn btn-light" @click="asking = !asking" x-show="!asking">Ask for a change</button>
                        @endunless
                    </div>
                    <form x-show="asking" x-cloak method="POST" action="{{ route('projects.changes.store', $project->id) }}" enctype="multipart/form-data" class="bg-sand mt-4 space-y-3 rounded-xl p-4">
                        @csrf
                        <div>
                            <label for="change-title" class="label">What is the change?</label>
                            <input id="change-title" name="title" required maxlength="200" value="{{ old('title') }}" class="field" placeholder="For example Add a booking page">
                        </div>
                        <div>
                            <label for="change-details" class="label">Details</label>
                            <textarea id="change-details" name="details" rows="4" required minlength="10" maxlength="5000" class="field" placeholder="What should it do, and is there a deadline?">{{ old('details') }}</textarea>
                        </div>
                        <x-file-drop id="change-files" label="Examples or files" />
                        <div class="flex flex-wrap gap-3">
                            <button type="submit" class="btn btn-dark">Send change request</button>
                            <button type="button" class="btn btn-light" @click="asking = false">Cancel</button>
                        </div>
                    </form>
                    @if ($project->changeRequests->isNotEmpty())
                        <ul class="divide-line mt-4 divide-y">
                            @foreach ($project->changeRequests as $c)
                                <li class="py-3">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <p class="font-medium">{{ $c->title }} <span class="text-muted text-sm font-normal">· {{ $c->created_at->format('j M') }}</span></p>
                                        <x-status :status="$c->status === 'new' ? 'reviewing' : ($c->status === 'included' ? 'accepted' : $c->status)" :label="$c->clientStatusLabel()" />
                                    </div>
                                    @if ($c->team_reply)<p class="text-muted mt-1 text-sm">Our note: {{ $c->team_reply }}</p>@endif
                                    @if ($c->quote && $c->quote->status !== 'draft')
                                        <a href="{{ route('quotes.show', $c->quote_id) }}" class="link mt-1 inline-block text-sm">View quote {{ $c->quote->reference() }}</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
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

            @if ($project->handover_sent_at)
                <div id="panel-handover" role="tabpanel" aria-labelledby="tab-handover" x-show="tab === 'handover'" x-cloak class="grid items-start gap-6 lg:grid-cols-5">
                    <section class="card lg:col-span-3">
                        <h2 class="h-section">Your handover pack</h2>
                        <p class="text-muted mt-1 text-sm">Everything you need to look after your work. Keep a copy somewhere safe.</p>
                        @if ($project->handover_notes)
                            <div class="bg-sand mt-4 rounded-xl p-4 whitespace-pre-line">{{ $project->handover_notes }}</div>
                        @endif
                        @if ($project->handoverFiles->isNotEmpty())
                            <ul class="divide-line mt-4 divide-y">
                                @foreach ($project->handoverFiles as $f)
                                    <li class="py-2.5"><a href="{{ route('files.download', $f) }}" class="link break-all">{{ $f->original_name }}</a> <span class="text-muted text-xs">{{ $f->sizeLabel() }}</span></li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <div class="space-y-6 lg:col-span-2">
                        @if (! $project->handover_confirmed_at)
                            <form method="POST" action="{{ route('projects.handover.confirm', $project->id) }}" class="card space-y-3 border-violet-200 bg-violet-50">
                                @csrf
                                <h2 class="h-section">Got everything?</h2>
                                <p class="text-violet-950">Look through the pack. If anything is missing, tell us in Messages. Otherwise confirm below.</p>
                                <label for="confirmed_name" class="label">Type your name to confirm</label>
                                <input id="confirmed_name" name="confirmed_name" required maxlength="120" value="{{ auth()->user()->name }}" class="field">
                                <button type="submit" class="btn btn-primary w-full">I have received everything</button>
                            </form>
                        @else
                            <div class="card">
                                <p class="font-medium">Handover confirmed</p>
                                <p class="text-muted text-sm">By {{ $project->handover_confirmed_name }} on {{ $project->handover_confirmed_at->format('j F Y') }}.</p>
                            </div>
                        @endif

                        @if ($project->testimonial?->status === 'requested')
                            <form method="POST" action="{{ route('projects.testimonial', $project->id) }}" class="card space-y-4" x-data="{ rating: {{ (int) old('rating', 0) }} }">
                                @csrf
                                <h2 class="h-section">How did we do?</h2>
                                <fieldset>
                                    <legend class="label">Your rating</legend>
                                    <div class="flex gap-1">
                                        @for ($i = 1; $i <= 5; $i++)
                                            <label class="cursor-pointer">
                                                <input type="radio" name="rating" value="{{ $i }}" x-model.number="rating" class="peer sr-only" required>
                                                <span class="flex size-11 items-center justify-center rounded-lg text-3xl peer-focus-visible:outline-2 peer-focus-visible:outline-[var(--color-brand)]"
                                                      :class="rating >= {{ $i }} ? 'text-amber-500' : 'text-line-strong'" aria-hidden="true">★</span>
                                                <span class="sr-only">{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}</span>
                                            </label>
                                        @endfor
                                    </div>
                                    @error('rating')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
                                </fieldset>
                                <div>
                                    <label for="t-body" class="label">A few words about working with us <span class="text-muted font-normal">(optional)</span></label>
                                    <textarea id="t-body" name="body" rows="4" maxlength="2000" class="field">{{ old('body') }}</textarea>
                                </div>
                                <div>
                                    <label for="t-name" class="label">Name to show with it</label>
                                    <input id="t-name" name="display_name" maxlength="120" value="{{ old('display_name', auth()->user()->name.', '.$project->client->business_name) }}" class="field">
                                </div>
                                <label class="flex items-start gap-3 text-sm">
                                    <input type="checkbox" name="can_publish" value="1" class="accent-navy mt-0.5 size-5 shrink-0" @checked(old('can_publish', true))>
                                    <span>You may share this on your website and social media.</span>
                                </label>
                                <button type="submit" class="btn btn-dark w-full">Send feedback</button>
                            </form>
                        @elseif ($project->testimonial && $project->testimonial->status !== 'requested')
                            <div class="card">
                                <p class="font-medium">Thank you for your feedback</p>
                                <p class="mt-1 text-2xl text-amber-500" aria-label="{{ $project->testimonial->rating }} out of 5 stars">{{ str_repeat('★', $project->testimonial->rating) }}</p>
                                @if (session('review_prompt'))
                                    <p class="mt-3">Would you also share it on Google? It helps other businesses find us.</p>
                                    <a href="{{ \App\Models\Setting::get('google_review_url') }}" target="_blank" rel="noopener" class="btn btn-primary mt-3">Leave a Google review</a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
