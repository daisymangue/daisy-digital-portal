@php
    $rand = fn ($cents) => rtrim(rtrim(number_format($cents / 100, 2, '.', ''), '0'), '.');
    $rows = old('items') !== null
        ? collect(old('items'))->map(fn ($i) => [
            'description' => $i['description'] ?? '', 'quantity' => $i['quantity'] ?? '1', 'unit' => $i['unit'] ?? '',
            'price' => $i['price'] ?? '', 'service_id' => $i['service_id'] ?? '',
        ])->values()
        : $quote->items->map(fn ($i) => [
            'description' => $i->description, 'quantity' => (string) (float) $i->quantity, 'unit' => $i->unit ?? '',
            'price' => $rand($i->unit_price_cents), 'service_id' => $i->service_id ?? '',
        ])->values();
    $catalogue = $categories->map(fn ($c) => [
        'name' => $c->name,
        'services' => $c->services->map(fn ($s) => [
            'id' => $s->id, 'name' => $s->name, 'price' => $s->price_cents !== null ? $rand($s->price_cents) : '',
            'unit' => $s->unit ?? '', 'label' => $s->priceLabel(),
        ])->values(),
    ])->values();
@endphp
<x-layouts.app :title="'Edit quote '.$quote->reference()">
    <a href="{{ route('admin.quotes.index') }}" class="link text-sm">Back to quotes</a>
    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="h-page">Quote {{ $quote->reference() }}</h1>
            <p class="text-muted mt-1">Draft for {{ $quote->client->business_name }}. The client cannot see it until you send it.</p>
        </div>
        <x-status status="draft" label="Draft" />
    </div>

    <form method="POST" action="{{ route('admin.quotes.update', $quote) }}" class="mt-8 space-y-6"
          x-data="{
              items: @js($rows),
              catalogue: @js($catalogue),
              pick: '',
              discount: @js((string) old('discount', $quote->discount_cents ? $rand($quote->discount_cents) : '')),
              vat: @js((string) old('vat_rate', $quote->vat_rate)),
              deposit: @js((string) old('deposit_percent', $quote->deposit_percent)),
              num(v) { const n = parseFloat(String(v).replace(/[^\d.]/g, '')); return isNaN(n) ? 0 : n },
              line(i) { return Math.round(this.num(i.quantity) * this.num(i.price) * 100) / 100 },
              get subtotal() { return this.items.reduce((t, i) => t + this.line(i), 0) },
              get discountAmount() { return Math.min(this.num(this.discount), this.subtotal) },
              get vatAmount() { return Math.round((this.subtotal - this.discountAmount) * this.num(this.vat)) / 100 },
              get total() { return this.subtotal - this.discountAmount + this.vatAmount },
              money(v) { const [a, b] = v.toFixed(2).split('.'); return 'R' + a.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + b },
              add() { this.items.push({ description: '', quantity: '1', unit: '', price: '', service_id: '' }) },
              addFromCatalogue() {
                  if (!this.pick) return;
                  for (const c of this.catalogue) { const s = c.services.find(x => String(x.id) === this.pick);
                      if (s) { this.items.push({ description: s.name, quantity: '1', unit: s.unit, price: s.price, service_id: s.id }); break } }
                  this.pick = '';
              },
          }">
        @csrf
        @method('PUT')

        <section class="card grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="title" class="label">Quote title</label>
                <input id="title" name="title" value="{{ old('title', $quote->title) }}" required maxlength="200" class="field">
            </div>
            <div class="sm:col-span-2">
                <label for="scope" class="label">Scope and deliverables <span class="text-muted font-normal">(optional)</span></label>
                <textarea id="scope" name="scope" rows="4" class="field" placeholder="What is included, and anything that is not.">{{ old('scope', $quote->scope) }}</textarea>
            </div>
            <div>
                <label for="timeline" class="label">Estimated timeline <span class="text-muted font-normal">(optional)</span></label>
                <input id="timeline" name="timeline" value="{{ old('timeline', $quote->timeline) }}" maxlength="200" class="field" placeholder="For example: 3 to 4 weeks from deposit">
            </div>
            <div>
                <label for="valid_until" class="label">Valid until</label>
                <input id="valid_until" name="valid_until" type="date" value="{{ old('valid_until', $quote->valid_until?->toDateString()) }}" required class="field">
            </div>
        </section>

        <section class="card">
            <h2 class="h-section">Line items</h2>

            <div class="mt-4 space-y-3">
                <template x-for="(item, index) in items" :key="index">
                    <div class="border-line grid items-end gap-3 rounded-xl border p-3 sm:grid-cols-12">
                        <input type="hidden" :name="`items[${index}][service_id]`" :value="item.service_id">
                        <div class="sm:col-span-5">
                            <label :for="`d-${index}`" class="label">Description</label>
                            <input :id="`d-${index}`" :name="`items[${index}][description]`" x-model="item.description" required maxlength="500" class="field">
                        </div>
                        <div class="sm:col-span-1">
                            <label :for="`q-${index}`" class="label">Qty</label>
                            <input :id="`q-${index}`" :name="`items[${index}][quantity]`" x-model="item.quantity" required inputmode="decimal" class="field px-2 text-center">
                        </div>
                        <div class="sm:col-span-2">
                            <label :for="`u-${index}`" class="label">Unit</label>
                            <input :id="`u-${index}`" :name="`items[${index}][unit]`" x-model="item.unit" maxlength="30" placeholder="hour, month" class="field">
                        </div>
                        <div class="sm:col-span-2">
                            <label :for="`p-${index}`" class="label">Price (R)</label>
                            <input :id="`p-${index}`" :name="`items[${index}][price]`" x-model="item.price" inputmode="decimal" class="field">
                        </div>
                        <div class="flex items-center justify-between gap-2 sm:col-span-2 sm:flex-col sm:items-end">
                            <span class="font-semibold whitespace-nowrap" x-text="money(line(item))"></span>
                            <button type="button" class="min-h-11 cursor-pointer text-sm text-red-800 underline-offset-2 hover:underline" @click="items.splice(index, 1)" :aria-label="`Remove line ${index + 1}`">Remove</button>
                        </div>
                    </div>
                </template>
                <p x-show="items.length === 0" class="text-muted">No line items yet.</p>
            </div>

            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div class="w-full sm:w-96">
                    <label for="pick" class="label">Add from your services</label>
                    <select id="pick" x-model="pick" class="field">
                        <option value="">Choose a service</option>
                        @foreach ($categories as $category)
                            <optgroup label="{{ $category->name }}">
                                @foreach ($category->services as $service)
                                    <option value="{{ $service->id }}">{{ $service->name }} ({{ $service->priceLabel() }})</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </div>
                <button type="button" class="btn btn-dark" @click="addFromCatalogue()">Add service</button>
                <button type="button" class="btn btn-light" @click="add()">Add blank line</button>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <div class="card grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="discount" class="label">Discount (R)</label>
                    <input id="discount" name="discount" x-model="discount" inputmode="decimal" class="field">
                </div>
                <div>
                    <label for="vat_rate" class="label">VAT %</label>
                    <input id="vat_rate" name="vat_rate" x-model="vat" required inputmode="decimal" class="field">
                </div>
                <div>
                    <label for="deposit_percent" class="label">Deposit %</label>
                    <input id="deposit_percent" name="deposit_percent" x-model="deposit" required inputmode="decimal" class="field">
                </div>
                <div class="sm:col-span-3">
                    <label for="terms" class="label">Terms</label>
                    <textarea id="terms" name="terms" rows="5" class="field">{{ old('terms', $quote->terms) }}</textarea>
                </div>
            </div>

            <div class="card self-start">
                <h2 class="h-section">Totals</h2>
                <dl class="mt-3 space-y-2">
                    <div class="flex justify-between gap-4"><dt class="text-muted">Subtotal</dt><dd x-text="money(subtotal)"></dd></div>
                    <div class="flex justify-between gap-4" x-show="discountAmount > 0"><dt class="text-muted">Discount</dt><dd x-text="'-' + money(discountAmount)"></dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-muted">VAT (<span x-text="num(vat)"></span>%)</dt><dd x-text="money(vatAmount)"></dd></div>
                    <div class="border-line flex justify-between gap-4 border-t pt-2 text-lg font-semibold"><dt>Total</dt><dd x-text="money(total)"></dd></div>
                    <div class="flex justify-between gap-4" x-show="num(deposit) > 0"><dt class="text-muted">Deposit to start</dt><dd x-text="money(Math.round(total * num(deposit)) / 100)"></dd></div>
                </dl>
                <p class="text-muted mt-3 text-sm">A preview. The saved quote recalculates every amount on the server.</p>
            </div>
        </section>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <button type="submit" name="action" value="save" class="btn btn-light">Save draft</button>
            <button type="submit" name="action" value="send" class="btn btn-primary" onclick="return confirm('Send this quote to the client? It will be locked once sent.')">Save and send to client</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.quotes.destroy', $quote) }}" class="mt-6 text-right" onsubmit="return confirm('Delete this draft?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="min-h-11 cursor-pointer text-sm text-red-800 underline-offset-2 hover:underline">Delete draft</button>
    </form>
</x-layouts.app>
