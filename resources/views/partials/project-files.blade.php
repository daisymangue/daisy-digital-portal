@if ($files->isEmpty())
    <p class="text-muted">No files yet.</p>
@else
    <ul class="divide-line max-h-80 divide-y overflow-y-auto">
        @foreach ($files as $f)
            <li class="py-2.5">
                <a href="{{ route('files.download', $f) }}" class="link block break-all">{{ $f->original_name }}</a>
                <p class="text-muted text-xs">{{ $f->sizeLabel() }} · {{ $f->uploader?->isAdmin() ? 'Daisy Digital' : ($f->uploader?->name ?? 'Unknown') }} · {{ $f->created_at->format('j M') }}</p>
            </li>
        @endforeach
    </ul>
@endif
