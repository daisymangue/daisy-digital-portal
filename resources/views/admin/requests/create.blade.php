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
        <form method="POST" action="{{ route('admin.requests.store') }}" enctype="multipart/form-data" class="mt-8">
            @csrf

            @include('partials.request-form', ['admin' => true])
        </form>
    @endif
</x-layouts.app>
