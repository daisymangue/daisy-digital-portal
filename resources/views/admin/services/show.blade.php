@php
    $types = \App\Models\Service::PRICING_TYPES;
@endphp
<x-layouts.app :title="$category->name">
    <a href="{{ route('admin.services.index') }}" class="link text-sm">Back to services</a>
    <h1 class="h-page mt-3">{{ $category->name }}</h1>

    <form method="POST" action="{{ route('admin.categories.update', $category) }}" class="card mt-6 grid gap-4 sm:grid-cols-6">
        @csrf
        @method('PUT')
        <div class="sm:col-span-2">
            <label for="cat-name" class="label">Category name</label>
            <input id="cat-name" name="name" value="{{ old('name', $category->name) }}" required class="field">
        </div>
        <div class="sm:col-span-3">
            <label for="cat-desc" class="label">Card description</label>
            <input id="cat-desc" name="description" value="{{ old('description', $category->description) }}" maxlength="200" class="field">
        </div>
        <div>
            <label for="cat-order" class="label">Order</label>
            <input id="cat-order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $category->sort_order) }}" required class="field">
        </div>
        <label class="flex min-h-11 items-center gap-3 sm:col-span-4">
            <input type="checkbox" name="is_active" value="1" class="accent-navy size-5" @checked($category->is_active)> Show this category to clients
        </label>
        <div class="sm:col-span-2 sm:text-right"><button type="submit" class="btn btn-dark">Save category</button></div>
    </form>

    <section class="mt-10">
        <h2 class="h-section">Services <span class="text-muted font-normal">({{ $category->services->count() }})</span></h2>
        <p class="text-muted mt-1 text-sm">Leave the price empty for "quote on request". Untick "Shown" to hide a service without losing past requests.</p>

        <div class="mt-4 space-y-2">
            @foreach ($category->services as $service)
                <form method="POST" action="{{ route('admin.services.update', $service) }}" class="card grid items-end gap-3 p-4 sm:grid-cols-12 sm:p-4">
                    @csrf
                    @method('PUT')
                    <div class="sm:col-span-5">
                        <label for="s-name-{{ $service->id }}" class="label">Service</label>
                        <input id="s-name-{{ $service->id }}" name="name" value="{{ $service->name }}" required class="field">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="s-type-{{ $service->id }}" class="label">Pricing</label>
                        <select id="s-type-{{ $service->id }}" name="pricing_type" class="field">
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}" @selected($service->pricing_type === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="s-price-{{ $service->id }}" class="label">Price (R)</label>
                        <input id="s-price-{{ $service->id }}" name="price" inputmode="decimal" value="{{ $service->price_cents !== null ? rtrim(rtrim(number_format($service->price_cents / 100, 2, '.', ''), '0'), '.') : '' }}" class="field">
                    </div>
                    <input type="hidden" name="unit" value="{{ $service->unit }}">
                    @if ($service->is_from)<input type="hidden" name="is_from" value="1">@endif
                    <label class="flex min-h-11 items-center gap-2 sm:col-span-1">
                        <input type="checkbox" name="is_active" value="1" class="accent-navy size-5" @checked($service->is_active)> Shown
                    </label>
                    <div class="sm:col-span-2 sm:text-right"><button type="submit" class="btn btn-light w-full sm:w-auto">Save</button></div>
                </form>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.services.store', $category) }}" class="card mt-4 grid items-end gap-3 sm:grid-cols-12">
            @csrf
            <h3 class="font-semibold sm:col-span-12">Add a service</h3>
            <div class="sm:col-span-6">
                <label for="new-name" class="label">Service</label>
                <input id="new-name" name="name" required class="field">
            </div>
            <div class="sm:col-span-2">
                <label for="new-type" class="label">Pricing</label>
                <select id="new-type" name="pricing_type" class="field">
                    @foreach ($types as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="new-price" class="label">Price (R)</label>
                <input id="new-price" name="price" inputmode="decimal" class="field">
            </div>
            <div class="sm:col-span-2 sm:text-right"><button type="submit" class="btn btn-dark w-full sm:w-auto">Add</button></div>
        </form>
    </section>

    <section class="mt-10">
        <h2 class="h-section">Questions</h2>
        <p class="text-muted mt-1 text-sm">Asked when a client picks any service in this category. Keep it to three or four.</p>

        <div class="mt-4 space-y-2">
            @foreach ($category->questions as $question)
                <div class="card p-4 sm:p-4">
                    <form id="q-form-{{ $question->id }}" method="POST" action="{{ route('admin.questions.update', $question) }}" class="grid items-end gap-3 sm:grid-cols-12">
                        @csrf
                        @method('PUT')
                        <div class="sm:col-span-6">
                            <label for="q-label-{{ $question->id }}" class="label">Question</label>
                            <input id="q-label-{{ $question->id }}" name="label" value="{{ $question->label }}" required class="field">
                        </div>
                        <div class="sm:col-span-3">
                            <label for="q-type-{{ $question->id }}" class="label">Answer type</label>
                            <select id="q-type-{{ $question->id }}" name="type" class="field">
                                @foreach (\App\Models\CategoryQuestion::TYPES as $key => $label)
                                    <option value="{{ $key }}" @selected($question->type === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <label for="q-opts-{{ $question->id }}" class="label">Options <span class="text-muted font-normal">(comma separated)</span></label>
                            <input id="q-opts-{{ $question->id }}" name="options" value="{{ implode(', ', $question->options ?? []) }}" class="field">
                        </div>
                        <label class="flex min-h-11 items-center gap-2 sm:col-span-3">
                            <input type="checkbox" name="is_required" value="1" class="accent-navy size-5" @checked($question->is_required)> Required
                        </label>
                        <label class="flex min-h-11 items-center gap-2 sm:col-span-3">
                            <input type="checkbox" name="is_active" value="1" class="accent-navy size-5" @checked($question->is_active)> Shown
                        </label>
                    </form>
                    <div class="mt-3 flex flex-wrap justify-end gap-3">
                        <form method="POST" action="{{ route('admin.questions.destroy', $question) }}" onsubmit="return confirm('Remove this question?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn text-red-800 hover:bg-red-50">Remove</button>
                        </form>
                        <button type="submit" form="q-form-{{ $question->id }}" class="btn btn-light">Save</button>
                    </div>
                </div>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.questions.store', $category) }}" class="card mt-4 grid items-end gap-3 sm:grid-cols-12">
            @csrf
            <h3 class="font-semibold sm:col-span-12">Add a question</h3>
            <div class="sm:col-span-5">
                <label for="nq-label" class="label">Question</label>
                <input id="nq-label" name="label" required class="field">
            </div>
            <div class="sm:col-span-2">
                <label for="nq-type" class="label">Answer type</label>
                <select id="nq-type" name="type" class="field">
                    @foreach (\App\Models\CategoryQuestion::TYPES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-3">
                <label for="nq-opts" class="label">Options <span class="text-muted font-normal">(comma separated)</span></label>
                <input id="nq-opts" name="options" class="field">
            </div>
            <label class="flex min-h-11 items-center gap-2 sm:col-span-1">
                <input type="checkbox" name="is_required" value="1" class="accent-navy size-5"> Required
            </label>
            <div class="sm:col-span-1 sm:text-right"><button type="submit" class="btn btn-dark w-full sm:w-auto">Add</button></div>
        </form>
    </section>
</x-layouts.app>
