<div class="grid gap-4 {{ ($narrow ?? false) ? '' : 'sm:grid-cols-2' }}">
    <div class="sm:col-span-2">
        <label for="business_name" class="label">Business name</label>
        <input id="business_name" name="business_name" value="{{ old('business_name', $client?->business_name) }}" required maxlength="160" class="field">
        <p class="text-muted mt-1.5 text-sm">For an individual, use their name.</p>
    </div>
    <div>
        <label for="contact_name" class="label">Contact person</label>
        <input id="contact_name" name="contact_name" value="{{ old('contact_name', $client?->contact_name) }}" maxlength="120" class="field">
    </div>
    <div>
        <label for="phone" class="label">Phone or WhatsApp</label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone', $client?->phone) }}" maxlength="30" class="field">
    </div>
    <div class="sm:col-span-2">
        <label for="email" class="label">Email <span class="text-muted font-normal">(optional)</span></label>
        <input id="email" name="email" type="email" value="{{ old('email', $client?->email) }}" maxlength="190" class="field">
    </div>
    <div class="sm:col-span-2">
        <label for="address" class="label">Address <span class="text-muted font-normal">(optional, printed on quotes and invoices)</span></label>
        <textarea id="address" name="address" rows="2" maxlength="500" class="field">{{ old('address', $client?->address) }}</textarea>
    </div>
    <div class="sm:col-span-2">
        <label for="notes" class="label">Internal notes <span class="text-muted font-normal">(only your team sees these)</span></label>
        <textarea id="notes" name="notes" rows="3" maxlength="3000" class="field">{{ old('notes', $client?->notes) }}</textarea>
    </div>
</div>
