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
<div class="space-y-10"
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
     }">
    @unless ($admin)
        <a href="#special" class="border-brand/30 flex flex-wrap items-center justify-between gap-3 rounded-2xl border bg-pink-50 px-5 py-4 text-pink-950 hover:bg-pink-100">
            <span><span class="font-semibold">Not on our list? If it is IT or digital, we can most likely do it.</span> Tell us about it in a special request.</span>
            <span class="font-semibold underline underline-offset-2">Make a special request</span>
        </a>
    @endunless

    @if ($packages->isNotEmpty())
        <section aria-labelledby="pkg-heading" class="bg-navy -mx-5 px-5 py-8 text-white sm:mx-0 sm:rounded-3xl sm:px-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <p class="text-navy-soft text-sm font-medium tracking-wider">BEST VALUE</p>
                    <h2 id="pkg-heading" class="font-display mt-1 text-2xl font-bold">Packages</h2>
                    <p class="text-navy-soft mt-1">Our most requested services, bundled for less. Optional.</p>
                </div>
                <p class="text-navy-soft text-sm">Tap a selected package again to unselect it.</p>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($packages as $package)
                    @php
                        $includes = $package->includes ?? [];
                    @endphp
                    <label @if ($loop->index >= 3) x-show="showAllPkgs" x-cloak @endif class="relative flex cursor-pointer flex-col rounded-2xl border-2 bg-white p-5 text-ink transition-colors"
                           :class="pkg === '{{ $package->id }}' ? 'border-brand ring-brand ring-2' : '{{ $package->is_featured ? 'border-brand' : 'border-transparent' }}'">
                        <input type="radio" name="package_id" value="{{ $package->id }}" x-model="pkg" class="sr-only"
                               @click="if (pkg === '{{ $package->id }}') { $event.preventDefault(); $nextTick(() => pkg = '') }">
                        @if ($package->is_featured)
                            <span class="bg-brand absolute -top-3 left-5 rounded-full px-3 py-1 text-xs font-semibold text-white">Most popular</span>
                        @endif
                        <span class="flex items-start justify-between gap-3">
                            <span class="font-semibold">{{ $package->name }}</span>
                            <span x-show="pkg === '{{ $package->id }}'" x-cloak class="bg-brand rounded-full px-2.5 py-0.5 text-xs font-semibold text-white">Selected</span>
                        </span>
                        <span class="font-display mt-2 text-2xl font-bold">{{ $package->priceLabel() }}</span>
                        @if ($includes)
                            <ul class="mt-3 space-y-1.5 text-sm">
                                @foreach (array_slice($includes, 0, 4) as $line)
                                    <li class="flex gap-2"><svg class="text-brand mt-0.5 size-4 shrink-0" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $line }}</li>
                                @endforeach
                            </ul>
                            @if (count($includes) > 4)
                                <details class="mt-2 text-sm" @click.stop>
                                    <summary class="text-brand-dark min-h-8 cursor-pointer font-medium">and {{ count($includes) - 4 }} more</summary>
                                    <ul class="mt-1.5 space-y-1.5">
                                        @foreach (array_slice($includes, 4) as $line)
                                            <li class="flex gap-2"><svg class="text-brand mt-0.5 size-4 shrink-0" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3 3 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                </details>
                            @endif
                        @endif
                        <span class="mt-auto pt-4">
                            <span class="btn w-full" :class="pkg === '{{ $package->id }}' ? 'btn-primary' : 'btn-light'" x-text="pkg === '{{ $package->id }}' ? 'Selected' : 'Choose this package'">Choose this package</span>
                        </span>
                    </label>
                @endforeach
            </div>
            @if ($packages->count() > 3)
                <div class="mt-5 text-center">
                    <button type="button" class="btn min-h-11 border border-white/40 px-6 text-white hover:bg-white/10" @click="showAllPkgs = !showAllPkgs"
                            x-text="showAllPkgs ? 'Show fewer packages' : 'See all {{ $packages->count() }} packages'">See all {{ $packages->count() }} packages</button>
                </div>
            @endif
        </section>
    @endif

    <section aria-labelledby="svc-heading">
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
                <p class="text-muted mt-1">If it is IT related, we can most likely still help. Describe it as a special request.</p>
                <button type="button" class="btn btn-primary mt-3" @click="special = special || find.trim() + ': '; find = ''; $nextTick(() => { document.getElementById('special').scrollIntoView({ behavior: 'smooth' }); document.getElementById('special_details').focus() })">Make a special request</button>
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

    <section id="special" class="scroll-mt-6 rounded-2xl border-2 border-dashed border-pink-300 bg-white p-5 sm:p-6"
>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="h-section">Special request</h2>
                <p class="text-muted mt-1">{{ $admin ? 'For work that is not in the packages or service list.' : 'Our lists cover the most common jobs, not everything we do. If it is IT related, from websites and apps to computers, networks, security and automation, describe it here and we will work out the best way to do it.' }}</p>
            </div>
            <span class="pill bg-pink-100 text-pink-900">Custom work</span>
        </div>

        @unless ($admin)
            <div class="mt-4 flex flex-wrap gap-2" aria-label="Examples">
                @foreach (['A booking system for my salon', 'Move my old website to a new host', 'Automate my invoices and follow-ups', 'A custom app for my staff'] as $example)
                    <button type="button" class="border-line-strong min-h-9 cursor-pointer rounded-full border px-3 text-sm hover:bg-pink-50"
                            @click="special = special ? special : @js($example.'. '); $nextTick(() => $refs.special.focus())">{{ $example }}</button>
                @endforeach
            </div>
        @endunless

        <div class="mt-4">
            <label for="special_details" class="label">What do you need?</label>
            <textarea id="special_details" name="special_details" x-ref="special" x-model="special" rows="4" maxlength="5000" class="field"
                      placeholder="What it should do, who will use it, and anything you have seen elsewhere that you like."></textarea>
        </div>

        <div x-show="special.trim().length > 0" x-cloak class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="budget_range" class="label">Rough budget <span class="text-muted font-normal">(optional)</span></label>
                <select id="budget_range" name="budget_range" class="field">
                    @foreach (\App\Models\ServiceRequest::BUDGETS as $budget)
                        <option value="{{ $budget }}" @selected(old('budget_range') === $budget)>{{ $budget }}</option>
                    @endforeach
                </select>
                <p class="text-muted mt-1.5 text-sm">Helps us suggest an option that fits. It is not a commitment.</p>
            </div>
            <div>
                <label for="needed_by" class="label">Needed by <span class="text-muted font-normal">(optional)</span></label>
                <input id="needed_by" name="needed_by" type="date" min="{{ today()->toDateString() }}" value="{{ old('needed_by') }}" class="field">
            </div>
        </div>

        <div x-show="special.trim().length > 0" x-cloak class="mt-4">
            <x-file-drop id="special-files" label="Show us an example" hint="A screenshot, photo, sketch or document helps us understand. Up to 8 files in total, 10 MB each." />
        </div>
    </section>

    <section class="card space-y-5">
        <h2 class="h-section">{{ $admin ? 'What the client asked for' : 'Anything else we should know?' }}</h2>
        <div>
            <label for="description" class="label">
                {{ $admin ? 'Notes from the call, email or message' : 'Notes for our team' }}
                <span class="text-muted font-normal">(optional)</span>
            </label>
            <textarea id="description" name="description" rows="5" maxlength="5000" class="field" placeholder="For example: we already have a logo, and we would like to launch before December.">{{ old('description') }}</textarea>
            @unless ($admin)
                <p class="text-muted mt-1.5 text-sm">Anything that helps us with what you picked above.</p>
            @endunless
        </div>
        <x-file-drop id="files" label="Logos, photos or documents" />
    </section>

    <div class="h-16" aria-hidden="true"></div>
    <div class="border-line fixed inset-x-0 bottom-0 z-10 border-t bg-white px-5 py-3 lg:left-60">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
            <p class="text-sm">
                <span x-show="pkg" x-cloak><span class="font-semibold" x-text="pkgNames[pkg]"></span><span x-show="picked.length"> plus </span></span>
                <span x-show="picked.length" x-cloak class="font-semibold" x-text="picked.length + (picked.length === 1 ? ' service' : ' services')"></span>
                <span x-show="hasSpecial" x-cloak><span x-show="pkg || picked.length"> plus </span><span class="font-semibold">a special request</span></span>
                <span x-show="!pkg && !picked.length && !hasSpecial" class="text-muted">Nothing selected yet</span>
            </p>
            <button type="submit" class="btn btn-primary">{{ $admin ? 'Save request' : 'Send request' }}</button>
        </div>
    </div>
</div>
