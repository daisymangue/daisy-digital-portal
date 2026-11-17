{{--
    The service picker, questions, description and files. Used by the client's "Start a request"
    page and the admin's "New request for a client" page. $admin = true relaxes required questions.
--}}
@php
    $admin = $admin ?? false;
    $catMap = $categories->mapWithKeys(fn ($c) => [$c->id => $c->services->pluck('id')->map(fn ($id) => (string) $id)->all()]);
    $pkgNames = $packages->pluck('name', 'id');
    $allServices = $categories->flatMap(fn ($c) => $c->services->map(fn ($s) => [
        'id' => (string) $s->id, 'name' => $s->name, 'cat' => $c->name, 'price' => $s->priceLabel(),
    ]))->values();
    $popular = $popular ?? collect();
    $selectedPkgIndex = $packages->search(fn ($p) => (string) $p->id === (string) old('package_id'));
@endphp
<div class="flex flex-col gap-10 lg:grid lg:grid-cols-5 lg:items-start lg:gap-8"
     x-data="{
         open: null,
         picked: @js(array_map('strval', old('services', []))),
         pkg: @js((string) old('package_id', '')),
         cats: @js($catMap),
         pkgNames: @js($pkgNames),
         count(id) { return this.cats[id].filter(s => this.picked.includes(s)).length },
         special: @js((string) old('special_details', '')),
         all: @js($allServices),
         find: '',
         showAllPkgs: @js($selectedPkgIndex !== false && $selectedPkgIndex >= 3),
         get results() {
             const q = this.find.trim().toLowerCase();
             if (q.length < 2) return [];
             return this.all.filter(s => s.name.toLowerCase().includes(q) || s.cat.toLowerCase().includes(q)).slice(0, 25);
         },
         toggle(id) { this.picked = this.picked.includes(id) ? this.picked.filter(x => x !== id) : [...this.picked, id] },
         get hasSpecial() { return this.special.trim().length > 0 },
         callback: @js((bool) old('callback_phone')),
         focusMessage() { $nextTick(() => { document.getElementById('special').scrollIntoView({ behavior: 'smooth', block: 'start' }); document.getElementById('special_details').focus() }) },
     }">

    {{-- Left on large screens. On phones "contents" lets these sit around the request box, in order. --}}
    <div class="contents lg:col-span-3 lg:block lg:space-y-10">
    @if ($packages->isNotEmpty())
        <section aria-labelledby="pkg-heading" class="order-1">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="pkg-heading" class="h-section">Packages</h2>
                    <p class="text-muted mt-1">Our most requested services, bundled for less. Optional.</p>
                </div>
            </div>
            <div class="mt-4 grid gap-4 sm:grid-cols-3">
                @foreach ($packages as $package)
                    @php
                        $includes = $package->includes ?? [];
                    @endphp
                    <label @if ($loop->index >= 3) x-show="showAllPkgs" x-cloak @endif class="relative flex cursor-pointer flex-col rounded-2xl border-2 bg-white p-4 text-ink transition-colors"
                           :class="pkg === '{{ $package->id }}' ? 'border-brand ring-brand ring-2' : '{{ $package->is_featured ? 'border-brand' : 'border-line hover:border-line-strong' }}'">
                        <input type="radio" name="package_id" value="{{ $package->id }}" x-model="pkg" class="sr-only"
                               @click="if (pkg === '{{ $package->id }}') { $event.preventDefault(); $nextTick(() => pkg = '') }">
                        @if ($package->is_featured)
                            <span class="bg-brand absolute -top-3 left-5 rounded-full px-3 py-1 text-xs font-semibold text-white">Most popular</span>
                        @endif
                        <span class="flex items-start justify-between gap-3">
                            <span class="font-semibold">{{ $package->name }}</span>
                            <span x-show="pkg === '{{ $package->id }}'" x-cloak class="bg-brand rounded-full px-2.5 py-0.5 text-xs font-semibold text-white">Selected</span>
                        </span>
                        <span class="text-brand-dark mt-1 font-semibold">{{ $package->priceLabel() }}</span>
                        @if ($includes)
                            <ul class="text-muted mt-3 space-y-1 text-sm">
                                @foreach (array_slice($includes, 0, 3) as $line)
                                    <li class="flex gap-2"><svg class="text-brand mt-0.5 size-4 shrink-0" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $line }}</li>
                                @endforeach
                            </ul>
                            @if (count($includes) > 3)
                                <details class="mt-2 text-sm" @click.stop>
                                    <summary class="text-brand-dark min-h-8 cursor-pointer font-medium">and {{ count($includes) - 3 }} more</summary>
                                    <ul class="mt-1.5 space-y-1.5">
                                        @foreach (array_slice($includes, 3) as $line)
                                            <li class="flex gap-2"><svg class="text-brand mt-0.5 size-4 shrink-0" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        @endif
                        <span class="mt-auto pt-4">
                            <span class="btn w-full" :class="pkg === '{{ $package->id }}' ? 'btn-primary' : 'btn-light'" x-text="pkg === '{{ $package->id }}' ? 'Selected' : 'Select'">Select</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @if ($packages->count() > 3)
                <div class="mt-3">
                    <button type="button" class="link min-h-11 cursor-pointer" @click="showAllPkgs = !showAllPkgs"
                            x-text="showAllPkgs ? 'Show fewer packages' : 'See all {{ $packages->count() }} packages'">See all {{ $packages->count() }} packages</button>
                </div>
            @endif
        </section>
    @endif

    <section aria-labelledby="svc-heading" class="order-3">
        <h2 id="svc-heading" class="h-section">Individual services</h2>
        <p class="text-muted mt-1">Search, tap a popular service, or open a category. You can choose as many as you like.</p>

        <div class="mt-4">
            <label for="find-service" class="sr-only">Search all services</label>
            <input id="find-service" type="search" x-model="find" placeholder="Search all services: logo, email, website" class="field text-base" autocomplete="off">
        </div>

        <div x-show="find.trim().length >= 2" x-cloak class="card mt-3 p-0 sm:p-0">
            <ul class="divide-line divide-y" x-show="results.length">
                <template x-for="s in results" :key="s.id">
                    <li>
                        <label class="flex min-h-12 cursor-pointer items-center gap-3 px-4 py-2">
                            <input type="checkbox" :value="s.id" x-model="picked" class="accent-navy size-5 shrink-0">
                            <span class="flex-1"><span x-text="s.name"></span><span class="text-muted block text-sm" x-text="s.cat"></span></span>
                            <span class="text-muted text-right text-sm" x-text="s.price"></span>
                        </label>
                    </li>
                </template>
            </ul>
            <div x-show="!results.length" class="px-4 py-5">
                <p class="font-medium">No service matches "<span x-text="find.trim()"></span>".</p>
                <p class="text-muted mt-1">Not on our list does not mean we cannot do it. Tell us in your own words.</p>
                <button type="button" class="btn btn-primary mt-3" @click="special = special || find.trim() + ': '; find = ''; focusMessage()">Tell us what you need</button>
            </div>
        </div>

        @if ($popular->isNotEmpty())
            <div class="mt-5">
                <p class="text-sm font-medium">Popular services</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($popular as $service)
                        <button type="button" @click="toggle('{{ $service->id }}')" :aria-pressed="picked.includes('{{ $service->id }}').toString()"
                                class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-full border-2 px-4 text-sm font-medium"
                                :class="picked.includes('{{ $service->id }}') ? 'border-navy bg-navy text-white' : 'border-line bg-white hover:border-line-strong'">
                            <svg x-show="picked.includes('{{ $service->id }}')" x-cloak class="size-4" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ $service->name }}
                            <span class="font-normal" :class="picked.includes('{{ $service->id }}') ? 'text-navy-soft' : 'text-muted'">{{ $service->priceLabel() }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <p class="mt-6 text-sm font-medium">Browse by category</p>

        <div class="mt-2 grid grid-cols-2 gap-2 sm:gap-3 xl:grid-cols-3">
            @foreach ($categories as $category)
                <button type="button" @click="open = open === {{ $category->id }} ? null : {{ $category->id }}; if (open) $nextTick(() => document.getElementById('cat-{{ $category->id }}').scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                        :aria-expanded="(open === {{ $category->id }}).toString()" aria-controls="cat-{{ $category->id }}"
                        class="flex min-h-16 cursor-pointer flex-col rounded-2xl border-2 p-3 text-left text-sm sm:min-h-20 sm:p-4 sm:text-base"
                        :class="open === {{ $category->id }} ? 'border-navy bg-navy text-white' : (count({{ $category->id }}) ? 'border-navy bg-white' : 'border-line bg-white')">
                    <span class="flex w-full flex-wrap items-start justify-between gap-1 sm:flex-nowrap sm:gap-2">
                        <span class="font-semibold">{{ $category->name }}</span>
                        <span x-show="count({{ $category->id }})" x-cloak class="pill bg-brand text-white" x-text="count({{ $category->id }}) + ' selected'"></span>
                    </span>
                    @if ($category->description)
                        <span class="mt-1 hidden text-sm sm:block" :class="open === {{ $category->id }} ? 'text-navy-soft' : 'text-muted'">{{ $category->description }}</span>
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
            <section x-show="count({{ $category->id }}) > 0" x-cloak class="card order-4 space-y-5">
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

    </div>

    {{-- The request box: beside the menu on large screens and always in view, after packages on phones. --}}
    <aside class="order-2 lg:col-span-2 lg:self-stretch">
        <section id="special" class="bg-navy -mx-5 scroll-mt-6 px-5 py-7 text-white sm:mx-0 sm:rounded-3xl sm:px-6 lg:sticky lg:top-6 lg:max-h-[calc(100vh-3rem)] lg:overflow-y-auto">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="font-display text-2xl font-bold">{{ $admin ? 'In their own words' : 'Tell us what you need' }}</h2>
                @unless ($admin)
                    <span class="bg-brand rounded-full px-3 py-1 text-xs font-semibold tracking-wide">ANYTHING GOES</span>
                @endunless
            </div>
            <p class="text-navy-soft mt-2 text-sm">{{ $admin ? 'For anything not in the packages or service list, or extra detail.' : 'Documents, translations, tech help, design, or anything else. If you need it done, ask us.' }}</p>

            <label for="special_details" class="sr-only">Describe what you need</label>
            <textarea id="special_details" name="special_details" x-ref="special" x-model="special" rows="4" maxlength="5000"
                      class="text-ink mt-4 w-full rounded-2xl border-0 bg-white px-4 py-3 placeholder:text-gray-500"
                      placeholder="For example: Translate my 12-page booklet from English to isiZulu by Friday"></textarea>

            @unless ($admin)
                <div class="mt-3 flex flex-wrap gap-2" aria-label="Ideas">
                    @foreach (['Convert a PDF to Word', 'Translate a document', 'Type up my handwritten notes', 'Create an invoice for me', 'Fix my slow laptop', 'Recover deleted photos', 'Help with an online application'] as $example)
                        <button type="button" class="min-h-9 cursor-pointer rounded-full border border-white/25 px-3 text-xs text-white hover:bg-white/10"
                                @click="special = special ? special : @js($example.': '); $nextTick(() => $refs.special.focus())">{{ $example }}</button>
                    @endforeach
                    {{-- An open invitation for the odd jobs, so nobody feels their request is too strange to ask. --}}
                    <button type="button" class="border-brand bg-brand/20 hover:bg-brand/30 min-h-9 cursor-pointer rounded-full border px-3 text-xs font-semibold text-white"
                            @click="special = special ? special : 'This might be an unusual one, but '; $nextTick(() => $refs.special.focus())">✨ Something unusual? Try us</button>
                </div>
            @endunless

            <div class="mt-4 rounded-2xl bg-white p-3 text-ink">
                <x-file-drop id="files" :compact="true" label="Attach files (PDFs, photos, documents)" />
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div>
                    <label for="needed_by" class="text-navy-soft mb-1 block text-xs">When do you need it?</label>
                    <input id="needed_by" name="needed_by" type="date" min="{{ today()->toDateString() }}" value="{{ old('needed_by') }}" class="text-ink min-h-11 w-full rounded-xl border-0 bg-white px-3">
                </div>
                <div>
                    <label for="budget_range" class="text-navy-soft mb-1 block text-xs">Budget <span class="opacity-75">(optional)</span></label>
                    <select id="budget_range" name="budget_range" class="text-ink min-h-11 w-full rounded-xl border-0 bg-white px-3">
                        <option value="">Not sure</option>
                        @foreach (array_diff(\App\Models\ServiceRequest::BUDGETS, ['Not sure']) as $budget)
                            <option value="{{ $budget }}" @selected(old('budget_range') === $budget)>{{ $budget }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if ($admin)
                <div class="mt-4">
                    <label for="description" class="text-navy-soft mb-1 block text-xs">Notes from the call, email or message <span class="opacity-75">(optional)</span></label>
                    <textarea id="description" name="description" rows="3" maxlength="5000" class="text-ink w-full rounded-xl border-0 bg-white px-3 py-2">{{ old('description') }}</textarea>
                </div>
            @else
                <div class="mt-4">
                    <button type="button" class="text-navy-soft min-h-10 cursor-pointer text-sm underline underline-offset-2 hover:text-white" @click="callback = !callback" :aria-expanded="callback.toString()">Rather talk? Ask us to call you</button>
                    <div x-show="callback" x-cloak class="mt-2 grid grid-cols-2 gap-3">
                        <div>
                            <label for="callback_phone" class="text-navy-soft mb-1 block text-xs">Your number</label>
                            <input id="callback_phone" name="callback_phone" type="tel" maxlength="30" value="{{ old('callback_phone', auth()->user()->phone ?? auth()->user()->client?->phone) }}" :disabled="!callback" class="text-ink min-h-11 w-full rounded-xl border-0 bg-white px-3">
                        </div>
                        <div>
                            <label for="callback_time" class="text-navy-soft mb-1 block text-xs">Best time</label>
                            <select id="callback_time" name="callback_time" :disabled="!callback" class="text-ink min-h-11 w-full rounded-xl border-0 bg-white px-3">
                                @foreach (\App\Support\RequestBuilder::CALLBACK_TIMES as $t)
                                    <option @selected(old('callback_time') === $t)>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            @endif

            <div class="mt-5 rounded-2xl bg-white/10 p-4">
                <p class="text-navy-soft text-xs font-semibold tracking-widest">{{ $admin ? 'THIS REQUEST' : 'YOUR REQUEST' }}</p>
                <ul class="mt-2 space-y-1 text-sm">
                    <li x-show="hasSpecial" x-cloak>✓ {{ $admin ? 'Their words' : 'Your message' }}</li>
                    <li x-show="callback" x-cloak>✓ A call back</li>
                    <li x-show="pkg" x-cloak>✓ <span x-text="pkgNames[pkg]"></span></li>
                    <template x-for="id in picked" :key="id">
                        <li>✓ <span x-text="(all.find(s => s.id === id) || {}).name"></span> <span class="text-navy-soft" x-text="'· ' + ((all.find(s => s.id === id) || {}).price || '')"></span></li>
                    </template>
                    <li x-show="!pkg && !picked.length && !hasSpecial && !callback" class="text-navy-soft">Nothing yet. Write above, or tap a package or service.</li>
                </ul>
            </div>

            <button type="submit" class="btn btn-primary mt-5 w-full text-lg">{{ $admin ? 'Save request' : 'Send request' }}</button>
            @unless ($admin)
                <p class="text-navy-soft mt-3 text-center text-xs">We reply with a quote, usually within one working day. Prices are a guide.</p>
            @endunless
        </section>
    </aside>

    {{-- Phones: the send button stays in reach while scrolling the services. --}}
    <div class="h-14 lg:hidden" aria-hidden="true"></div>
    <div class="border-line fixed inset-x-0 bottom-0 z-10 flex items-center justify-between gap-3 border-t bg-white px-5 py-2.5 lg:hidden">
        <p class="min-w-0 truncate text-sm">
            <span x-show="pkg || picked.length || hasSpecial || callback" x-cloak class="font-semibold"
                  x-text="[hasSpecial ? 'Your message' : '', callback ? 'call back' : '', pkg ? '1 package' : '', picked.length ? picked.length + (picked.length === 1 ? ' service' : ' services') : ''].filter(Boolean).join(' + ')"></span>
            <span x-show="!pkg && !picked.length && !hasSpecial && !callback" class="text-muted">Nothing selected yet</span>
        </p>
        <button type="submit" class="btn btn-primary min-h-10 shrink-0 px-5">{{ $admin ? 'Save' : 'Send request' }}</button>
    </div>
</div>
