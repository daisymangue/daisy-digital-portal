<x-layouts.app title="New request for a client">
    <a href="{{ route('admin.requests.index') }}" class="link text-sm">Back to requests</a>
    <h1 class="h-page mt-3">New request for a client</h1>
    <p class="text-muted mt-1">For work that came in by phone, email, WhatsApp or in person. The client does not need a portal login.</p>

    @if ($clients->isEmpty())
        <div class="card mt-8">
            <p>Add the client first.</p>
            <a href="{{ route('admin.clients.create') }}" class="btn btn-primary mt-4">Add a client</a>
        </div>
    @else
        <form method="POST" action="{{ route('admin.requests.store') }}" enctype="multipart/form-data" class="mt-8 space-y-10">
            @csrf
            <section class="card grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="client_id" class="label">Client</label>
                    <select id="client_id" name="client_id" required class="field">
                        <option value="">Choose a client</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected(old('client_id', $selected) === $c->id)>{{ $c->business_name }}</option>
                        @endforeach
                    </select>
                    <p class="text-muted mt-1.5 text-sm">Not listed? <a href="{{ route('admin.clients.create') }}" class="link">Add a client</a> first.</p>
                </div>
                <div>
                    <label for="source" class="label">How did it come in?</label>
                    <select id="source" name="source" required class="field">
                        @foreach (\App\Models\ServiceRequest::SOURCES as $key => $label)
                            @continue($key === 'portal')
                            <option value="{{ $key }}" @selected(old('source') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </section>

            @include('partials.request-form', ['admin' => true])
        </form>
    @endif
</x-layouts.app>
