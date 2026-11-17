<?php

use App\Models\AuditLog;
use App\Models\CarePlan;
use App\Models\Invoice;
use App\Models\LoginToken;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\InvoiceReminder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
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

/*
 * Care plans: raise every invoice that has fallen due. Safe to run again, it never bills a period twice.
 *   php artisan portal:bill-care-plans
 */
Artisan::command('portal:bill-care-plans', function () {
    $count = 0;
    CarePlan::where('status', 'active')->whereDate('next_invoice_on', '<=', today())->each(function (CarePlan $plan) use (&$count) {
        $count += count($plan->billDue());
    });
    $this->info("Care plan invoices created: {$count}");
})->purpose('Create invoices for care plans that are due');
Schedule::command('portal:bill-care-plans')->dailyAt('06:05');

/*
 * Overdue reminders: 1, 7 and 14 days after the due date, then stop. Only for clients with a login.
 *   php artisan portal:send-reminders
 */
Artisan::command('portal:send-reminders', function () {
    if (Setting::get('overdue_reminders') !== '1') {
        $this->info('Overdue reminders are switched off in Settings.');

        return;
    }

    $sent = 0;
    $steps = [1, 7, 14];
    Invoice::overdue()->with('client.users')->where('reminders_sent', '<', count($steps))->each(function (Invoice $invoice) use ($steps, &$sent) {
        $daysLate = (int) $invoice->due_date->startOfDay()->diffInDays(today());
        if ($daysLate < $steps[$invoice->reminders_sent] || $invoice->client->users->isEmpty()) {
            return;
        }
        // Never two reminders in one day, even if the schedule ran late.
        if ($invoice->last_reminded_at?->isToday()) {
            return;
        }
        Notification::send($invoice->client->users, new InvoiceReminder($invoice));
        $invoice->reminders_sent++;
        $invoice->last_reminded_at = now();
        $invoice->save();
        AuditLog::record('invoice.reminder', $invoice, ['number' => $invoice->number, 'step' => $invoice->reminders_sent]);
        $sent++;
    });
    $this->info("Reminders sent: {$sent}");
})->purpose('Email reminders for overdue invoices');
Schedule::command('portal:send-reminders')->dailyAt('08:15');

/*
 * Data clean-up. Keeps the database small and holds personal data no longer than needed.
 * Business records (quotes, invoices, payments) are never removed: SARS expects five years.
 *   php artisan portal:prune
 */
Artisan::command('portal:prune', function () {
    $counts = [
        'read notifications older than 6 months' => DB::table('notifications')->whereNotNull('read_at')->where('created_at', '<', now()->subMonths(6))->delete(),
        'any notifications older than 12 months' => DB::table('notifications')->where('created_at', '<', now()->subMonths(12))->delete(),
        'sign-in links' => LoginToken::where('expires_at', '<', now()->subDay())->delete(),
        'activity log entries older than 3 years' => AuditLog::where('created_at', '<', now()->subYears(3))->delete(),
        'expired sessions' => DB::table('sessions')->where('last_activity', '<', now()->subDays(30)->getTimestamp())->delete(),
    ];

    // Leftover temporary files: spreadsheet previews and client exports.
    $removed = 0;
    foreach (['imports', 'exports'] as $folder) {
        foreach (Storage::disk('local')->files($folder) as $path) {
            if (Storage::disk('local')->lastModified($path) < now()->subDay()->getTimestamp()) {
                Storage::disk('local')->delete($path);
                $removed++;
            }
        }
    }
    $counts['temporary files'] = $removed;

    foreach ($counts as $what => $n) {
        $this->line(str_pad((string) $n, 6, ' ', STR_PAD_LEFT)."  {$what}");
    }
})->purpose('Remove old notifications, logs, sessions and temporary files');
Schedule::command('portal:prune')->dailyAt('03:20');

// Proof the scheduler runs, for portal:check.
Schedule::call(fn () => Setting::put(['scheduler_last_run' => now()->toIso8601String()]))->everyFiveMinutes();

/*
 * Launch checklist. Run on the server after deploying:
 *   php artisan portal:check
 */
Artisan::command('portal:check', function () {
    $checks = [];
    // Each check may be a closure, so one broken setting (like the database) does not stop the rest.
    $add = function (string $label, bool|Closure $ok, string $fix = '') use (&$checks) {
        $ok = $ok instanceof Closure ? (bool) rescue($ok, false, false) : $ok;
        $checks[] = [$ok ? 'OK  ' : 'FIX ', $label, $ok ? '' : $fix];
    };

    $add('APP_ENV is production', app()->isProduction(), 'Set APP_ENV=production in .env');
    $add('APP_DEBUG is off', ! config('app.debug'), 'Set APP_DEBUG=false in .env. Debug pages show secrets.');
    $add('APP_KEY is set', (bool) config('app.key'), 'Run php artisan key:generate');
    $add('APP_URL uses https', str_starts_with((string) config('app.url'), 'https://'), 'Set APP_URL=https://portal.daisydigitalagency.com');
    $add('Session cookie is secure', (bool) config('session.secure') || ! app()->isProduction(), 'Set SESSION_SECURE_COOKIE=true');
    $add('Email is set up', ! in_array(config('mail.default'), ['log', 'array'], true), 'Set MAIL_MAILER=smtp and the Hostinger mailbox details');
    $add('Email sender address is set', (bool) config('mail.from.address') && ! str_contains((string) config('mail.from.address'), 'example.com'), 'Set MAIL_FROM_ADDRESS to your portal mailbox');
    $add('Database connects', fn () => (bool) DB::select('select 1'), 'Check the DB_ settings');
    $add('Database is up to date', function () {
        Artisan::call('migrate:status');

        return ! str_contains(Artisan::output(), 'Pending');
    }, 'Run php artisan migrate --force');
    $add('An admin exists', fn () => User::where('role', User::ROLE_ADMIN)->exists(), 'Run php artisan portal:make-admin "Your name" you@example.com');
    $add('Storage folder is writable', is_writable(storage_path('app/private')) || is_writable(storage_path('app')), 'Make storage/ writable by the web user');
    $add('Uploaded files are not public', ! is_dir(public_path('storage/clients')), 'Remove public/storage/clients. Client files must stay private.');
    $add('Frontend is built', is_file(public_path('build/manifest.json')), 'Run npm run build locally and commit public/build');
    $add('Business email in Settings', fn () => Setting::get('business_email') !== '', 'Admin > Settings > Business email');
    $add('Bank details in Settings', fn () => Setting::get('bank_details') !== '', 'Admin > Settings > Bank details, shown on invoices');
    $add('Scheduler ran in the last 15 minutes', function () {
        $last = Setting::get('scheduler_last_run');

        return $last !== '' && Carbon::parse($last)->gt(now()->subMinutes(15));
    },
        'Add the cron job in hPanel: * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1');
    $add('Zip support for client exports', class_exists(ZipArchive::class), 'Enable the zip PHP extension in hPanel');

    $this->table(['', 'Check', 'How to fix'], $checks);
    $fails = collect($checks)->where(0, 'FIX ')->count();
    $fails ? $this->warn("{$fails} thing(s) to fix before clients use the portal.") : $this->info('All good. Ready for clients.');

    return $fails ? 1 : 0;
})->purpose('Check the server is ready for real clients');
