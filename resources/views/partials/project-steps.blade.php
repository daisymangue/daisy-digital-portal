@if ($project->milestones->isEmpty())
    <p class="text-muted mt-2">{{ auth()->user()->isAdmin() ? 'No steps yet.' : 'We will set out the steps shortly.' }}</p>
@else
    <ol class="mt-4 space-y-4">
        @foreach ($project->milestones as $m)
            <li class="flex gap-3">
                <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $m->status === 'done' ? 'bg-green-600 text-white' : ($m->status === 'pending' ? 'border-line-strong text-muted border-2' : 'bg-brand text-white') }}" aria-hidden="true">{{ $m->status === 'done' ? '✓' : $loop->iteration }}</span>
                <div class="min-w-0">
                    <p class="font-medium {{ $m->status === 'done' ? 'text-muted' : '' }}">{{ $m->title }}</p>
                    <p class="text-muted text-sm">
                        {{ $m->statusLabel() }}
                        @if ($m->due_date && $m->status !== 'done') · by {{ $m->due_date->format('j M') }} @endif
                        @if ($m->approved_at) · approved by {{ $m->approved_name }} {{ $m->approved_at->format('j M') }} @endif
                    </p>
                    @if ($m->status === 'changes_requested' && $m->client_feedback)
                        <p class="mt-1 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-950">{{ auth()->user()->isAdmin() ? 'Client feedback' : 'Your feedback' }}: {{ $m->client_feedback }}</p>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
@endif
