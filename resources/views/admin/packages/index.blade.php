<x-layouts.app title="Packages">
    <h1 class="h-page">Packages</h1>
    <p class="text-muted mt-1">Only packages switched to "Show to clients" appear on the request page. The first 3 are shown, with "Most popular" always first, and any others sit behind "See all packages". Put each included item on its own line.</p>

    <div class="mt-8 grid gap-4 lg:grid-cols-2">
        @foreach ($packages as $package)
            <form method="POST" action="{{ route('admin.packages.update', $package) }}" class="card space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="p-name-{{ $package->id }}" class="label">Package name</label>
                    <input id="p-name-{{ $package->id }}" name="name" value="{{ $package->name }}" required class="field">
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label for="p-price-{{ $package->id }}" class="label">Price (R)</label>
                        <input id="p-price-{{ $package->id }}" name="price" inputmode="decimal" value="{{ $package->price_cents !== null ? rtrim(rtrim(number_format($package->price_cents / 100, 2, '.', ''), '0'), '.') : '' }}" class="field">
                    </div>
                    <div>
                        <label for="p-order-{{ $package->id }}" class="label">Order</label>
                        <input id="p-order-{{ $package->id }}" name="sort_order" type="number" min="0" value="{{ $package->sort_order }}" class="field">
                    </div>
                    <div>
                        <label for="p-unit-{{ $package->id }}" class="label">Billing</label>
                        <select id="p-unit-{{ $package->id }}" name="unit" class="field">
                            <option value="">Once-off</option>
                            <option value="month" @selected($package->unit === 'month')>Per month</option>
                        </select>
                    </div>
                </div>
                <div class="grid gap-3 rounded-xl bg-amber-50 p-3 sm:grid-cols-2">
                    <div>
                        <label for="p-sale-{{ $package->id }}" class="label">Sale price (R) <span class="text-muted font-normal">(optional)</span></label>
                        <input id="p-sale-{{ $package->id }}" name="sale_price" inputmode="decimal" value="{{ $package->sale_price_cents !== null ? rtrim(rtrim(number_format($package->sale_price_cents / 100, 2, '.', ''), '0'), '.') : '' }}" class="field" placeholder="Leave empty for no sale">
                    </div>
                    <div>
                        <label for="p-sale-end-{{ $package->id }}" class="label">Sale ends <span class="text-muted font-normal">(optional)</span></label>
                        <input id="p-sale-end-{{ $package->id }}" name="sale_ends_on" type="date" value="{{ $package->sale_ends_on?->format('Y-m-d') }}" class="field">
                    </div>
                    <p class="text-sm sm:col-span-2 {{ $package->onSale() ? 'font-medium text-amber-900' : 'text-muted' }}">
                        @if ($package->onSale())
                            On sale now: clients see {{ $package->priceLabel() }} with {{ $package->regularPriceLabel() }} crossed out{{ $package->sale_ends_on ? ', until '.$package->sale_ends_on->format('j M Y') : '' }}.
                        @elseif ($package->sale_price_cents !== null)
                            The sale ended {{ $package->sale_ends_on?->format('j M Y') }}. Clients see the normal price. Clear the sale price or set a new end date.
                        @else
                            Clients see the normal price. A sale price shows with the normal price crossed out.
                        @endif
                    </p>
                </div>
                @error('sale_price')<p class="text-sm text-red-800">{{ $message }}</p>@enderror
                <div>
                    <label for="p-inc-{{ $package->id }}" class="label">What is included</label>
                    <textarea id="p-inc-{{ $package->id }}" name="includes" rows="6" class="field">{{ implode("\n", $package->includes ?? []) }}</textarea>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-x-5">
                        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="is_from" value="1" class="accent-navy size-5" @checked($package->is_from)> Show as "From"</label>
                        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="is_active" value="1" class="accent-navy size-5" @checked($package->is_active)> Show to clients</label>
                        <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="is_featured" value="1" class="accent-navy size-5" @checked($package->is_featured)> Most popular</label>
                        @if ($package->onSale())<span class="flex min-h-11 items-center"><span class="rounded-full bg-amber-400 px-3 py-0.5 text-xs font-bold">On sale</span></span>@endif
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
