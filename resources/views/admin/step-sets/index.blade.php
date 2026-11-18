<x-layouts.app title="Step sets">
    <a href="{{ route('admin.projects.index') }}" class="link inline-flex min-h-11 items-center text-sm">Back to projects</a>
    <h1 class="h-page">Step sets</h1>
    <p class="text-muted mt-1 max-w-2xl">The steps a project starts with. When a quote becomes a project, it gets the set linked to its package, or to the category of its services. You can still change the steps on any project.</p>

    <div class="mt-8 grid items-start gap-6 lg:grid-cols-5">
        <section class="space-y-3 lg:col-span-3">
            <h2 class="h-section">Your sets</h2>
            @foreach ($sets as $set)
                <x-fold :title="$set->name" :summary="$set->summary()" :open="$errors->any() && (int) old('set_id') === $set->id">
                    <form method="POST" action="{{ route('admin.step-sets.update', $set) }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="set_id" value="{{ $set->id }}">
                        <div>
                            <label for="name-{{ $set->id }}" class="label">Name</label>
                            <input id="name-{{ $set->id }}" name="name" required maxlength="120" value="{{ $set->name }}" class="field">
                        </div>
                        <div>
                            <label for="steps-{{ $set->id }}" class="label">Steps, one per line</label>
                            <textarea id="steps-{{ $set->id }}" name="steps" rows="6" required class="field font-mono text-sm">{{ $set->asText() }}</textarea>
                            <p class="text-muted mt-1 text-sm">End a step with <span class="text-ink font-semibold">*</span> when the client must approve it, for example <span class="text-ink">First design *</span></p>
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                    <form method="POST" action="{{ route('admin.step-sets.destroy', $set) }}" class="mt-2" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Remove '.$set->name.'? Projects keep their steps.') }})">
                        @csrf
                        @method('DELETE')
                        <button class="min-h-11 cursor-pointer text-sm text-red-800 underline-offset-2 hover:underline">Remove set</button>
                    </form>
                </x-fold>
            @endforeach

            <x-fold title="Add a step set" summary="For a new kind of work" :open="$sets->isEmpty() || ($errors->any() && ! old('set_id'))">
                <form method="POST" action="{{ route('admin.step-sets.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label for="new-name" class="label">Name</label>
                        <input id="new-name" name="name" required maxlength="120" value="{{ old('set_id') ? '' : old('name') }}" class="field" placeholder="For example Translation">
                    </div>
                    <div>
                        <label for="new-steps" class="label">Steps, one per line</label>
                        <textarea id="new-steps" name="steps" rows="5" required class="field font-mono text-sm" placeholder="Got the document&#10;Translating&#10;Ready for you *"></textarea>
                        @error('steps')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Add set</button>
                </form>
            </x-fold>
        </section>

        <form method="POST" action="{{ route('admin.step-sets.links') }}" class="card space-y-6 lg:col-span-2">
            @csrf
            @method('PUT')
            <div>
                <h2 class="h-section">Which set to use</h2>
                <p class="text-muted mt-1 text-sm">A package's set wins. Without a package, the biggest set among the chosen services is used.</p>
            </div>
            @foreach (['packages' => [$packages, 'Packages'], 'categories' => [$categories, 'Service categories']] as $field => [$rows, $heading])
                <fieldset>
                    <legend class="text-sm font-semibold">{{ $heading }}</legend>
                    <div class="divide-line mt-2 divide-y">
                        @foreach ($rows as $row)
                            <div class="flex items-center justify-between gap-3 py-2">
                                <label for="{{ $field }}-{{ $row->id }}" class="min-w-0 text-sm">{{ $row->name }}</label>
                                <select id="{{ $field }}-{{ $row->id }}" name="{{ $field }}[{{ $row->id }}]" class="field w-44 shrink-0 py-2 text-sm">
                                    <option value="">No steps</option>
                                    @foreach ($sets as $set)
                                        <option value="{{ $set->id }}" @selected($row->step_set_id === $set->id)>{{ $set->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            <button type="submit" class="btn btn-dark w-full">Save links</button>
        </form>
    </div>
</x-layouts.app>
