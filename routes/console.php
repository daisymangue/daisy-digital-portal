<?php

use App\Models\AuditLog;
use App\Models\CarePlan;
use App\Models\Invoice;
use App\Models\LoginToken;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\InvoiceReminder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
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
