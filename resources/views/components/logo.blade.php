@php
    $logo = \App\Support\Brand::logoUrl();
@endphp
<a href="{{ url('/') }}" class="inline-flex items-center">
    @if ($logo)
        {{-- On a white tile so dark and light logos both show on the navy menu. --}}
        <span class="inline-flex rounded-lg bg-white px-2.5 py-1.5"><img src="{{ $logo }}" alt="{{ \App\Models\Setting::get('business_name') }}" class="h-8 w-auto max-w-40 object-contain"></span>
    @else
        <span class="inline-flex flex-col leading-none">
            <span class="text-navy-soft text-xs tracking-[0.2em]">DAISY DIGITAL</span>
            <span class="mt-1 text-xl font-semibold tracking-[0.25em] text-white">AGENCY</span>
        </span>
    @endif
</a>
