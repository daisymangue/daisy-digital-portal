{{--
    The service picker, questions, description and files. Used by the client's "Start a request"
    page and the admin's "New request for a client" page. $admin = true relaxes required questions.
--}}
@php
    $admin = $admin ?? false;
    $catMap = $categories->mapWithKeys(fn ($c) => [$c->id => $c->services->pluck('id')->map(fn ($id) => (string) $id)->all()]);
    $pkgNames = $packages->pluck('name', 'id');
@endphp
<div class="space-y-10"
     x-data="{
         open: null,
         picked: @js(array_map('strval', old('services', []))),
         pkg: @js((string) old('package_id', '')),
         cats: @js($catMap),
         pkgNames: @js($pkgNames),
         count(id) { return this.cats[id].filter(s => this.picked.includes(s)).length },
     }">
    @if ($packages->isNotEmpty())
        <section aria-labelledby="pkg-heading">
            <h2 id="pkg-heading" class="h-section">Packages <span class="text-muted text-base font-normal">(optional)</span></h2>
            <p class="text-muted mt-1">Bundles of our most requested services.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($packages as $package)
                    <label class="relative flex cursor-pointer flex-col rounded-2xl border-2 p-5"
                           :class="pkg === '{{ $package->id }}' ? 'border-navy bg-navy text-white' : 'border-line bg-white'">
                        <input type="radio" name="package_id" value="{{ $package->id }}" x-model="pkg" class="sr-only"
                               @click="if (pkg === '{{ $package->id }}') { $event.preventDefault(); $nextTick(() => pkg = '') }">
                        <span class="font-semibold">{{ $package->name }}</span>
                        <span class="mt-1 text-sm" :class="pkg === '{{ $package->id }}' ? 'text-navy-soft' : 'text-muted'">{{ $package->priceLabel() }}</span>
                        @if ($package->includes)
                            <details class="mt-3 text-sm" @click.stop>
                                <summary class="min-h-8 cursor-pointer underline underline-offset-2">What is included</summary>
                                <ul class="mt-2 list-disc space-y-1 pl-5">
                                    @foreach ($package->includes as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </label>
                @endforeach
            </div>
            <p class="text-muted mt-2 text-sm">Tap a selected package again to unselect it.</p>
        </section>
    @endif

    <section aria-labelledby="svc-heading">
        <h2 id="svc-heading" class="h-section">Individual services</h2>
        <p class="text-muted mt-1">Open a category and tick what is needed. You can choose from more than one.</p>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($categories as $category)
                <button type="button" @click="open = open === {{ $category->id }} ? null : {{ $category->id }}; if (open) $nextTick(() => document.getElementById('cat-{{ $category->id }}').scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                        :aria-expanded="(open === {{ $category->id }}).toString()" aria-controls="cat-{{ $category->id }}"
                        class="flex min-h-20 cursor-pointer flex-col rounded-2xl border-2 p-4 text-left"
                        :class="open === {{ $category->id }} ? 'border-navy bg-navy text-white' : (count({{ $category->id }}) ? 'border-navy bg-white' : 'border-line bg-white')">
                    <span class="flex w-full items-start justify-between gap-2">
                        <span class="font-semibold">{{ $category->name }}</span>
                        <span x-show="count({{ $category->id }})" x-cloak class="pill bg-brand text-white" x-text="count({{ $category->id }}) + ' selected'"></span>
                    </span>
                    @if ($category->description)
                        <span class="mt-1 text-sm" :class="open === {{ $category->id }} ? 'text-navy-soft' : 'text-muted'">{{ $category->description }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        @foreach ($categories as $category)
            <div id="cat-{{ $category->id }}" x-show="open === {{ $category->id }}" x-cloak x-data="{ q: '' }" class="card mt-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-semibold">{{ $category->name }}</h3>
                    <div class="w-full sm:w-64">
                        <label for="search-{{ $category->id }}" class="sr-only">Search {{ $category->name }}</label>
                        <input id="search-{{ $category->id }}" type="search" x-model="q" placeholder="Search these services" class="field">
                    </div>
                </div>
                <ul class="divide-line mt-3 divide-y">
                    @foreach ($category->services as $service)
                        <li x-show="!q || @js(Str::lower($service->name)).includes(q.toLowerCase())">
                            <label class="flex min-h-12 cursor-pointer items-center gap-3 py-2">
                                <input type="checkbox" name="services[]" value="{{ $service->id }}" x-model="picked" class="accent-navy size-5 shrink-0">
                                <span class="flex-1">{{ $service->name }}</span>
                                <span class="text-muted text-right text-sm">{{ $service->priceLabel() }}</span>
                            </label>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
        <p class="text-muted mt-3 text-sm">Prices are a guide. The quote confirms the final amount.</p>
    </section>

    @foreach ($categories as $category)
        @if ($category->questions->isNotEmpty())
            <section x-show="count({{ $category->id }}) > 0" x-cloak class="card space-y-5">
                <div>
                    <p class="text-muted text-sm">A few quick questions{{ $admin ? ', fill in what you know' : '' }}</p>
                    <h2 class="h-section">{{ $category->name }}</h2>
                </div>
                @foreach ($category->questions as $question)
                    @php
                        $fieldId = 'answer-'.$question->id;
                        $mustAnswer = $question->is_required && ! $admin;
                    @endphp
                    <div>
                        <label for="{{ $fieldId }}" class="label">
                            {{ $question->label }}
                            @unless ($mustAnswer)
                                <span class="text-muted font-normal">(optional)</span>
                            @endunless
                        </label>
                        @if ($question->type === 'textarea')
                            <textarea id="{{ $fieldId }}" name="answers[{{ $question->id }}]" rows="3" maxlength="2000" class="field"
                                      @if ($mustAnswer) :required="count({{ $category->id }}) > 0" @endif>{{ old('answers.'.$question->id) }}</textarea>
                        @elseif (in_array($question->type, ['select', 'yesno']))
                            <select id="{{ $fieldId }}" name="answers[{{ $question->id }}]" class="field sm:max-w-sm"
                                    @if ($mustAnswer) :required="count({{ $category->id }}) > 0" @endif>
                                <option value="">Choose one</option>
                                @foreach ($question->type === 'yesno' ? ['Yes', 'No'] : ($question->options ?? []) as $option)
                                    <option value="{{ $option }}" @selected(old('answers.'.$question->id) === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="{{ $fieldId }}" name="answers[{{ $question->id }}]" value="{{ old('answers.'.$question->id) }}" maxlength="2000" class="field sm:max-w-lg"
                                   @if ($mustAnswer) :required="count({{ $category->id }}) > 0" @endif>
                        @endif
                    </div>
                @endforeach
            </section>
        @endif
    @endforeach

    <section class="card space-y-5">
        <h2 class="h-section">{{ $admin ? 'What the client asked for' : 'Anything else we should know?' }}</h2>
        <div>
            <label for="description" class="label">
                {{ $admin ? 'Notes from the call, email or message' : 'Tell us in your own words' }}
                <span class="text-muted font-normal">(optional if services are picked)</span>
            </label>
            <textarea id="description" name="description" rows="5" maxlength="5000" class="field" placeholder="For example: I need a website for my clothing business and help with social media.">{{ old('description') }}</textarea>
            @unless ($admin)
                <p class="text-muted mt-1.5 text-sm">Not sure which services you need? Just describe it here and we will work it out with you.</p>
            @endunless
        </div>
        <div>
            <label for="files" class="label">Add files <span class="text-muted font-normal">(optional)</span></label>
            <input id="files" name="files[]" type="file" multiple class="field file:bg-sand-dark file:mr-3 file:rounded-full file:px-4 file:py-1.5 file:font-medium"
                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip">
            <p class="text-muted mt-1.5 text-sm">Logos, photos or examples. Up to 8 files, 10 MB each.</p>
        </div>
    </section>

    <div class="h-16" aria-hidden="true"></div>
    <div class="border-line fixed inset-x-0 bottom-0 z-10 border-t bg-white px-5 py-3 lg:left-60">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
            <p class="text-sm">
                <span x-show="pkg" x-cloak><span class="font-semibold" x-text="pkgNames[pkg]"></span><span x-show="picked.length"> plus </span></span>
                <span x-show="picked.length" x-cloak class="font-semibold" x-text="picked.length + (picked.length === 1 ? ' service' : ' services')"></span>
                <span x-show="!pkg && !picked.length" class="text-muted">Nothing selected yet</span>
            </p>
            <button type="submit" class="btn btn-primary">{{ $admin ? 'Save request' : 'Send request' }}</button>
        </div>
    </div>
</div>
