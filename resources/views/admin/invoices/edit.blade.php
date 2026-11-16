@php
    $rand = fn ($cents) => rtrim(rtrim(number_format($cents / 100, 2, '.', ''), '0'), '.');
    $rows = old('items') !== null
        ? collect(old('items'))->map(fn ($i) => ['description' => $i['description'] ?? '', 'quantity' => $i['quantity'] ?? '1', 'unit' => $i['unit'] ?? '', 'price' => $i['price'] ?? ''])->values()
        : $invoice->items->map(fn ($i) => ['description' => $i->description, 'quantity' => (string) (float) $i->quantity, 'unit' => $i->unit ?? '', 'price' => $rand($i->unit_price_cents)])->values();
    $locked = $invoice->isFromQuote();
@endphp
<x-layouts.app title="Edit draft invoice">
    <a href="{{ route('admin.invoices.show', $invoice) }}" class="link text-sm">Back to invoice</a>
    <h1 class="h-page mt-3">Draft invoice</h1>
    <p class="text-muted mt-1">For {{ $invoice->client->business_name }}. The client cannot see it until you issue it.</p>

    <form method="POST" action="{{ route('admin.invoices.update', $invoice) }}" class="mt-8 space-y-6"
          x-data="{
              items: @js($rows),
              discount: @js((string) old('discount', $invoice->discount_cents ? $rand($invoice->discount_cents) : '')),
              vat: @js((string) old('vat_rate', $invoice->vat_rate)),
              num(v) { const n = parseFloat(String(v).replace(/[^\d.]/g, '')); return isNaN(n) ? 0 : n },
              line(i) { return Math.round(this.num(i.quantity) * this.num(i.price) * 100) / 100 },
              get subtotal() { return this.items.reduce((t, i) => t + this.line(i), 0) },
              get discountAmount() { return Math.min(this.num(this.discount), this.subtotal) },
              get vatAmount() { return Math.round((this.subtotal - this.discountAmount) * this.num(this.vat)) / 100 },
              get total() { return this.subtotal - this.discountAmount + this.vatAmount },
              money(v) { const [a, b] = v.toFixed(2).split('.'); return 'R' + a.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + '.' + b },
          }">
        @csrf
        @method('PUT')

        <section class="card grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="title" class="label">Invoice title</label>
                <input id="title" name="title" value="{{ old('title', $invoice->title) }}" required maxlength="200" class="field">
            </div>
            <div>
                <label for="due_date" class="label">Due date</label>
                <input id="due_date" name="due_date" type="date" value="{{ old('due_date', $invoice->due_date?->toDateString()) }}" required class="field">
            </div>
            <div class="sm:col-span-3">
                <label for="notes" class="label">Note to the client <span class="text-muted font-normal">(optional)</span></label>
                <textarea id="notes" name="notes" rows="3" class="field">{{ old('notes', $invoice->notes) }}</textarea>
            </div>
        </section>

        @if ($locked)
            <div class="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-blue-950">
                The amounts on this invoice come from the accepted quote and cannot be changed here. Total: <span class="font-semibold">{{ \App\Support\Money::format($invoice->total_cents) }}</span>.
            </div>
        @else
            <section class="card">
                <h2 class="h-section">Line items</h2>
                <div class="mt-4 space-y-3">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="border-line grid items-end gap-3 rounded-xl border p-3 sm:grid-cols-12">
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
                <button type="button" class="btn btn-light mt-4" @click="items.push({ description: '', quantity: '1', unit: '', price: '' })">Add line</button>
            </section>

            <section class="grid gap-6 lg:grid-cols-2">
                <div class="card grid gap-4 self-start sm:grid-cols-2">
                    <div>
                        <label for="discount" class="label">Discount (R)</label>
                        <input id="discount" name="discount" x-model="discount" inputmode="decimal" class="field">
                    </div>
                    <div>
                        <label for="vat_rate" class="label">VAT %</label>
                        <input id="vat_rate" name="vat_rate" x-model="vat" required inputmode="decimal" class="field">
                    </div>
                </div>
                <div class="card">
                    <h2 class="h-section">Totals</h2>
                    <dl class="mt-3 space-y-2">
                        <div class="flex justify-between gap-4"><dt class="text-muted">Subtotal</dt><dd x-text="money(subtotal)"></dd></div>
                        <div class="flex justify-between gap-4" x-show="discountAmount > 0"><dt class="text-muted">Discount</dt><dd x-text="'-' + money(discountAmount)"></dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-muted">VAT (<span x-text="num(vat)"></span>%)</dt><dd x-text="money(vatAmount)"></dd></div>
                        <div class="border-line flex justify-between gap-4 border-t pt-2 text-lg font-semibold"><dt>Total</dt><dd x-text="money(total)"></dd></div>
                    </dl>
                </div>
            </section>
        @endif

        <div class="flex flex-wrap items-center justify-end gap-3">
            <button type="submit" name="action" value="save" class="btn btn-light">Save draft</button>
            <button type="submit" name="action" value="issue" class="btn btn-primary" onclick="return confirm('Issue this invoice to the client? It can no longer be edited.')">Save and issue</button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.invoices.destroy', $invoice) }}" class="mt-6 text-right" onsubmit="return confirm('Delete this draft?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="min-h-11 cursor-pointer text-sm text-red-800 underline-offset-2 hover:underline">Delete draft</button>
    </form>
</x-layouts.app>
