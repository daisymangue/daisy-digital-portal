{{--
    Project chat. Loads with the messages already on the page, then asks for new ones every
    10 seconds while the page is visible. Sending works without JavaScript too (normal form post).
    Progress updates from the team show as highlighted cards in the same timeline.
    The files button in the corner opens every file on the project, with an upload box.
    Needs: $project, $messages, $files, $feedUrl, $postUrl, $uploadUrl, $closed
--}}
@php $isAdmin = auth()->user()->isAdmin(); @endphp
<section id="chat" class="card relative flex h-[72vh] min-h-[26rem] flex-col overflow-hidden p-0 sm:p-0 lg:h-[calc(100vh-13rem)] lg:min-h-[30rem]" @chat-shown.window="scroll()"
    x-data="{
        messages: @js($messages),
        body: '',
        error: '',
        sending: false,
        filesOpen: {{ $errors->has('files') || $errors->has('files.0') ? 'true' : 'false' }},
        lastId() { return this.messages.length ? this.messages[this.messages.length - 1].id : 0 },
        scroll() { this.$nextTick(() => { const b = this.$refs.list; b.scrollTop = b.scrollHeight }) },
        grow(t) { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 160) + 'px' },
        async poll() {
            if (document.hidden) return;
            try {
                const r = await fetch('{{ $feedUrl }}?after=' + this.lastId(), { headers: { Accept: 'application/json' } });
                if (!r.ok) return;
                const data = await r.json();
                if (data.messages.length) { this.add(data.messages); this.scroll() }
            } catch (e) {}
        },
        add(list) { for (const m of list) if (!this.messages.some(x => x.id === m.id)) this.messages.push(m) },
        async send(form) {
            this.error = '';
            const fd = new FormData(form);
            if (!this.body.trim() && !form.querySelector('input[type=file]').files.length) { this.error = 'Write a message or attach a file.'; return }
            this.sending = true;
            try {
                const r = await fetch('{{ $postUrl }}', { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
                const data = await r.json();
                if (!r.ok) { this.error = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'That did not send. Please try again.'); return }
                this.add([data.message]);
                this.body = '';
                form.reset();
                this.$refs.box.style.height = '';
                window.dispatchEvent(new CustomEvent('files-cleared', { detail: form }));
                this.scroll();
            } catch (e) {
                this.error = 'That did not send. Check your connection and try again.';
            } finally {
                this.sending = false;
            }
        },
    }"
    x-init="scroll(); setInterval(() => poll(), 10000); document.addEventListener('visibilitychange', () => poll())">
    <div class="border-line flex items-center justify-between gap-3 border-b py-2 pr-2 pl-5">
        <div class="min-w-0">
            <h2 class="font-semibold">Chat</h2>
            <p class="text-muted truncate text-xs">{{ $isAdmin ? 'The client sees everything here' : 'We usually reply within a working day' }}</p>
        </div>
        <button type="button" @click="filesOpen = !filesOpen" :aria-expanded="filesOpen.toString()" aria-controls="chat-files-panel"
                class="hover:bg-sand-dark relative flex min-h-11 shrink-0 cursor-pointer items-center gap-1.5 rounded-full px-3 text-sm font-medium"
                :class="filesOpen ? 'bg-sand-dark text-ink' : 'text-muted'">
            <svg class="size-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3.75 3A1.75 1.75 0 0 0 2 4.75v3.26a3.24 3.24 0 0 1 1.75-.51h12.5c.64 0 1.24.19 1.75.51V6.75A1.75 1.75 0 0 0 16.25 5h-4.84a.25.25 0 0 1-.18-.07L9.82 3.51A1.75 1.75 0 0 0 8.59 3H3.75ZM3.75 9A1.75 1.75 0 0 0 2 10.75v4.5c0 .97.78 1.75 1.75 1.75h12.5c.97 0 1.75-.78 1.75-1.75v-4.5A1.75 1.75 0 0 0 16.25 9H3.75Z"/></svg>
            <span>Files</span>
            <span class="bg-sand-dark text-muted rounded-full px-1.5 text-xs font-semibold" :class="filesOpen && 'bg-white'">{{ $files->count() }}</span>
        </button>
    </div>

    {{-- Every file on the project, over the chat --}}
    <div id="chat-files-panel" x-show="filesOpen" x-cloak x-transition.opacity class="absolute inset-x-0 top-[3.75rem] bottom-0 z-10 flex flex-col bg-white">
        <div class="flex items-center justify-between px-5 pt-4">
            <p class="font-semibold">All files <span class="text-muted font-normal">({{ $files->count() }})</span></p>
            <button type="button" @click="filesOpen = false" class="link min-h-11 cursor-pointer text-sm">Back to chat</button>
        </div>
        <div class="min-h-0 flex-1 overflow-y-auto px-5 pb-2">
            @if ($files->isEmpty())
                <p class="text-muted py-8 text-center">No files yet. Anything shared in the chat, in the steps or uploaded here shows up in this list.</p>
            @else
                <ul class="divide-line divide-y">
                    @foreach ($files as $f)
                        <li class="flex items-center gap-3 py-2.5">
                            <span class="bg-sand-dark text-muted flex size-10 shrink-0 items-center justify-center rounded-lg text-[0.65rem] font-bold uppercase">{{ Str::limit(pathinfo($f->original_name, PATHINFO_EXTENSION), 4, '') }}</span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('files.download', $f) }}" class="link block truncate">{{ $f->original_name }}</a>
                                <p class="text-muted text-xs">{{ $f->sizeLabel() }} · {{ $f->uploader?->isAdmin() ? 'Daisy Digital' : ($f->uploader?->name ?? 'Unknown') }} · {{ $f->created_at->format('j M') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
        @unless ($closed)
            <form method="POST" action="{{ $uploadUrl }}" enctype="multipart/form-data" class="border-line flex flex-wrap items-center justify-between gap-3 border-t px-5 py-3">
                @csrf
                <x-file-drop id="panel-files" :compact="true" label="Choose files" />
                <button type="submit" class="btn btn-dark min-h-10 px-4 text-sm">Upload</button>
            </form>
        @endunless
    </div>

    <div x-ref="list" class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-5" aria-live="polite">
        <template x-if="!messages.length">
            <p class="text-muted py-10 text-center">No messages yet. {{ $isAdmin ? 'Say hello and set expectations.' : 'Ask us anything about your project here.' }}</p>
        </template>
        <template x-for="m in messages" :key="m.id">
            <div>
                <template x-if="m.update">
                    <div class="rounded-2xl border border-violet-200 bg-violet-50 px-4 py-3">
                        <p class="text-xs font-semibold text-violet-900">Progress update · <span class="font-normal" x-text="m.time"></span></p>
                        <p class="mt-1 break-words whitespace-pre-line text-violet-950" x-text="m.body"></p>
                        <template x-for="f in m.files">
                            <a :href="f.url" class="mt-2 flex items-center gap-2 rounded-xl bg-white px-3 py-2 text-sm underline-offset-2 hover:underline">
                                <span class="break-all" x-text="f.name"></span><span class="text-muted shrink-0 whitespace-nowrap" x-text="f.size"></span>
                            </a>
                        </template>
                    </div>
                </template>
                <template x-if="!m.update">
                    <div class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
                        <div class="max-w-[85%]">
                            <p class="text-muted mb-1 text-xs" :class="m.mine && 'text-right'"><span x-text="m.author"></span> · <span x-text="m.time"></span></p>
                            <div class="rounded-2xl px-4 py-2.5" :class="m.mine ? 'bg-navy text-white rounded-br-md' : 'bg-sand-dark text-ink rounded-bl-md'">
                                <p class="break-words whitespace-pre-line" x-show="m.body" x-text="m.body"></p>
                                <template x-for="f in m.files">
                                    <a :href="f.url" class="mt-2 flex items-center gap-2 rounded-xl px-3 py-2 text-sm underline-offset-2 hover:underline" :class="m.mine ? 'bg-white/10' : 'bg-white'">
                                        <svg class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.5 2A1.5 1.5 0 0 0 3 3.5v13A1.5 1.5 0 0 0 4.5 18h11a1.5 1.5 0 0 0 1.5-1.5V7.62a1.5 1.5 0 0 0-.44-1.06l-4.12-4.12A1.5 1.5 0 0 0 11.38 2H4.5Z" clip-rule="evenodd"/></svg>
                                        <span class="break-all" x-text="f.name"></span><span class="shrink-0 whitespace-nowrap opacity-70" x-text="f.size"></span>
                                    </a>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    @if ($closed)
        <p class="border-line text-muted border-t px-5 py-4">This project is closed, so messages are read only.</p>
    @else
        <form method="POST" action="{{ $postUrl }}" enctype="multipart/form-data" class="border-line flex flex-wrap items-end gap-2 border-t p-3" @submit.prevent="send($el)">
            @csrf
            <x-file-drop id="chat-files" :max="5" :icon="true" label="Attach files" />
            <label for="chat-body" class="sr-only">Your message</label>
            <textarea id="chat-body" x-ref="box" name="body" rows="1" maxlength="5000" x-model="body" class="field min-w-0 flex-1 resize-none" placeholder="Write a message"
                @input="grow($el)" @keydown.ctrl.enter.prevent="send($el.form)" @keydown.meta.enter.prevent="send($el.form)"></textarea>
            <button type="submit" class="btn btn-primary shrink-0 px-5" :disabled="sending" x-text="sending ? 'Sending…' : 'Send'">Send</button>
            <p x-show="error" x-cloak x-text="error" class="order-last basis-full text-sm font-medium text-red-800" role="alert"></p>
        </form>
    @endif
</section>
