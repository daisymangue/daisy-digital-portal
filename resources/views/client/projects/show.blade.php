@php
    $total = $project->milestones->count();
    $done = $project->milestones->where('status', 'done')->count();
@endphp
<x-layouts.app :title="$project->number.' '.$project->title">
    <a href="{{ route('projects.index') }}" class="link inline-flex min-h-11 items-center text-sm">Back to projects</a>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-muted text-sm">{{ $project->number }}@if ($project->quote) · <a href="{{ route('quotes.show', $project->quote_id) }}" class="link">Quote {{ $project->quote->reference() }}</a>@endif</p>
            <h1 class="h-page">{{ $project->title }}</h1>
            <p class="text-muted mt-1">
                @if ($project->due_date)Aiming to finish by {{ $project->due_date->format('j M Y') }}@else Started {{ ($project->start_date ?? $project->created_at)->format('j M Y') }}@endif
                @if ($total) · {{ $done }} of {{ $total }} steps done @endif
            </p>
        </div>
        <x-status :status="$project->status" :label="$project->clientStatusLabel()" />
    </div>
    <div class="bg-sand-dark mt-4 h-2 overflow-hidden rounded-full" role="progressbar" aria-label="Progress" aria-valuenow="{{ $project->progress }}" aria-valuemin="0" aria-valuemax="100">
        <div class="bg-brand h-2 rounded-full" style="width: {{ $project->progress }}%"></div>
    </div>

    @include('partials.project-layout', [
        'admin' => false,
        'feedUrl' => route('projects.messages', $project->id),
        'postUrl' => route('projects.messages.store', $project->id),
        'uploadUrl' => route('projects.files', $project->id),
        'closed' => $project->status === 'cancelled',
    ])
</x-layouts.app>
