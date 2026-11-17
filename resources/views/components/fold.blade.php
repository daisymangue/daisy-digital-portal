@props(['title', 'summary' => null, 'open' => false])
{{--
    A card that folds away. Used for actions you only need now and then, so the side column
    stays short. Built on <details>, so it works with the keyboard and screen readers, no script.
--}}
<details {{ $attributes->merge(['class' => 'card group p-0 sm:p-0']) }} @if ($open) open @endif>
    <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-3 px-5 py-3 sm:px-6 [&::-webkit-details-marker]:hidden">
        <span class="min-w-0">
            <span class="h-section block">{{ $title }}</span>
            @if ($summary)<span class="text-muted block truncate text-sm">{{ $summary }}</span>@endif
        </span>
        <svg class="text-muted size-5 shrink-0 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.17l3.71-3.94a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
    </summary>
    <div class="border-line space-y-3 border-t px-5 py-4 sm:px-6">
        {{ $slot }}
    </div>
</details>
