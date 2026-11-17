<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => strtolower($data['email']), 'password' => $data['password']], $request->boolean('remember'))) {
            AuditLog::record('login.failed', null, ['email' => strtolower($data['email'])]);

            return back()->withErrors(['email' => 'Those details do not match our records.'])->onlyInput('email');
        }

        if (! $request->user()->canSignIn()) {
            Auth::logout();

            return back()->withErrors(['email' => 'This account is closed. Please contact us if you need access again.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        AuditLog::record('login.password', $request->user());

        return redirect(self::destination($request));
    }

    /**
     * Go back to the page the user was trying to open, but only if it belongs to their role.
     * Otherwise an admin who once opened a client page (or the reverse) would land on a 404.
     */
    public static function destination(Request $request): string
    {
        $user = $request->user();
        $intended = $request->session()->pull('url.intended');
        if (! $intended) {
            return $user->homeRoute();
        }

        $path = '/'.ltrim((string) parse_url($intended, PHP_URL_PATH), '/');
        $isAdminPage = $path === '/admin' || str_starts_with($path, '/admin/');
        $isShared = $path === '/account' || str_starts_with($path, '/files/');

        return $isShared || $isAdminPage === $user->isAdmin() ? $intended : $user->homeRoute();
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'business_name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(10)],
            'accept_terms' => ['accepted'],
        ], [
            'accept_terms.accepted' => 'Please accept the terms and privacy policy to continue.',
            'email.unique' => 'This email already has an account. Sign in instead, and choose "Email me a link" if you do not have a password.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $client = Client::create(['business_name' => $data['business_name'], 'phone' => $data['phone'] ?? null]);

            $user = new User([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
            ]);
            // Registration can only ever create a client user.
            $user->role = User::ROLE_CLIENT;
            $user->client_id = $client->id;
            $user->save();

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        AuditLog::record('client.registered', $user->client);

        return redirect()->route('requests.create')->with('status', 'Welcome. Tell us what you need and we will get back to you.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
