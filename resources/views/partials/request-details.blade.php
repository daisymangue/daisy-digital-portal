{{-- Shared by the client and admin request pages. Contains nothing internal. --}}
@if ($serviceRequest->isSpecial())
    <section class="rounded-2xl border-2 border-dashed border-pink-300 bg-white p-5 sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="h-section">Special request</h2>
            <span class="pill bg-pink-100 text-pink-900">Custom work</span>
        </div>
        <p class="mt-3 whitespace-pre-line">{{ $serviceRequest->special_details }}</p>
        @if ($serviceRequest->budget_range || $serviceRequest->needed_by)
            <dl class="border-line mt-4 grid gap-3 border-t pt-4 sm:grid-cols-2">
                @if ($serviceRequest->budget_range)
                    <div><dt class="text-muted text-sm">Rough budget</dt><dd class="font-medium">{{ $serviceRequest->budget_range }}</dd></div>
                @endif
                @if ($serviceRequest->needed_by)
                    <div><dt class="text-muted text-sm">Needed by</dt><dd class="font-medium">{{ $serviceRequest->needed_by->format('j F Y') }}</dd></div>
                @endif
            </dl>
        @endif
    </section>
@endif

@if ($serviceRequest->package_name)
    <section class="card">
        <h2 class="h-section">Package</h2>
        <p class="mt-2 font-medium">{{ $serviceRequest->package_name }}</p>
    </section>
@endif

@if ($serviceRequest->services->isNotEmpty())
    <section class="card">
        <h2 class="h-section">Services requested</h2>
        @foreach ($serviceRequest->services->groupBy('category_name') as $category => $items)
            <h3 class="text-muted mt-4 text-sm font-medium">{{ $category }}</h3>
            <ul class="divide-line mt-1 divide-y">
                @foreach ($items as $item)
                    <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2.5">
                        <span>{{ $item->name }}</span>
                        <span class="text-muted text-sm">{{ $item->priceLabel() }}</span>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </section>
@endif

@if ($serviceRequest->answers->isNotEmpty())
    <section class="card">
        <h2 class="h-section">Answers</h2>
        <dl class="mt-3 space-y-4">
            @foreach ($serviceRequest->answers as $answer)
                <div>
                    <dt class="text-muted text-sm">{{ $answer->question }}</dt>
                    <dd class="mt-0.5 whitespace-pre-line">{{ $answer->answer }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
@endif

@if ($serviceRequest->description)
    <section class="card">
        <h2 class="h-section">{{ $descriptionHeading ?? 'Your message' }}</h2>
        <p class="mt-3 whitespace-pre-line">{{ $serviceRequest->description }}</p>
    </section>
@endif

@if ($serviceRequest->files->isNotEmpty())
    <section class="card">
        <h2 class="h-section">Files</h2>
        <ul class="mt-3 space-y-2">
            @foreach ($serviceRequest->files as $file)
                <li class="flex flex-wrap items-baseline justify-between gap-x-4">
                    <a href="{{ route('files.download', $file) }}" class="link break-all">{{ $file->original_name }}</a>
                    <span class="text-muted text-sm">{{ $file->sizeLabel() }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
