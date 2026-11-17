<x-layouts.app title="Testimonials">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="h-page">Testimonials</h1>
            <p class="text-muted mt-1">Ratings from clients after handover. Star the best ones to use on the website.</p>
        </div>
        @if ($average)
            <p class="text-muted">Average <span class="text-ink text-xl font-semibold">{{ number_format($average, 1) }}</span> / 5 from {{ $received->count() }}</p>
        @endif
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-2">
        @forelse ($received as $t)
            <article class="card flex flex-col gap-3 {{ $t->status === 'featured' ? 'border-amber-300' : '' }}">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-xl text-amber-500" aria-label="{{ $t->rating }} out of 5 stars">{{ str_repeat('★', $t->rating) }}<span class="text-line-strong">{{ str_repeat('★', 5 - $t->rating) }}</span></p>
                    <span class="text-muted text-sm">{{ $t->received_at->format('j M Y') }}</span>
                </div>
                @if ($t->body)<blockquote class="flex-1 text-lg">"{{ $t->body }}"</blockquote>@endif
                <p class="text-muted text-sm">
                    {{ $t->display_name ?: $t->client->business_name }}
                    @if ($t->project) · <a href="{{ route('admin.projects.show', $t->project) }}" class="link">{{ $t->project->number }}</a> @endif
                </p>
                <div class="border-line flex flex-wrap items-center justify-between gap-3 border-t pt-3 text-sm">
                    @if ($t->can_publish)
                        <span class="font-medium text-green-800">OK to publish</span>
                        <form method="POST" action="{{ route('admin.testimonials.feature', $t) }}">@csrf<button class="link min-h-11 cursor-pointer">{{ $t->status === 'featured' ? '★ Favourite, remove' : '☆ Mark as favourite' }}</button></form>
                    @else
                        <span class="text-muted">Private, do not publish</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="card text-center md:col-span-2">
                <p class="font-medium">No testimonials yet.</p>
                <p class="text-muted mt-1">Clients are asked when they confirm a handover.</p>
            </div>
        @endforelse
    </div>

    @if ($waiting->isNotEmpty())
        <h2 class="h-section mt-10">Asked, no answer yet</h2>
        <ul class="card mt-4 divide-line divide-y py-0 sm:py-0">
            @foreach ($waiting as $t)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <span>{{ $t->client->business_name }} @if ($t->project)· <a href="{{ route('admin.projects.show', $t->project) }}" class="link">{{ $t->project->number }}</a>@endif</span>
                    <span class="text-muted text-sm">Asked {{ $t->requested_at?->format('j M Y') }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
