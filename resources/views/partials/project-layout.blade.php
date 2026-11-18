{{--
    Two halves: steps on the left, chat on the right (kept in view on a computer).
    On a phone they stack: the current step (the rest folded), then the chat and files.
    Needs: $project, $admin, $messages, $files, $unread, chat urls, and $stepSets when $admin.
--}}
<div class="mt-5 grid items-start gap-5 lg:grid-cols-2">
    <div class="min-w-0">
        @include('partials.project-steps')
    </div>
    <div class="min-w-0 lg:sticky lg:top-6">
        @include('partials.project-chat')
    </div>
</div>
