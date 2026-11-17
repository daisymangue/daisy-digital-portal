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
            ['Invoices', route('admin.invoices.index'), request()->routeIs('admin.invoices.*')],
            ['Clients', route('admin.clients.index'), request()->routeIs('admin.clients.*')],
            ['Services', route('admin.services.index'), request()->routeIs('admin.services.*', 'admin.categories.*')],
            ['Packages', route('admin.packages.index'), request()->routeIs('admin.packages.*')],
            ['Settings', route('admin.settings'), request()->routeIs('admin.settings')],
            ['Account', route('account'), request()->routeIs('account')],
        ]
        : [
            ['Dashboard', route('dashboard'), request()->routeIs('dashboard')],
            ['Projects', route('projects.index'), request()->routeIs('projects.*'), $unread],
            ['My Requests', route('requests.index'), request()->routeIs('requests.index', 'requests.show')],
            ['Quotes', route('quotes.index'), request()->routeIs('quotes.*')],
            ['Invoices', route('invoices.index'), request()->routeIs('invoices.*')],
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
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="min-h-screen lg:flex" x-data="{ open: false }">
        <header class="bg-navy flex items-center justify-between px-5 py-4 lg:hidden">
            <x-logo />
            <button type="button" class="btn min-h-11 border border-white/30 px-4 text-white" @click="open = !open" :aria-expanded="open.toString()" aria-controls="main-nav">Menu</button>
        </header>

        <nav id="main-nav" aria-label="Main" class="bg-navy flex-col gap-8 px-4 pb-6 lg:flex lg:min-h-screen lg:w-60 lg:shrink-0 lg:px-5 lg:py-7" :class="open ? 'flex' : 'hidden'">
            <div class="hidden px-3 lg:block">
                <x-logo />
                @if ($user->isAdmin())
                    <span class="bg-sand-dark text-navy mt-3 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wider">ADMIN</span>
                @endif
            </div>
            <ul class="flex flex-col gap-1">
                @foreach ($nav as $item)
                    @php
                        [$label, $url, $active] = $item;
                        $badge = $item[3] ?? 0;
                    @endphp
                    <li>
                        <a href="{{ $url }}" @if ($active) aria-current="page" @endif
                           class="flex min-h-11 items-center justify-between gap-2 rounded-xl px-3 {{ $active ? 'bg-white/10 font-medium text-white' : 'text-navy-soft hover:bg-white/5 hover:text-white' }}">
                            <span>{{ $label }}</span>
                            @if ($badge > 0)
                                <span class="bg-brand rounded-full px-2 py-0.5 text-xs font-semibold text-white">{{ $badge }}<span class="sr-only"> unread {{ $badge === 1 ? 'message' : 'messages' }}</span></span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="mt-auto border-t border-white/10 px-3 pt-5">
                <p class="truncate text-sm font-medium text-white">{{ $user->name }}</p>
                <p class="text-navy-soft truncate text-sm">{{ $user->isAdmin() ? 'Administrator' : $user->client->business_name }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
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
