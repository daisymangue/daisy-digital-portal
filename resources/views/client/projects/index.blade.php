<x-layouts.app title="Projects">
    <h1 class="h-page">Projects</h1>
    <p class="text-muted mt-1">Work we are doing for you. Open a project to message us, share files and approve work.</p>

    <div class="mt-8 grid gap-4 md:grid-cols-2">
        @forelse ($projects as $p)
            <a href="{{ route('projects.show', $p->id) }}" class="card hover:border-line-strong block space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-muted text-sm">{{ $p->number }}</p>
                        <p class="truncate text-lg font-semibold">{{ $p->title }}</p>
                    </div>
                    <x-status :status="$p->status" :label="$p->clientStatusLabel()" />
                </div>
                @include('partials.project-progress', ['project' => $p])
                @if ($p->unread_count || $p->needed_count)
                    <div class="flex flex-wrap gap-2 text-sm">
                        @if ($p->unread_count)
                            <span class="bg-brand rounded-full px-3 py-1 font-semibold text-white">{{ trans_choice(':count new message|:count new messages', $p->unread_count) }}</span>
                        @endif
                        @if ($p->needed_count)
                            <span class="rounded-full bg-amber-100 px-3 py-1 font-semibold text-amber-900">{{ trans_choice(':count thing we need from you|:count things we need from you', $p->needed_count) }}</span>
                        @endif
                    </div>
                @endif
            </a>
        @empty
            <div class="card text-center md:col-span-2">
                <p class="font-medium">No projects yet.</p>
                <p class="text-muted mt-1">A project opens here once you accept a quote and the deposit is paid.</p>
                <a href="{{ route('quotes.index') }}" class="btn btn-light mt-4">See your quotes</a>
            </div>
        @endforelse
    </div>
</x-layouts.app>
