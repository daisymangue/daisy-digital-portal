{{--
    Project chat. Loads with the messages already on the page, then asks for new ones every
    10 seconds while the tab is visible. Sending works without JavaScript too (normal form post).
    Needs: $project, $messages, $feedUrl, $postUrl, $closed
--}}
<section id="chat" class="card flex flex-col p-0 sm:p-0"
    x-data="{
        messages: @js($messages),
        body: '',
        error: '',
        sending: false,
        timer: null,
        lastId() { return this.messages.length ? this.messages[this.messages.length - 1].id : 0 },
        scroll() { this.$nextTick(() => { const b = this.$refs.list; b.scrollTop = b.scrollHeight }) },
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
                window.dispatchEvent(new CustomEvent('files-cleared', { detail: form }));
                this.scroll();
            } catch (e) {
                this.error = 'That did not send. Check your connection and try again.';
            } finally {
                this.sending = false;
            }
        },
    }"
    x-init="scroll(); timer = setInterval(() => poll(), 10000); document.addEventListener('visibilitychange', () => poll())">
    <div class="border-line flex items-center justify-between gap-3 border-b px-5 py-4 sm:px-6">
        <h2 class="h-section">Messages</h2>
        <p class="text-muted text-sm">{{ auth()->user()->isAdmin() ? 'The client sees everything here.' : 'Our team usually replies within one working day.' }}</p>
    </div>

    <div x-ref="list" class="max-h-[28rem] min-h-48 space-y-4 overflow-y-auto px-5 py-5 sm:px-6" aria-live="polite">
        <template x-if="!messages.length">
            <p class="text-muted py-10 text-center">No messages yet. {{ auth()->user()->isAdmin() ? 'Say hello and set expectations.' : 'Ask us anything about your project here.' }}</p>
        </template>
        <template x-for="m in messages" :key="m.id">
            <div class="flex" :class="m.mine ? 'justify-end' : 'justify-start'">
                <div class="max-w-[85%] sm:max-w-[75%]">
                    <p class="text-muted mb-1 text-xs" :class="m.mine && 'text-right'"><span x-text="m.author"></span> · <span x-text="m.time"></span></p>
                    <div class="rounded-2xl px-4 py-3" :class="m.mine ? 'bg-navy text-white rounded-br-md' : 'bg-sand-dark text-ink rounded-bl-md'">
                        <p class="break-words whitespace-pre-line" x-show="m.body" x-text="m.body"></p>
                        <template x-for="f in m.files">
                            <a :href="f.url" class="mt-2 flex items-center gap-2 rounded-xl px-3 py-2 text-sm underline-offset-2 hover:underline" :class="m.mine ? 'bg-white/10' : 'bg-white'">
                                <span aria-hidden="true">📎</span><span class="break-all" x-text="f.name"></span><span class="opacity-70" x-text="'(' + f.size + ')'"></span>
                            </a>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @if ($closed)
        <p class="border-line text-muted border-t px-5 py-4 sm:px-6">This project is closed, so messages are read only.</p>
    @else
        <form method="POST" action="{{ $postUrl }}" enctype="multipart/form-data" class="border-line space-y-3 border-t px-5 py-4 sm:px-6" @submit.prevent="send($el)">
            @csrf
            <label for="chat-body" class="sr-only">Your message</label>
            <textarea id="chat-body" name="body" rows="3" maxlength="5000" x-model="body" class="field" placeholder="Write a message"
                @keydown.ctrl.enter.prevent="send($el.form)" @keydown.meta.enter.prevent="send($el.form)"></textarea>
            <p x-show="error" x-cloak x-text="error" class="text-sm font-medium text-red-800" role="alert"></p>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1"><x-file-drop id="chat-files" :max="5" :compact="true" label="Attach" /></div>
                <button type="submit" class="btn btn-primary" :disabled="sending" x-text="sending ? 'Sending…' : 'Send message'">Send message</button>
            </div>
        </form>
    @endif
</section>
