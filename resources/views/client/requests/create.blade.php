<x-layouts.app title="Start a request">
    <h1 class="h-page">What can we help you with?</h1>
    <p class="text-muted mt-1">Choose a package, pick services, or describe what you need. If it is IT related, we can help.</p>

    <form method="POST" action="{{ route('requests.store') }}" enctype="multipart/form-data" class="mt-8">
        @csrf
        @include('partials.request-form', ['admin' => false])
    </form>
</x-layouts.app>
