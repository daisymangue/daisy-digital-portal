{{-- The last step for the team: prepare and send the handover pack, and ask for a testimonial. --}}
<div id="handover" class="border-line border-t px-5 py-4" x-data="{ open: {{ $errors->has('handover_notes') ? 'true' : 'false' }} }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="min-w-0">
            <h3 class="font-semibold">Handover pack</h3>
            <p class="text-muted text-sm">
                @if ($project->handover_sent_at)
                    Sent {{ $project->handover_sent_at->format('j M') }}.
                    {{ $project->handover_confirmed_at ? 'Confirmed by '.$project->handover_confirmed_name.' on '.$project->handover_confirmed_at->format('j M').'.' : 'Waiting for the client to confirm.' }}
                @else
                    Guides, how-tos and final files the client keeps. Not sent yet.
                @endif
            </p>
        </div>
        <button type="button" class="link min-h-11 cursor-pointer text-sm" @click="open = !open" x-text="open ? 'Close' : '{{ $project->handover_sent_at ? 'Edit' : 'Prepare' }}'">Prepare</button>
    </div>

    <form x-show="open" x-cloak method="POST" action="{{ route('admin.projects.handover', $project) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
        @csrf
        <div>
            <label for="handover_notes" class="label">Notes</label>
            <textarea id="handover_notes" name="handover_notes" rows="7" maxlength="10000" class="field" placeholder="Website: log in at yoursite.co.za/wp-admin&#10;Domain renews: 12 March each year&#10;Support: message us in the portal">{{ old('handover_notes', $project->handover_notes) }}</textarea>
            <p class="text-muted mt-1 text-sm">No passwords here. Share those another safe way.</p>
            @error('handover_notes')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
        </div>
        @foreach ($project->handoverFiles as $f)
            <a href="{{ route('files.download', $f) }}" class="link block text-sm break-all">{{ $f->original_name }}</a>
        @endforeach
        <x-file-drop id="handover-files" label="Guides, brand files, final designs" />
        <div class="flex flex-wrap gap-2">
            <button type="submit" name="send" value="0" class="btn btn-light min-h-10 px-4 text-sm">Save draft</button>
            <button type="submit" name="send" value="1" class="btn btn-primary min-h-10 px-4 text-sm">{{ $project->handover_sent_at ? 'Save and send again' : 'Send to client' }}</button>
        </div>
    </form>

    <div class="mt-3 text-sm">
        @if ($project->testimonial && $project->testimonial->status !== 'requested')
            <p><span class="text-amber-500" aria-label="{{ $project->testimonial->rating }} out of 5 stars">{{ str_repeat('★', $project->testimonial->rating) }}</span> {{ $project->testimonial->body ? '"'.Str::limit($project->testimonial->body, 120).'"' : '' }}</p>
            <p class="text-muted">{{ $project->testimonial->display_name }} · {{ $project->testimonial->can_publish ? 'OK to publish' : 'Not for publishing' }}</p>
        @else
            <form method="POST" action="{{ route('admin.projects.testimonial', $project) }}" class="flex flex-wrap items-center gap-x-3">
                @csrf
                <span class="text-muted">{{ $project->testimonial ? 'Testimonial asked on '.$project->testimonial->requested_at?->format('j M').'.' : 'Testimonial:' }}</span>
                <button type="submit" class="link min-h-9 cursor-pointer">{{ $project->testimonial ? 'Ask again' : 'Ask for one now' }}</button>
            </form>
        @endif
    </div>
</div>
