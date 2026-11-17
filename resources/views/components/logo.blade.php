@props(['size' => 'h-9'])
@php
    // An uploaded logo (Settings) wins. Otherwise the built-in white version, made for the navy menu.
    $uploaded = \App\Support\Brand::logoUrl();
@endphp
<a href="{{ url('/') }}" class="inline-flex items-center">
    @if ($uploaded)
        <span class="inline-flex rounded-lg bg-white px-2.5 py-1.5"><img src="{{ $uploaded }}" alt="{{ \App\Models\Setting::get('business_name') }}" class="h-8 w-auto max-w-40 object-contain"></span>
    @else
        <img src="{{ asset('images/logo-light.png') }}" alt="Daisy Digital Agency" width="411" height="99" class="{{ $size }} w-auto">
    @endif
</a>
