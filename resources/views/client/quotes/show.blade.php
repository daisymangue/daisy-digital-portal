@php
    $state = $quote->effectiveStatus();
@endphp
<x-layouts.app :title="'Quote '.$quote->reference()">
    <a href="{{ route('quotes.index') }}" class="link text-sm">Back to quotes</a>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="h-page">{{ $quote->title }}</h1>
            <p class="text-muted mt-1">Quote {{ $quote->reference() }} · Sent {{ $quote->sent_at?->format('j F Y') }} · Valid until {{ $quote->valid_until?->format('j F Y') }}</p>
        </div>
        <x-status :status="$state" :label="$quote->clientStatusLabel()" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @include('partials.quote-document', ['quote' => $quote])
        </div>

        <aside class="min-w-0 space-y-6" x-data="{ panel: '{{ $errors->has('message') ? 'changes' : '' }}' }">
            @if ($state === 'sent')
                <form method="POST" action="{{ route('quotes.accept', $quote->id) }}" class="card space-y-4">
                    @csrf
                    <h2 class="h-section">Accept this quote</h2>
                    <p>Total <span class="font-semibold">{{ \App\Support\Money::format($quote->total_cents) }}</span>.
                        @if ($quote->deposit_percent > 0)
                            A deposit of <span class="font-semibold">{{ \App\Support\Money::format($quote->depositCents()) }}</span> gets the work started.
                        @endif
                    </p>
                    <div>
                        <label for="accepted_name" class="label">Type your full name</label>
                        <input id="accepted_name" name="accepted_name" value="{{ old('accepted_name', auth()->user()->name) }}" required class="field" autocomplete="name">
                    </div>
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="confirm" value="1" required class="accent-navy mt-0.5 size-5 shrink-0">
                        <span>I accept this quote and its terms on behalf of {{ $quote->client->business_name }}.</span>
                    </label>
                    <button type="submit" class="btn btn-primary w-full">Accept quote</button>
                </form>

                <div class="card space-y-3">
                    <h2 class="h-section">Not quite right?</h2>
                    <button type="button" class="btn btn-light w-full" @click="panel = panel === 'changes' ? '' : 'changes'" :aria-expanded="(panel === 'changes').toString()">Ask for changes</button>
                    <form method="POST" action="{{ route('quotes.changes', $quote->id) }}" x-show="panel === 'changes'" x-cloak class="space-y-3">
                        @csrf
                        <label for="message" class="label">What would you like changed?</label>
                        <textarea id="message" name="message" rows="4" required maxlength="3000" class="field">{{ old('message') }}</textarea>
                        <button type="submit" class="btn btn-dark w-full">Send to the team</button>
                    </form>

                    <button type="button" class="btn w-full text-red-800 hover:bg-red-50" @click="panel = panel === 'decline' ? '' : 'decline'" :aria-expanded="(panel === 'decline').toString()">Decline quote</button>
                    <form method="POST" action="{{ route('quotes.decline', $quote->id) }}" x-show="panel === 'decline'" x-cloak class="space-y-3">
                        @csrf
                        <label for="decline-message" class="label">Reason <span class="text-muted font-normal">(optional)</span></label>
                        <textarea id="decline-message" name="message" rows="3" maxlength="3000" class="field"></textarea>
                        <button type="submit" class="btn btn-dark w-full">Confirm decline</button>
                    </form>
                </div>
            @elseif ($state === 'accepted')
                <div class="card">
                    <h2 class="h-section">Accepted</h2>
                    <p class="mt-2">Accepted by {{ $quote->acceptance?->accepted_name }} on {{ $quote->acceptance?->accepted_at->format('j F Y \a\t H:i') }}@if ($quote->acceptance?->isRecordedByTeam()), recorded by our team ({{ $quote->acceptance->methodLabel() }})@endif.</p>
                    <p class="text-muted mt-2 text-sm">These terms are now locked. Any change needs a new quote that you approve.</p>
                </div>
            @elseif ($state === 'changes_requested')
                <div class="card">
                    <h2 class="h-section">Changes requested</h2>
                    <p class="mt-2 whitespace-pre-line">{{ $quote->client_message }}</p>
                    <p class="text-muted mt-2 text-sm">We will send a revised quote.</p>
                </div>
            @elseif ($state === 'expired')
                <div class="card"><h2 class="h-section">Expired</h2><p class="mt-2">This quote has passed its valid-until date. Contact us and we will send an updated one.</p></div>
            @elseif ($state === 'superseded')
                <div class="card"><h2 class="h-section">Replaced</h2><p class="mt-2">A newer version of this quote has been sent. <a href="{{ route('quotes.index') }}" class="link">See your quotes</a>.</p></div>
            @elseif ($state === 'declined')
                <div class="card"><h2 class="h-section">Declined</h2><p class="mt-2">You declined this quote. If you change your mind, contact us.</p></div>
            @endif

            <a href="{{ route('quotes.pdf', $quote->id) }}" class="btn btn-light w-full">Download PDF</a>
        </aside>
    </div>
</x-layouts.app>
