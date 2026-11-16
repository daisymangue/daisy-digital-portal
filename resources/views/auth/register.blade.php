<x-layouts.guest title="Create an account">
    <h1 class="h-page">Create your account</h1>
    <p class="text-muted mt-2">It takes a minute. Then you can tell us what you need.</p>

    <form method="POST" action="{{ url('/register') }}" class="mt-8 space-y-5">
        @csrf
        <div>
            <label for="name" class="label">Your name</label>
            <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name" class="field">
        </div>
        <div>
            <label for="business_name" class="label">Business name</label>
            <input id="business_name" name="business_name" value="{{ old('business_name') }}" required autocomplete="organization" class="field">
            <p class="text-muted mt-1.5 text-sm">No business? Use your own name.</p>
        </div>
        <div>
            <label for="email" class="label">Email address</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="field">
        </div>
        <div>
            <label for="phone" class="label">Phone or WhatsApp number <span class="text-muted font-normal">(optional)</span></label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" class="field">
        </div>
        <div>
            <label for="password" class="label">Password</label>
            <input id="password" name="password" type="password" required minlength="10" autocomplete="new-password" class="field">
            <p class="text-muted mt-1.5 text-sm">At least 10 characters.</p>
        </div>
        <div>
            <label for="password_confirmation" class="label">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field">
        </div>
        <label class="flex items-start gap-3">
            <input type="checkbox" name="accept_terms" value="1" required class="accent-navy mt-0.5 size-5 shrink-0" @checked(old('accept_terms'))>
            <span>I agree to the <a href="{{ config('portal.terms_url') }}" class="link" target="_blank" rel="noopener">terms</a> and <a href="{{ config('portal.privacy_url') }}" class="link" target="_blank" rel="noopener">privacy policy</a>.</span>
        </label>
        <button type="submit" class="btn btn-primary w-full">Create account</button>
    </form>

    <p class="mt-8 text-center">Already have an account? <a href="{{ route('login') }}" class="link">Sign in</a></p>
</x-layouts.guest>
