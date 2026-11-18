@props(['title' => 'Dashboard'])
@php
    $user = auth()->user();
    // Unread chat messages from the other side, shown as a count next to Projects.
    $unread = \App\Models\ProjectMessage::whereNull('read_at')->where('from_team', $user->isClient())
        ->when($user->isClient(), fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $user->client_id)))
        ->count();
    $nav = $user->isAdmin()
        ? [
            ['Dashboard', route('admin.dashboard'), request()->routeIs('admin.dashboard')],
            ['Requests', route('admin.requests.index'), request()->routeIs('admin.requests.*')],
            ['Projects', route('admin.projects.index'), request()->routeIs('admin.projects.*'), $unread],
            ['Quotes', route('admin.quotes.index'), request()->routeIs('admin.quotes.*')],
            ['Billing', route('admin.care-plans.index'), request()->routeIs('admin.invoices.*', 'admin.care-plans.*', 'admin.plan-templates.*')],
            ['Clients', route('admin.clients.index'), request()->routeIs('admin.clients.*')],
            ['Services', route('admin.services.index'), request()->routeIs('admin.services.*', 'admin.categories.*')],
            ['Packages', route('admin.packages.index'), request()->routeIs('admin.packages.*')],
            ['Testimonials', route('admin.testimonials.index'), request()->routeIs('admin.testimonials.*')],
            ['Settings', route('admin.settings'), request()->routeIs('admin.settings')],
            ['Account', route('account'), request()->routeIs('account')],
        ]
        : [
            ['Dashboard', route('dashboard'), request()->routeIs('dashboard')],
            ['Projects', route('projects.index'), request()->routeIs('projects.*'), $unread],
            ['My Requests', route('requests.index'), request()->routeIs('requests.index', 'requests.show')],
            ['Quotes', route('quotes.index'), request()->routeIs('quotes.*')],
            ['Billing', route('invoices.index'), request()->routeIs('invoices.*')],
            ['Start a Request', route('requests.create'), request()->routeIs('requests.create')],
            ['Account', route('account'), request()->routeIs('account')],
        ];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} · {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/icon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="relative min-h-screen lg:flex" x-data="{ open: false }" @keydown.escape.window="open = false">
        <header class="bg-navy relative z-40 flex items-center justify-between px-5 py-4 lg:hidden">
            <x-logo />
            <button type="button" class="btn min-h-11 border border-white/30 px-4 text-white" @click="open = !open" :aria-expanded="open.toString()" aria-controls="main-nav" x-text="open ? 'Close' : 'Menu'">Menu</button>
        </header>

        {{-- On a phone the menu drops down over the page instead of filling the screen. --}}
        <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-20 bg-navy/40 lg:hidden" @click="open = false" aria-hidden="true"></div>

        <nav id="main-nav" aria-label="Main" class="bg-navy absolute inset-x-0 top-[4.75rem] z-30 flex-col gap-3 rounded-b-2xl px-3 pb-3 shadow-xl lg:static lg:flex lg:min-h-screen lg:w-60 lg:shrink-0 lg:gap-8 lg:rounded-none lg:px-5 lg:py-7 lg:shadow-none" :class="open ? 'flex' : 'hidden'">
            <div class="hidden px-3 lg:block">
                <x-logo />
                @if ($user->isAdmin())
                    <span class="bg-sand-dark text-navy mt-3 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wider">ADMIN</span>
                @endif
            </div>
            <ul class="grid {{ count($nav) > 8 ? 'grid-cols-3' : 'grid-cols-2' }} gap-1 lg:flex lg:flex-col">
                @foreach ($nav as $item)
                    @php
                        [$label, $url, $active] = $item;
                        $badge = $item[3] ?? 0;
                    @endphp
                    <li>
                        <a href="{{ $url }}" @if ($active) aria-current="page" @endif
                           class="flex min-h-11 items-center justify-between gap-2 rounded-xl px-3 text-sm lg:text-base {{ $active ? 'bg-white/10 font-medium text-white' : 'text-navy-soft hover:bg-white/5 hover:text-white' }}">
                            <span>{{ $label }}</span>
                            @if ($badge > 0)
                                <span class="bg-brand rounded-full px-2 py-0.5 text-xs font-semibold text-white">{{ $badge }}<span class="sr-only"> unread {{ $badge === 1 ? 'message' : 'messages' }}</span></span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="flex items-center justify-between gap-3 border-t border-white/10 px-3 pt-2 lg:mt-auto lg:block lg:pt-5">
                <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-white">{{ $user->name }}</p>
                    <p class="text-navy-soft truncate text-sm">{{ $user->isAdmin() ? 'Administrator' : $user->client->business_name }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0 lg:mt-3">
                    @csrf
                    <button type="submit" class="text-navy-soft min-h-11 cursor-pointer underline-offset-2 hover:text-white hover:underline">Sign out</button>
                </form>
            </div>
        </nav>

        <main class="min-w-0 flex-1 px-5 py-8 sm:px-8 lg:px-12 lg:py-10">
            <div class="mx-auto max-w-6xl">
                <x-flash />
                {{ $slot }}
            </div>
        </main>
    </div>
</body>
</html>
