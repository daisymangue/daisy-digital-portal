<x-layouts.guest title="Sign in">
    <h1 class="h-page">Sign in</h1>
    <p class="text-muted mt-2">Welcome back. Sign in to see your requests and projects.</p>

    <div x-data="{ mode: '{{ $errors->has('password') || old('password_mode') ? 'password' : 'link' }}' }" class="mt-8">
        <div class="border-line mb-6 grid grid-cols-2 gap-1 rounded-full border bg-white p-1" role="tablist">
            <button type="button" role="tab" :aria-selected="(mode === 'link').toString()" @click="mode = 'link'"
                    class="min-h-11 cursor-pointer rounded-full text-sm font-medium" :class="mode === 'link' ? 'bg-navy text-white' : 'text-ink'">Email me a link</button>
            <button type="button" role="tab" :aria-selected="(mode === 'password').toString()" @click="mode = 'password'"
                    class="min-h-11 cursor-pointer rounded-full text-sm font-medium" :class="mode === 'password' ? 'bg-navy text-white' : 'text-ink'">Use my password</button>
        </div>

        <form method="POST" action="{{ route('login.link.send') }}" x-show="mode === 'link'" class="space-y-5">
            @csrf
            <div>
                <label for="link-email" class="label">Email address</label>
                <input id="link-email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" placeholder="Your email is your username" class="field">
                <p class="text-muted mt-2 text-sm">We will email you a one-tap sign-in link. No password needed.</p>
            </div>
            <button type="submit" class="btn btn-primary w-full">Send sign-in link</button>
        </form>

        <form method="POST" action="{{ url('/login') }}" x-show="mode === 'password'" x-cloak class="space-y-5">
            @csrf
            <input type="hidden" name="password_mode" value="1">
            <div>
                <label for="email" class="label">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" placeholder="Your email is your username" class="field">
            </div>
            <div>
                <label for="password" class="label">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="field">
            </div>
            <label class="flex min-h-11 items-center gap-3">
                <input type="checkbox" name="remember" value="1" class="accent-navy size-5"> Keep me signed in on this device
            </label>
            <button type="submit" class="btn btn-primary w-full">Sign in</button>
            <p class="text-muted text-center text-sm">Forgot your password, or never set one? Choose <button type="button" class="link cursor-pointer" @click="mode = 'link'">Email me a link</button> instead.</p>
        </form>
    </div>

    <p class="mt-8 text-center">New here? <a href="{{ route('register') }}" class="link">Create an account</a></p>
</x-layouts.guest>
