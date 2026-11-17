@props(['id', 'max' => 8, 'label' => 'Add files', 'hint' => 'Images, PDFs, Word, Excel, PowerPoint or zip. Up to 8 files, 10 MB each.'])
{{--
    Drag-and-drop file picker with previews. It keeps a real <input type="file" name="files[]">
    in sync, so the form posts normally. Several of these on one form post into the same files[] list.
--}}
<div x-data="{
        files: [],
        error: '',
        over: false,
        max: {{ (int) $max }},
        types: ['jpg','jpeg','png','gif','webp','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','zip'],
        add(list) {
            this.error = '';
            for (const f of list) {
                const ext = f.name.split('.').pop().toLowerCase();
                if (!this.types.includes(ext)) { this.error = f.name + ' is not a supported file type.'; continue }
                if (f.size > 10 * 1024 * 1024) { this.error = f.name + ' is larger than 10 MB. For videos, share a link in your message instead.'; continue }
                if (this.files.length >= this.max) { this.error = 'You can add up to ' + this.max + ' files.'; break }
                if (this.files.some(x => x.file.name === f.name && x.file.size === f.size)) continue;
                this.files.push({ file: f, url: f.type.startsWith('image/') ? URL.createObjectURL(f) : null });
            }
            this.sync();
        },
        remove(i) { if (this.files[i].url) URL.revokeObjectURL(this.files[i].url); this.files.splice(i, 1); this.sync() },
        sync() { const dt = new DataTransfer(); this.files.forEach(x => dt.items.add(x.file)); this.$refs.input.files = dt.files },
        size(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB' },
     }">
    <span class="label">{{ $label }} <span class="text-muted font-normal">(optional)</span></span>
    <label for="{{ $id }}"
           class="flex min-h-28 cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed px-4 py-5 text-center transition-colors"
           :class="over ? 'border-brand bg-pink-50' : 'border-line-strong bg-sand hover:bg-sand-dark'"
           @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="over = false; add($event.dataTransfer.files)">
        <svg class="text-brand size-7" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 16V4m0 0l-4 4m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span class="font-semibold">Drop files here or tap to choose</span>
        <span class="text-muted text-sm">On a phone you can also take a photo.</span>
    </label>
    <input id="{{ $id }}" x-ref="input" type="file" name="files[]" multiple class="sr-only" @change="add([...$event.target.files])"
           accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,image/*">
    <p class="text-muted mt-1.5 text-sm">{{ $hint }}</p>
    <p x-show="error" x-cloak x-text="error" role="alert" class="mt-1.5 text-sm text-red-800"></p>

    <ul x-show="files.length" x-cloak class="mt-3 grid gap-2 sm:grid-cols-2">
        <template x-for="(f, i) in files" :key="f.file.name + f.file.size">
            <li class="border-line flex items-center gap-3 rounded-xl border bg-white p-2 pr-3">
                <template x-if="f.url"><img :src="f.url" alt="" class="size-12 shrink-0 rounded-lg object-cover"></template>
                <template x-if="!f.url"><span class="bg-sand-dark text-muted flex size-12 shrink-0 items-center justify-center rounded-lg text-xs font-semibold uppercase" x-text="f.file.name.split('.').pop()"></span></template>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium" x-text="f.file.name"></span>
                    <span class="text-muted block text-xs" x-text="size(f.file.size)"></span>
                </span>
                <button type="button" @click="remove(i)" class="min-h-11 cursor-pointer px-2 text-sm text-red-800 underline-offset-2 hover:underline" :aria-label="'Remove ' + f.file.name">Remove</button>
            </li>
        </template>
    </ul>
</div>
