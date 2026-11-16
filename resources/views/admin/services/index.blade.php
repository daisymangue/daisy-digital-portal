<x-layouts.app title="Services">
    <h1 class="h-page">Services</h1>
    <p class="text-muted mt-1">These categories are the cards clients see when they start a request. Open one to edit its services, prices and questions.</p>

    <div class="mt-8 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($categories as $category)
            <a href="{{ route('admin.categories.show', $category) }}" class="card hover:border-line-strong block">
                <div class="flex items-start justify-between gap-2">
                    <p class="font-semibold">{{ $category->name }}</p>
                    @unless ($category->is_active)<span class="pill bg-sand-dark text-muted">Hidden</span>@endunless
                </div>
                <p class="text-muted mt-1 text-sm">{{ $category->services_count }} services · {{ $category->questions_count }} questions</p>
            </a>
        @endforeach
    </div>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="card mt-8 max-w-xl space-y-4">
        @csrf
        <h2 class="h-section">Add a category</h2>
        <div>
            <label for="name" class="label">Name</label>
            <input id="name" name="name" required maxlength="120" class="field">
        </div>
        <div>
            <label for="description" class="label">Short description <span class="text-muted font-normal">(shown on the card)</span></label>
            <input id="description" name="description" maxlength="200" class="field">
        </div>
        <button type="submit" class="btn btn-dark">Add category</button>
    </form>
</x-layouts.app>
