<x-layouts.app title="Start a request">
    <h1 class="h-page">What can we help you with?</h1>
    <p class="text-muted mt-1">Choose a package, pick individual services, or simply describe what you need.</p>

    <form method="POST" action="{{ route('requests.store') }}" enctype="multipart/form-data" class="mt-8">
        @csrf
        @include('partials.request-form', ['admin' => false])
    </form>
</x-layouts.app>
