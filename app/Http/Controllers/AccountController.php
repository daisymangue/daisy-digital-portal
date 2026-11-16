<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function edit(Request $request)
    {
        return view('account', ['user' => $request->user()->load('client')]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'business_name' => [Rule::requiredIf($user->isClient()), 'nullable', 'string', 'max:160'],
        ]);

        $user->update(['name' => $data['name'], 'email' => strtolower($data['email']), 'phone' => $data['phone'] ?? null]);

        if ($user->isClient()) {
            $user->client->update(['business_name' => $data['business_name'], 'phone' => $data['phone'] ?? null]);
        }

        AuditLog::record('account.updated', $user);

        return back()->with('status', 'Your details have been saved.');
    }

    public function password(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            // Someone invited by the admin has never had a password, so there is nothing to confirm.
            'current_password' => $user->has_password ? ['required', 'current_password'] : ['nullable'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        $user->password = $data['password'];
        $user->has_password = true;
        $user->save();
        AuditLog::record('account.password_changed', $request->user());

        return back()->with('status', 'Your password has been changed.');
    }
}
