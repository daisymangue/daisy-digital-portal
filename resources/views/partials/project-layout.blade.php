{{--
    Two halves: steps on the left, chat on the right (kept in view on a computer).
    On a phone a Steps / Chat switch shows one half at a time.
    Needs: $project, $admin, $messages, $files, $unread, chat urls, and $stepSets when $admin.
--}}
@php
    $forYou = $project->checklist->where('status', $admin ? 'submitted' : 'open')->count()
        + $project->milestones->where('status', $admin ? 'changes_requested' : 'awaiting_approval')->count()
        + ($admin ? $project->changeRequests->where('status', 'new')->count() : 0);
@endphp
<div x-data="{ pane: location.hash === '#chat' ? 'chat' : 'steps', show(p) { this.pane = p; if (p === 'chat') this.$dispatch('chat-shown') } }">
    <div class="bg-sand-dark mt-5 grid grid-cols-2 gap-1 rounded-full p-1 lg:hidden" role="tablist" aria-label="Project">
        <button type="button" role="tab" :aria-selected="(pane === 'steps').toString()" @click="show('steps')" :class="pane === 'steps' ? 'bg-white shadow-sm' : 'text-muted'" class="flex min-h-11 items-center justify-center gap-1.5 rounded-full font-semibold">
            Steps
            @if ($forYou)<span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-900">{{ $forYou }} {{ $admin ? 'to check' : 'for you' }}</span>@endif
        </button>
        <button type="button" role="tab" :aria-selected="(pane === 'chat').toString()" @click="show('chat')" :class="pane === 'chat' ? 'bg-white shadow-sm' : 'text-muted'" class="flex min-h-11 items-center justify-center gap-1.5 rounded-full font-semibold">
            Chat
            @if ($unread)<span class="bg-brand rounded-full px-1.5 py-0.5 text-xs text-white">{{ $unread }}<span class="sr-only"> unread</span></span>@endif
        </button>
    </div>

    <div class="mt-5 grid items-start gap-5 lg:grid-cols-2">
        <div class="min-w-0" :class="pane === 'steps' ? '' : 'hidden lg:block'">
            @include('partials.project-steps')
        </div>
        <div class="min-w-0 lg:sticky lg:top-6" :class="pane === 'chat' ? '' : 'hidden lg:block'">
            @include('partials.project-chat')
        </div>
    </div>
</div>
