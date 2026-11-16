@props(['title' => 'Client Portal'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="flex min-h-screen flex-col lg:flex-row">
        <div class="bg-navy flex flex-col justify-between gap-8 px-6 py-8 text-white lg:w-2/5 lg:px-12 lg:py-14">
            <x-logo />
            <div class="hidden lg:block">
                <p class="font-display text-4xl leading-tight font-bold">Your projects, quotes and messages in one place.</p>
                <p class="text-navy-soft mt-4 text-lg">Tell us what you need, follow progress and reach our team without digging through email.</p>
            </div>
            <p class="text-navy-soft hidden text-sm lg:block">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
        </div>
        <main class="flex flex-1 items-start justify-center px-5 py-10 lg:items-center lg:px-12">
            <div class="w-full max-w-md">
                <x-flash />
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
