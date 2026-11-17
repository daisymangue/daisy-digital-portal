<x-layouts.app title="Settings">
    <h1 class="h-page">Settings</h1>
    <p class="text-muted mt-1">These details appear on quotes and invoices.</p>

    <form method="POST" action="{{ route('admin.settings') }}" class="mt-8 grid gap-6 lg:grid-cols-2">
        @csrf
        @method('PUT')

        <section class="card space-y-4">
            <h2 class="h-section">Business details</h2>
            <div>
                <label for="business_name" class="label">Business name</label>
                <input id="business_name" name="business_name" value="{{ old('business_name', $settings['business_name']) }}" required class="field">
            </div>
            <div>
                <label for="business_address" class="label">Address</label>
                <textarea id="business_address" name="business_address" rows="3" class="field">{{ old('business_address', $settings['business_address']) }}</textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="business_email" class="label">Email</label>
                    <input id="business_email" name="business_email" type="email" value="{{ old('business_email', $settings['business_email']) }}" class="field">
                </div>
                <div>
                    <label for="business_phone" class="label">Phone</label>
                    <input id="business_phone" name="business_phone" value="{{ old('business_phone', $settings['business_phone']) }}" class="field">
                </div>
                <div>
                    <label for="business_website" class="label">Website</label>
                    <input id="business_website" name="business_website" value="{{ old('business_website', $settings['business_website']) }}" class="field">
                </div>
                <div>
                    <label for="registration_number" class="label">Company registration number</label>
                    <input id="registration_number" name="registration_number" value="{{ old('registration_number', $settings['registration_number']) }}" class="field">
                </div>
            </div>
        </section>

        <section class="card space-y-4">
            <h2 class="h-section">VAT and quote defaults</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="vat_number" class="label">VAT number</label>
                    <input id="vat_number" name="vat_number" value="{{ old('vat_number', $settings['vat_number']) }}" class="field">
                </div>
                <div>
                    <label for="vat_rate" class="label">VAT rate %</label>
                    <input id="vat_rate" name="vat_rate" inputmode="decimal" value="{{ old('vat_rate', $settings['vat_rate']) }}" required class="field">
                </div>
            </div>
            <p class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Only charge VAT if the business is registered for VAT with SARS, and add the VAT number above so it prints on documents. If you are not registered, set the rate to 0.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="quote_valid_days" class="label">Quotes valid for (days)</label>
                    <input id="quote_valid_days" name="quote_valid_days" inputmode="numeric" value="{{ old('quote_valid_days', $settings['quote_valid_days']) }}" required class="field">
                </div>
                <div>
                    <label for="deposit_percent" class="label">Default deposit %</label>
                    <input id="deposit_percent" name="deposit_percent" inputmode="decimal" value="{{ old('deposit_percent', $settings['deposit_percent']) }}" required class="field">
                </div>
            </div>
            <div class="sm:max-w-xs">
                <label for="invoice_due_days" class="label">Invoices due after (days)</label>
                <input id="invoice_due_days" name="invoice_due_days" inputmode="numeric" value="{{ old('invoice_due_days', $settings['invoice_due_days']) }}" required class="field">
            </div>
            <div>
                <label for="quote_terms" class="label">Default quote terms</label>
                <textarea id="quote_terms" name="quote_terms" rows="5" class="field">{{ old('quote_terms', $settings['quote_terms']) }}</textarea>
            </div>
            <div>
                <label for="bank_details" class="label">Bank details for EFT <span class="text-muted font-normal">(shown on invoices)</span></label>
                <textarea id="bank_details" name="bank_details" rows="4" class="field" placeholder="Bank, account name, account number, branch code">{{ old('bank_details', $settings['bank_details']) }}</textarea>
            </div>
        </section>

        <section class="card space-y-4 lg:col-span-2">
            <h2 class="h-section">After the work</h2>
            <label class="flex items-start gap-3">
                <input type="hidden" name="overdue_reminders" value="0">
                <input type="checkbox" name="overdue_reminders" value="1" @checked(old('overdue_reminders', $settings['overdue_reminders']) === '1') class="accent-navy mt-0.5 size-5 shrink-0">
                <span><span class="font-medium">Email overdue reminders</span><span class="text-muted block text-sm">Polite reminders 1, 7 and 14 days after the due date, then they stop. You can always send one by hand from the invoice.</span></span>
            </label>
            <label class="flex items-start gap-3">
                <input type="hidden" name="ask_for_testimonial" value="0">
                <input type="checkbox" name="ask_for_testimonial" value="1" @checked(old('ask_for_testimonial', $settings['ask_for_testimonial']) === '1') class="accent-navy mt-0.5 size-5 shrink-0">
                <span><span class="font-medium">Ask for a testimonial after handover</span><span class="text-muted block text-sm">When a client confirms they received the handover, they are asked to rate the work.</span></span>
            </label>
            <div class="sm:max-w-lg">
                <label for="google_review_url" class="label">Google review link <span class="text-muted font-normal">(optional)</span></label>
                <input id="google_review_url" name="google_review_url" type="url" value="{{ old('google_review_url', $settings['google_review_url']) }}" class="field" placeholder="https://g.page/r/...">
                <p class="text-muted mt-1 text-sm">Happy clients (4 or 5 stars) are offered this link after leaving a testimonial.</p>
            </div>
        </section>

        <div class="lg:col-span-2"><button type="submit" class="btn btn-primary">Save settings</button></div>
    </form>
</x-layouts.app>
