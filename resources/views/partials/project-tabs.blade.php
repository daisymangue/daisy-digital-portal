{{--
    Tab bar for the project page. Every tab is on the page already, so switching is instant.
    The last tab used is remembered per project, so after sending a form you land back on it.
    Needs: $tabs = [key => [label, badge]], $default, $project
--}}
<div class="border-line sticky top-0 z-10 -mx-5 mt-8 border-b bg-sand/95 px-5 backdrop-blur sm:-mx-8 sm:px-8 lg:mx-0 lg:px-0">
    <div role="tablist" aria-label="Project sections" class="-mb-px flex gap-1 overflow-x-auto">
        @foreach ($tabs as $key => [$label, $badge, $short])
            <button type="button" role="tab" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                :aria-selected="(tab === '{{ $key }}').toString()" :tabindex="tab === '{{ $key }}' ? 0 : -1"
                @click="show('{{ $key }}')"
                @keydown.arrow-right.prevent="move(1)" @keydown.arrow-left.prevent="move(-1)"
                class="flex min-h-12 flex-1 cursor-pointer items-center justify-center gap-1.5 border-b-2 px-1 text-sm font-medium whitespace-nowrap sm:flex-none sm:gap-2 sm:px-4 sm:text-base"
                :class="tab === '{{ $key }}' ? 'border-brand text-ink' : 'border-transparent text-muted hover:text-ink'">
                <span class="sm:hidden">{{ $short }}</span><span class="hidden sm:inline">{{ $label }}</span>
                @if ($badge)
                    <span class="rounded-full px-1.5 py-0.5 sm:px-2 text-xs font-semibold {{ $key === 'messages' ? 'bg-brand text-white' : (in_array($key, ['needed', 'progress', 'handover'], true) ? 'bg-amber-100 text-amber-900' : 'bg-sand-dark text-muted') }}">{{ $badge }}</span>
                @endif
            </button>
        @endforeach
    </div>
</div>
