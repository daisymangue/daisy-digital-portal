{{-- The last step for the client: the handover pack, confirming it, and (when asked) a testimonial. --}}
<div id="handover" class="border-line border-t px-5 py-5 {{ $project->handover_confirmed_at ? '' : 'bg-violet-50/60' }}">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h3 class="font-semibold">Your handover pack</h3>
        @if ($project->handover_confirmed_at)
            <span class="text-xs font-semibold text-green-800">Confirmed {{ $project->handover_confirmed_at->format('j M') }}</span>
        @else
            <x-status status="awaiting_approval" label="Ready for you" />
        @endif
    </div>
    <p class="text-muted mt-1 text-sm">Everything you need to look after your work. Keep a copy somewhere safe.</p>
    @if ($project->handover_notes)
        <div class="mt-3 rounded-xl bg-white p-4 text-sm whitespace-pre-line">{{ $project->handover_notes }}</div>
    @endif
    @foreach ($project->handoverFiles as $f)
        <a href="{{ route('files.download', $f) }}" class="link mt-2 block text-sm break-all">{{ $f->original_name }} <span class="text-muted text-xs font-normal">{{ $f->sizeLabel() }}</span></a>
    @endforeach

    @if (! $project->handover_confirmed_at)
        <form method="POST" action="{{ route('projects.handover.confirm', $project->id) }}" class="mt-4 space-y-2">
            @csrf
            <p class="text-sm">Got everything? If something is missing, tell us in the chat. Otherwise confirm below.</p>
            <label for="confirmed_name" class="label">Type your name to confirm</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input id="confirmed_name" name="confirmed_name" required maxlength="120" value="{{ auth()->user()->name }}" class="field min-w-0 flex-1">
                <button type="submit" class="btn btn-primary">I have received everything</button>
            </div>
        </form>
    @endif

    @if ($project->testimonial?->status === 'requested')
        <form method="POST" action="{{ route('projects.testimonial', $project->id) }}" class="mt-5 space-y-4 rounded-xl bg-white p-4" x-data="{ rating: {{ (int) old('rating', 0) }} }">
            @csrf
            <h3 class="font-semibold">How did we do?</h3>
            <fieldset>
                <legend class="label">Your rating</legend>
                <div class="flex gap-1">
                    @for ($i = 1; $i <= 5; $i++)
                        <label class="cursor-pointer">
                            <input type="radio" name="rating" value="{{ $i }}" x-model.number="rating" class="peer sr-only" required>
                            <span class="flex size-11 items-center justify-center rounded-lg text-3xl peer-focus-visible:outline-2 peer-focus-visible:outline-[var(--color-brand)]"
                                  :class="rating >= {{ $i }} ? 'text-amber-500' : 'text-line-strong'" aria-hidden="true">★</span>
                            <span class="sr-only">{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}</span>
                        </label>
                    @endfor
                </div>
                @error('rating')<p class="mt-1 text-sm text-red-800">{{ $message }}</p>@enderror
            </fieldset>
            <div>
                <label for="t-body" class="label">A few words about working with us <span class="text-muted font-normal">(optional)</span></label>
                <textarea id="t-body" name="body" rows="3" maxlength="2000" class="field">{{ old('body') }}</textarea>
            </div>
            <div>
                <label for="t-name" class="label">Name to show with it</label>
                <input id="t-name" name="display_name" maxlength="120" value="{{ old('display_name', auth()->user()->name.', '.$project->client->business_name) }}" class="field">
            </div>
            <label class="flex items-start gap-3 text-sm">
                <input type="checkbox" name="can_publish" value="1" class="accent-navy mt-0.5 size-5 shrink-0" @checked(old('can_publish', true))>
                <span>You may share this on your website and social media.</span>
            </label>
            <button type="submit" class="btn btn-dark">Send feedback</button>
        </form>
    @elseif ($project->testimonial && $project->testimonial->status !== 'requested')
        <div class="mt-4 rounded-xl bg-white p-4">
            <p class="font-medium">Thank you for your feedback</p>
            <p class="mt-1 text-2xl text-amber-500" aria-label="{{ $project->testimonial->rating }} out of 5 stars">{{ str_repeat('★', $project->testimonial->rating) }}</p>
            @if (session('review_prompt'))
                <p class="mt-3">Would you also share it on Google? It helps other businesses find us.</p>
                <a href="{{ \App\Models\Setting::get('google_review_url') }}" target="_blank" rel="noopener" class="btn btn-primary mt-3">Leave a Google review</a>
            @endif
        </div>
    @endif
</div>
