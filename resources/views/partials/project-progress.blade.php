<div>
    <div class="flex items-center justify-between text-sm">
        <span class="text-muted">Progress</span>
        <span class="font-semibold">{{ $project->progress }}%</span>
    </div>
    <div class="bg-sand-dark mt-1.5 h-2.5 overflow-hidden rounded-full" role="progressbar" aria-valuenow="{{ $project->progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Project progress">
        <div class="bg-brand h-full rounded-full" style="width: {{ $project->progress }}%"></div>
    </div>
</div>
