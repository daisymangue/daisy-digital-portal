<?php

use App\Models\AuditLog;
use App\Models\LoginToken;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/*
 * Create or promote an admin. Admins can only be made here, on the server,
 * never through the public registration form.
 *   php artisan portal:make-admin "Thabang" you@example.com
 */
Artisan::command('portal:make-admin {name} {email}', function (string $name, string $email) {
    $password = $this->secret('Password (at least 10 characters)');

    $check = Validator::make(
        ['email' => $email, 'password' => $password],
        ['email' => ['required', 'email'], 'password' => ['required', Password::min(10)]],
    );
    if ($check->fails()) {
        $this->error(implode(' ', $check->errors()->all()));

        return 1;
    }

    $user = User::firstOrNew(['email' => strtolower($email)]);
    $user->name = $name;
    $user->password = $password;
    $user->role = User::ROLE_ADMIN;
    $user->client_id = null;
    $user->email_verified_at = now();
    $user->save();

    AuditLog::record('admin.created', $user, [], $user->id);
    $this->info("Admin ready: {$user->email}");

    return 0;
})->purpose('Create an admin user or promote an existing user');

// Remove used and expired sign-in links.
Schedule::call(fn () => LoginToken::where('expires_at', '<', now()->subDay())->delete())->daily();

// Hostinger shared hosting has no permanent worker, so the scheduler drains the queue every minute.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();

// Sent quotes past their valid-until date become expired.
Schedule::call(fn () => Quote::where('status', 'sent')->whereDate('valid_until', '<', today())->update(['status' => 'expired']))->dailyAt('00:10');
