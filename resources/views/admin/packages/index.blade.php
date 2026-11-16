<x-layouts.app title="Packages">
    <h1 class="h-page">Packages</h1>
    <p class="text-muted mt-1">Bundles shown at the top of the request form. Put each included item on its own line.</p>

    <div class="mt-8 grid gap-4 lg:grid-cols-2">
        @foreach ($packages as $package)
            <form method="POST" action="{{ route('admin.packages.update', $package) }}" class="card space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="p-name-{{ $package->id }}" class="label">Package name</label>
                    <input id="p-name-{{ $package->id }}" name="name" value="{{ $package->name }}" required class="field">
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="p-price-{{ $package->id }}" class="label">Price (R)</label>
                        <input id="p-price-{{ $package->id }}" name="price" inputmode="decimal" value="{{ $package->price_cents !== null ? rtrim(rtrim(number_format($package->price_cents / 100, 2, '.', ''), '0'), '.') : '' }}" class="field">
                    </div>
                    <div>
                        <label for="p-unit-{{ $package->id }}" class="label">Billing</label>
                        <select id="p-unit-{{ $package->id }}" name="unit" class="field">
                            <option value="">Once-off</option>
                            <option value="month" @selected($package->unit === 'month')>Per month</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label for="p-inc-{{ $package->id }}" class="label">What is included</label>
                    <textarea id="p-inc-{{ $package->id }}" name="includes" rows="6" class="field">{{ implode("\n", $package->includes ?? []) }}</textarea>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-x-5">
                        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="is_from" value="1" class="accent-navy size-5" @checked($package->is_from)> Show as "From"</label>
                        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="is_active" value="1" class="accent-navy size-5" @checked($package->is_active)> Shown</label>
                    </div>
                    <button type="submit" class="btn btn-light">Save</button>
                </div>
            </form>
        @endforeach

        <form method="POST" action="{{ route('admin.packages.store') }}" class="card space-y-4">
            @csrf
            <h2 class="h-section">Add a package</h2>
            <div>
                <label for="np-name" class="label">Package name</label>
                <input id="np-name" name="name" required class="field">
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="np-price" class="label">Price (R)</label>
                    <input id="np-price" name="price" inputmode="decimal" class="field">
                </div>
                <div>
                    <label for="np-unit" class="label">Billing</label>
                    <select id="np-unit" name="unit" class="field">
                        <option value="">Once-off</option>
                        <option value="month">Per month</option>
                    </select>
                </div>
            </div>
            <div>
                <label for="np-inc" class="label">What is included</label>
                <textarea id="np-inc" name="includes" rows="6" class="field"></textarea>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="is_from" value="1" class="accent-navy size-5" checked> Show as "From"</label>
                <button type="submit" class="btn btn-dark">Add package</button>
            </div>
        </form>
    </div>
</x-layouts.app>
