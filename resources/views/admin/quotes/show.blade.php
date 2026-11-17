@php
    $state = $quote->effectiveStatus();
@endphp
<x-layouts.app :title="'Quote '.$quote->reference()">
    <a href="{{ route('admin.quotes.index') }}" class="link text-sm">Back to quotes</a>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="h-page">{{ $quote->title }}</h1>
            <p class="text-muted mt-1">
                Quote {{ $quote->reference() }} · <a href="{{ route('admin.clients.show', $quote->client) }}" class="link">{{ $quote->client->business_name }}</a>
                @if ($quote->serviceRequest) · From <a href="{{ route('admin.requests.show', $quote->serviceRequest) }}" class="link">{{ $quote->serviceRequest->number }}</a>@endif
                @if ($quote->valid_until) · Valid until {{ $quote->valid_until->format('j F Y') }}@endif
            </p>
        </div>
        <x-status :status="$state" :label="$quote->statusLabel()" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @include('partials.quote-document', ['quote' => $quote])
        </div>

        <aside class="min-w-0 space-y-6">
            <div class="card space-y-3">
                <h2 class="h-section">Actions</h2>
                @if ($quote->isDraft())
                    <a href="{{ route('admin.quotes.edit', $quote) }}" class="btn btn-primary w-full">Edit draft</a>
                @endif
                @if ($quote->canBeRevised())
                    <form method="POST" action="{{ route('admin.quotes.revise', $quote) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary w-full">Revise (new version)</button>
                    </form>
                    <p class="text-muted text-sm">This version stays as the record. The client sees the new one once you send it.</p>
                @endif
                <a href="{{ route('admin.quotes.pdf', $quote) }}" class="btn btn-light w-full">Download PDF</a>
            </div>

            @if ($quote->canBeAnswered())
                <div class="card space-y-4" x-data="{ answer: '{{ old('answer', 'accepted') }}' }">
                    <div>
                        <h2 class="h-section">Record the client's answer</h2>
                        <p class="text-muted mt-1 text-sm">If they replied by email, WhatsApp, phone or a signed copy instead of in the portal.</p>
                    </div>
                    <form method="POST" action="{{ route('admin.quotes.answer', $quote) }}" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-2">
                            <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-full border text-sm font-medium" :class="answer === 'accepted' ? 'border-navy bg-navy text-white' : 'border-line-strong'">
                                <input type="radio" name="answer" value="accepted" x-model="answer" class="sr-only"> Accepted
                            </label>
                            <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-full border text-sm font-medium" :class="answer === 'declined' ? 'border-navy bg-navy text-white' : 'border-line-strong'">
                                <input type="radio" name="answer" value="declined" x-model="answer" class="sr-only"> Declined
                            </label>
                        </div>
                        <div x-show="answer === 'accepted'">
                            <label for="accepted_name" class="label">Name of the person who accepted</label>
                            <input id="accepted_name" name="accepted_name" value="{{ old('accepted_name', $quote->client->contactName()) }}" maxlength="120" class="field" :required="answer === 'accepted'">
                        </div>
                        <div>
                            <label for="method" class="label">How did they tell you?</label>
                            <select id="method" name="method" class="field">
                                @foreach (\App\Models\QuoteAcceptance::METHODS as $key => $label)
                                    <option value="{{ $key }}" @selected(old('method') === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="note" class="label">Note <span class="text-muted font-normal">(optional)</span></label>
                            <input id="note" name="note" value="{{ old('note') }}" maxlength="500" class="field" placeholder="For example: email from Lerato, 16 Nov">
                        </div>
                        <label x-show="answer === 'accepted'" class="flex items-start gap-3 text-sm">
                            <input type="checkbox" name="confirm" value="1" class="accent-navy mt-0.5 size-5 shrink-0" :required="answer === 'accepted'">
                            <span>I have the client's acceptance and will keep a copy of it.</span>
                        </label>
                        <button type="submit" class="btn btn-dark w-full" x-text="answer === 'accepted' ? 'Record acceptance' : 'Record decline'">Record answer</button>
                    </form>
                </div>
            @endif

            @if ($quote->status === 'accepted')
                <div class="card space-y-3">
                    <h2 class="h-section">Invoices</h2>
                    @foreach ($invoices as $inv)
                        <a href="{{ route('admin.invoices.show', $inv) }}" class="flex items-center justify-between gap-3">
                            <span class="link">{{ $inv->reference() }} · {{ \App\Models\Invoice::TYPES[$inv->type] }}</span>
                            <x-status :status="$inv->effectiveStatus()" :label="$inv->statusLabel()" />
                        </a>
                    @endforeach
                    <p class="text-muted text-sm">Invoiced so far: {{ \App\Support\Money::format($invoiced) }} of {{ \App\Support\Money::format($quote->total_cents) }}.</p>
                    @if ($invoices->isEmpty())
                        @if ($quote->deposit_percent > 0)
                            <form method="POST" action="{{ route('admin.quotes.invoices.store', $quote) }}">
                                @csrf
                                <input type="hidden" name="type" value="deposit">
                                <button type="submit" class="btn btn-primary w-full">Create deposit invoice ({{ \App\Support\Money::format($quote->depositCents()) }})</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.quotes.invoices.store', $quote) }}">
                            @csrf
                            <input type="hidden" name="type" value="full">
                            <button type="submit" class="btn btn-light w-full">Invoice the full amount</button>
                        </form>
                    @elseif ($invoiced < $quote->total_cents)
                        <form method="POST" action="{{ route('admin.quotes.invoices.store', $quote) }}">
                            @csrf
                            <input type="hidden" name="type" value="balance">
                            <button type="submit" class="btn btn-primary w-full">Create balance invoice ({{ \App\Support\Money::format($quote->total_cents - $invoiced) }})</button>
                        </form>
                    @endif
                </div>
            @endif

            @if ($quote->status === 'accepted')
                <div class="card space-y-3">
                    <h2 class="h-section">Project</h2>
                    @if ($project)
                        <a href="{{ route('admin.projects.show', $project) }}" class="flex items-center justify-between gap-3">
                            <span class="link">{{ $project->number }} · {{ $project->title }}</span>
                            <x-status :status="$project->status" :label="$project->statusLabel()" />
                        </a>
                    @else
                        <p class="text-muted text-sm">The project opens by itself when the deposit or full invoice is paid. To start before then, give a reason.</p>
                        <form method="POST" action="{{ route('admin.projects.store') }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="quote_id" value="{{ $quote->id }}">
                            <label for="start_reason" class="label">Reason for starting now</label>
                            <input id="start_reason" name="start_reason" required maxlength="500" class="field" placeholder="For example deposit waived, trusted client">
                            <button type="submit" class="btn btn-light w-full">Start project now</button>
                        </form>
                    @endif
                </div>
            @endif

            @if ($quote->acceptance)
                <div class="card">
                    <h2 class="h-section">Acceptance record</h2>
                    <dl class="mt-3 space-y-2">
                        <div class="flex justify-between gap-3"><dt class="text-muted">Accepted by</dt><dd class="text-right">{{ $quote->acceptance->accepted_name }}</dd></div>
                        @if ($quote->acceptance->isRecordedByTeam())
                            <div class="flex justify-between gap-3"><dt class="text-muted">How</dt><dd class="text-right">{{ $quote->acceptance->methodLabel() }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-muted">Recorded by</dt><dd class="text-right">{{ $quote->acceptance->user->name }}</dd></div>
                            @if ($quote->acceptance->note)
                                <div class="flex justify-between gap-3"><dt class="text-muted">Note</dt><dd class="text-right">{{ $quote->acceptance->note }}</dd></div>
                            @endif
                        @else
                            <div class="flex justify-between gap-3"><dt class="text-muted">Portal account</dt><dd class="text-right break-all">{{ $quote->acceptance->user->email }}</dd></div>
                        @endif
                        <div class="flex justify-between gap-3"><dt class="text-muted">When</dt><dd class="text-right">{{ $quote->acceptance->accepted_at->format('j M Y, H:i') }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-muted">IP address</dt><dd class="text-right">{{ $quote->acceptance->ip }}</dd></div>
                    </dl>
                    <p class="text-muted mt-3 text-sm">A frozen copy of the accepted quote is stored with this record. The quote can no longer be edited or revised.</p>
                </div>
            @endif

            @if ($quote->client_message)
                <div class="card">
                    <h2 class="h-section">Client's message</h2>
                    <p class="mt-2 whitespace-pre-line">{{ $quote->client_message }}</p>
                    <p class="text-muted mt-2 text-sm">{{ $quote->responded_at?->format('j M Y, H:i') }}</p>
                </div>
            @endif

            @if ($versions->count() > 1)
                <div class="card">
                    <h2 class="h-section">Versions</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach ($versions as $v)
                            <li class="flex items-center justify-between gap-3">
                                <a href="{{ route('admin.quotes.show', $v->id) }}" class="{{ $v->id === $quote->id ? 'font-semibold' : 'link' }}">Version {{ $v->version }}</a>
                                <span class="text-muted text-sm">{{ \App\Models\Quote::STATUSES[$v->status] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
</x-layouts.app>
