<x-layouts.app :title="'Request '.$serviceRequest->number">
    <a href="{{ route('requests.index') }}" class="link text-sm">Back to my requests</a>

    <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="h-page">Request {{ $serviceRequest->number }}</h1>
            <p class="text-muted mt-1">Sent {{ $serviceRequest->submitted_at->format('j F Y, H:i') }}</p>
        </div>
        <x-status :status="$serviceRequest->status" :label="$serviceRequest->clientStatusLabel()" />
    </div>

    @if ($serviceRequest->status === 'awaiting_client')
        <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900">
            We need a little more information before we can quote. Please check your email for our message.
        </div>
    @endif

    <div class="mt-8 grid gap-6 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @include('partials.request-details', ['serviceRequest' => $serviceRequest])
        </div>
        <aside class="min-w-0 space-y-6">
            <div class="card">
                <h2 class="h-section">What happens next</h2>
                <ol class="mt-3 list-decimal space-y-2 pl-5">
                    <li>Our team reviews your request.</li>
                    <li>We send you a quote, or ask a question if anything is unclear.</li>
                    <li>Once you accept, we get started.</li>
                </ol>
                <p class="text-muted mt-4 text-sm">Prices shown are a guide. Your quote confirms the final amount.</p>
            </div>
        </aside>
    </div>
</x-layouts.app>
