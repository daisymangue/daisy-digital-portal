<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\LoginToken;
use App\Models\User;
use App\Notifications\MagicLoginLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/** One-tap sign-in links sent by email. Each link works once and expires after 20 minutes. */
class MagicLinkController extends Controller
{
    public const MINUTES = 20;

    public function send(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', strtolower($data['email']))->first();

        if ($user) {
            $user->notify(new MagicLoginLink(self::linkFor($user, self::MINUTES), self::MINUTES));
        }

        // Same message either way, so nobody can test which emails are registered.
        return back()->with('status', 'If that email is registered, a sign-in link is on its way. It works for '.self::MINUTES.' minutes.');
    }

    /** A one-time sign-in link. Only a hash of the token is stored. */
    public static function linkFor(User $user, int $minutes): string
    {
        $token = Str::random(64);
        LoginToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addMinutes($minutes),
        ]);

        return route('login.link', $token);
    }

    public function consume(Request $request, string $token)
    {
        $record = LoginToken::where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return redirect()->route('login')->withErrors(['email' => 'That sign-in link has expired or was already used. Please request a new one.']);
        }

        $record->update(['used_at' => now()]);
        Auth::login($record->user);
        $request->session()->regenerate();
        AuditLog::record('login.link', $record->user);

        return redirect($record->user->homeRoute());
    }
}
