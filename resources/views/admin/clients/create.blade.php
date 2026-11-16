<x-layouts.app title="Add a client">
    <a href="{{ route('admin.clients.index') }}" class="link text-sm">Back to clients</a>
    <h1 class="h-page mt-3">Add a client</h1>
    <p class="text-muted mt-1">For clients who contact you by phone, email or in person. They do not need to log in.</p>

    <form method="POST" action="{{ route('admin.clients.store') }}" class="card mt-8 max-w-2xl space-y-5" x-data="{ access: {{ old('portal_access') ? 'true' : 'false' }} }">
        @csrf
        @include('admin.clients.fields', ['client' => null])

        <label class="flex items-start gap-3">
            <input type="checkbox" name="portal_access" value="1" x-model="access" class="accent-navy mt-0.5 size-5 shrink-0">
            <span>
                <span class="font-medium">Give them portal access now</span>
                <span class="text-muted block text-sm">Emails them a sign-in link. From then on they also get emails for quotes and invoices. You can do this later instead.</span>
            </span>
        </label>
        <p x-show="access" x-cloak class="text-sm text-amber-900">An email address is needed for portal access.</p>

        <button type="submit" class="btn btn-primary">Add client</button>
    </form>
</x-layouts.app>
