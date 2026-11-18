@php
    $plain = fn ($cents) => number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, '.', '');
@endphp
<x-layouts.app title="Billing · Plan menu">
    @include('admin.billing.header', ['tab' => 'menu'])

    <p class="text-muted mt-6 max-w-2xl">Your standard subscriptions. When you add one for a client, pick from this menu and adjust if needed. Changing a price here never changes what existing clients pay.</p>

    <div class="mt-6 grid items-start gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($templates as $t)
            <x-fold :title="$t->name" :summary="\App\Support\Money::format($t->amount_cents).' excl. VAT, '.strtolower($t->intervalLabel())" :open="$errors->any() && (int) old('template_id') === $t->id">
                <form method="POST" action="{{ route('admin.plan-templates.update', $t) }}" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="template_id" value="{{ $t->id }}">
                    <div>
                        <label for="name-{{ $t->id }}" class="label">Name</label>
                        <input id="name-{{ $t->id }}" name="name" required maxlength="160" value="{{ $t->name }}" class="field">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="amount-{{ $t->id }}" class="label">Price excl. VAT (R)</label>
                            <input id="amount-{{ $t->id }}" name="amount" inputmode="decimal" required value="{{ $plain($t->amount_cents) }}" class="field">
                        </div>
                        <div>
                            <label for="interval-{{ $t->id }}" class="label">Billed</label>
                            <select id="interval-{{ $t->id }}" name="interval" class="field">
                                @foreach (\App\Models\CarePlan::INTERVALS as $k => $l)
                                    <option value="{{ $k }}" @selected($t->interval === $k)>{{ $l }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="description-{{ $t->id }}" class="label">What is included <span class="text-muted font-normal">(one per line)</span></label>
                        <textarea id="description-{{ $t->id }}" name="description" rows="4" maxlength="1000" class="field">{{ $t->description }}</textarea>
                    </div>
                    <div>
                        <label for="sort-{{ $t->id }}" class="label">Order in the list</label>
                        <input id="sort-{{ $t->id }}" name="sort_order" type="number" min="0" max="999" value="{{ $t->sort_order }}" class="field w-24">
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.plan-templates.destroy', $t) }}" class="mt-3" onsubmit="return confirm({{ \Illuminate\Support\Js::from('Remove '.$t->name.' from the menu? Existing subscriptions stay as they are.') }})">
                    @csrf
                    @method('DELETE')
                    <button class="link min-h-11 cursor-pointer text-sm text-red-800">Remove from menu</button>
                </form>
            </x-fold>
        @endforeach

        <x-fold title="Add to the menu" summary="A new standard plan" :open="$templates->isEmpty() || ($errors->any() && ! old('template_id'))">
            <form method="POST" action="{{ route('admin.plan-templates.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="new-name" class="label">Name</label>
                    <input id="new-name" name="name" required maxlength="160" value="{{ old('template_id') ? '' : old('name') }}" class="field" placeholder="For example Email hosting">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="new-amount" class="label">Price excl. VAT (R)</label>
                        <input id="new-amount" name="amount" inputmode="decimal" required class="field" placeholder="150">
                        @error('amount')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="new-interval" class="label">Billed</label>
                        <select id="new-interval" name="interval" class="field">
                            @foreach (\App\Models\CarePlan::INTERVALS as $k => $l)
                                <option value="{{ $k }}">{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label for="new-description" class="label">What is included <span class="text-muted font-normal">(one per line)</span></label>
                    <textarea id="new-description" name="description" rows="4" maxlength="1000" class="field"></textarea>
                </div>
                <button type="submit" class="btn btn-primary">Add to menu</button>
            </form>
        </x-fold>
    </div>
</x-layouts.app>
