@if (session('status'))
    <div role="status" class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-green-900">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div role="alert" class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-900">
        <ul class="space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
