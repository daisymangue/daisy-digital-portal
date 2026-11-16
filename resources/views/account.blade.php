<x-layouts.app title="Account">
    <h1 class="h-page">Account settings</h1>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('account') }}" class="card space-y-5">
            @csrf
            @method('PUT')
            <h2 class="h-section">Your details</h2>
            <div>
                <label for="name" class="label">Your name</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required class="field">
            </div>
            @if ($user->isClient())
                <div>
                    <label for="business_name" class="label">Business name</label>
                    <input id="business_name" name="business_name" value="{{ old('business_name', $user->client->business_name) }}" required class="field">
                </div>
            @endif
            <div>
                <label for="email" class="label">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required class="field">
            </div>
            <div>
                <label for="phone" class="label">Phone or WhatsApp number</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" class="field">
            </div>
            <button type="submit" class="btn btn-primary">Save details</button>
        </form>

        <form method="POST" action="{{ route('account.password') }}" class="card space-y-5 self-start">
            @csrf
            @method('PUT')
            <h2 class="h-section">{{ $user->has_password ? 'Change password' : 'Set a password' }}</h2>
            @if ($user->has_password)
                <div>
                    <label for="current_password" class="label">Current password</label>
                    <input id="current_password" name="current_password" type="password" required autocomplete="current-password" class="field">
                </div>
            @else
                <p class="text-muted text-sm">You sign in with email links. Set a password if you would also like to sign in with one.</p>
            @endif
            <div>
                <label for="password" class="label">New password</label>
                <input id="password" name="password" type="password" required minlength="10" autocomplete="new-password" class="field">
            </div>
            <div>
                <label for="password_confirmation" class="label">Confirm new password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field">
            </div>
            <button type="submit" class="btn btn-dark">{{ $user->has_password ? 'Change password' : 'Set password' }}</button>
        </form>
    </div>
</x-layouts.app>
