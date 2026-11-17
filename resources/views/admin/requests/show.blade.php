<x-layouts.app :title="'Request '.$serviceRequest->number">
    <a href="{{ route('admin.requests.index') }}" class="link text-sm">Back to requests</a>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="h-page">Request {{ $serviceRequest->number }}</h1>
            <p class="text-muted mt-1">
                <a href="{{ route('admin.clients.show', $serviceRequest->client) }}" class="link">{{ $serviceRequest->client->business_name }}</a>
                · {{ $serviceRequest->source === 'portal' ? 'Submitted' : 'Captured' }} {{ $serviceRequest->submitted_at->format('j F Y, H:i') }}
                · {{ $serviceRequest->sourceLabel() }}
            </p>
        </div>
        <x-status :status="$serviceRequest->status" :label="$serviceRequest->statusLabel()" />
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @include('partials.request-details', ['serviceRequest' => $serviceRequest, 'descriptionHeading' => "Client's own words"])

            @php
                $guide = $serviceRequest->services->whereNotNull('price_cents');
            @endphp
            @if ($guide->isNotEmpty())
                <section class="card">
                    <h2 class="h-section">Price guide</h2>
                    <p class="mt-2">
                        Listed prices add up to <span class="font-semibold">{{ \App\Support\Money::format($guide->sum('price_cents')) }}</span>
                        across {{ trans_choice(':count priced service|:count priced services', $guide->count()) }}.
                    </p>
                    <p class="text-muted mt-1 text-sm">A starting point only. It mixes fixed, "from", hourly and monthly prices, and leaves out anything marked as quote.</p>
                </section>
            @endif
        </div>

        <aside class="min-w-0 space-y-6">
            <div class="card space-y-3">
                <h2 class="h-section">Quote</h2>
                @forelse ($quotes as $q)
                    <a href="{{ route('admin.quotes.show', $q) }}" class="flex items-center justify-between gap-3">
                        <span class="link">{{ $q->reference() }}</span>
                        <x-status :status="$q->effectiveStatus()" :label="$q->statusLabel()" />
                    </a>
                @empty
                    <p class="text-muted text-sm">No quote yet. Creating one adds these services as line items for you to adjust.</p>
                @endforelse
                <form method="POST" action="{{ route('admin.requests.quotes.store', $serviceRequest) }}">
                    @csrf
                    <button type="submit" class="btn {{ $quotes->isEmpty() ? 'btn-primary' : 'btn-light' }} w-full">{{ $quotes->isEmpty() ? 'Create quote' : 'Create another quote' }}</button>
                </form>
            </div>

            <form method="POST" action="{{ route('admin.requests.status', $serviceRequest) }}" class="card space-y-2">
                @csrf
                @method('PATCH')
                <label for="status" class="h-section block">Status</label>
                <div class="flex gap-2">
                    <select id="status" name="status" class="field min-w-0 flex-1">
                        @foreach (\App\Models\ServiceRequest::STATUSES as $key => $label)
                            <option value="{{ $key }}" @selected($serviceRequest->status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-dark shrink-0">Update</button>
                </div>
                <p class="text-muted text-sm">The client is emailed when it changes.</p>
            </form>

            @php
                        $byClient = $serviceRequest->source === 'portal';
                        $client = $serviceRequest->client;
                        $cName = $byClient ? $serviceRequest->user->name : $client->contactName();
                        $cEmail = $byClient ? $serviceRequest->user->email : $client->contactEmail();
                        $cPhone = $byClient ? ($serviceRequest->user->phone ?: $client->phone) : $client->phone;
                    @endphp
            <x-fold title="Client" :summary="collect([$cName, $cPhone])->filter()->implode(' · ')">
                <dl class="space-y-2">
                    @if ($cName)
                        <div class="flex justify-between gap-3"><dt class="text-muted">Contact</dt><dd class="text-right">{{ $cName }}</dd></div>
                    @endif
                    @if ($cEmail)
                        <div class="flex justify-between gap-3"><dt class="text-muted">Email</dt><dd class="text-right break-all"><a href="mailto:{{ $cEmail }}" class="link">{{ $cEmail }}</a></dd></div>
                    @endif
                    @if ($cPhone)
                        <div class="flex justify-between gap-3"><dt class="text-muted">Phone</dt><dd class="text-right">{{ $cPhone }}</dd></div>
                    @endif
                    <div class="flex justify-between gap-3"><dt class="text-muted">Portal login</dt><dd>{{ $client->users->isNotEmpty() ? 'Yes' : 'No' }}</dd></div>
                    @unless ($byClient)
                        <div class="flex justify-between gap-3"><dt class="text-muted">Captured by</dt><dd class="text-right">{{ $serviceRequest->user->name }}</dd></div>
                    @endunless
                    <div class="flex justify-between gap-3"><dt class="text-muted">Earlier requests</dt><dd>{{ $pastCount }}</dd></div>
                </dl>
                <a href="{{ route('admin.clients.show', $client) }}" class="link inline-block text-sm">Open client page</a>
            </x-fold>

            <x-fold title="Internal notes" :summary="$serviceRequest->notes->isEmpty() ? 'Only your team sees these' : trans_choice(':count note|:count notes', $serviceRequest->notes->count()).', latest '.$serviceRequest->notes->first()->created_at->format('j M')" :open="$errors->has('body')">
                <form method="POST" action="{{ route('admin.requests.notes', $serviceRequest) }}" class="space-y-3">
                    @csrf
                    <label for="body" class="sr-only">New note</label>
                    <textarea id="body" name="body" rows="3" required maxlength="5000" class="field" placeholder="Add a note for the team. Clients never see these.">{{ old('body') }}</textarea>
                    <button type="submit" class="btn btn-dark">Save note</button>
                </form>
                <ul class="space-y-4">
                    @foreach ($serviceRequest->notes as $note)
                        <li class="border-line border-t pt-3">
                            <p class="whitespace-pre-line">{{ $note->body }}</p>
                            <p class="text-muted mt-1 text-sm">{{ $note->user->name }} · {{ $note->created_at->format('j M Y, H:i') }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-fold>
        </aside>
    </div>
</x-layouts.app>
