<x-layouts.app title="Import services from Excel">
    <a href="{{ route('admin.services.index') }}" class="link text-sm">Back to services</a>
    <h1 class="h-page mt-3">Import services from Excel</h1>
    <p class="text-muted mt-1">Update prices and add services in Excel, then upload the file here.</p>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <section class="card space-y-4">
            <h2 class="h-section">1. Download the current list</h2>
            <p>Always start from the latest list, so you edit what is live now. Each sheet is one category, with the columns Category, Service, Price type and Price.</p>
            <a href="{{ route('admin.services.export') }}" class="btn btn-light">Download current list</a>
        </section>

        <form method="POST" action="{{ route('admin.services.import.preview') }}" enctype="multipart/form-data" class="card space-y-4">
            @csrf
            <h2 class="h-section">2. Upload your edited file</h2>
            <div>
                <label for="sheet" class="label">Excel file (.xlsx)</label>
                <input id="sheet" name="sheet" type="file" required accept=".xlsx,.xls,.csv" class="field file:bg-sand-dark file:mr-3 file:rounded-full file:px-4 file:py-1.5 file:font-medium">
            </div>
            <p class="text-muted text-sm">Nothing changes yet. You will see every change and confirm it first.</p>
            <button type="submit" class="btn btn-primary">Check the file</button>
        </form>
    </div>

    <section class="card mt-6">
        <h2 class="h-section">Rules</h2>
        <ul class="mt-3 list-disc space-y-1.5 pl-5">
            <li>Price type is one of Fixed, From, Hourly, Monthly or Quote.</li>
            <li>Price is the amount in rand, such as 450 or 1500. Leave it empty for Quote.</li>
            <li>A new row adds a service. A new Category name adds a category.</li>
            <li>Services removed from the sheet can be hidden. They are never deleted, and past requests and quotes are not affected.</li>
            <li>"Shown", "Popular" and the questions per category stay as you set them in the portal.</li>
        </ul>
    </section>
</x-layouts.app>
