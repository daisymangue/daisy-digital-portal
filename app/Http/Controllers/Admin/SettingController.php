<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['settings' => Setting::values()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
            'business_address' => ['nullable', 'string', 'max:500'],
            'business_email' => ['nullable', 'email', 'max:190'],
            'business_phone' => ['nullable', 'string', 'max:40'],
            'business_website' => ['nullable', 'string', 'max:190'],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'vat_number' => ['nullable', 'string', 'max:30'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            'quote_valid_days' => ['required', 'integer', 'min:1', 'max:365'],
            'deposit_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'invoice_due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'bank_details' => ['nullable', 'string', 'max:1000'],
            'quote_terms' => ['nullable', 'string', 'max:5000'],
            'google_review_url' => ['nullable', 'url:https', 'max:300'],
            // No SVG: an SVG can carry script.
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ], ['logo.mimes' => 'Upload the logo as a PNG, JPG or WebP image.', 'logo.max' => 'The logo can be up to 1 MB.']);
        unset($data['logo']);

        $old = Setting::get('logo_path');
        if ($request->file('logo')) {
            $data['logo_path'] = $request->file('logo')->store('branding', 'local');
        } elseif ($request->boolean('remove_logo')) {
            $data['logo_path'] = '';
        }
        if (isset($data['logo_path']) && $old !== '' && $old !== $data['logo_path']) {
            Storage::disk('local')->delete($old);
        }
        $data['overdue_reminders'] = $request->boolean('overdue_reminders') ? '1' : '0';
        $data['ask_for_testimonial'] = $request->boolean('ask_for_testimonial') ? '1' : '0';

        Setting::put($data);
        AuditLog::record('settings.updated');

        return back()->with('status', 'Settings saved. New quotes and invoices will use them. Documents already sent are not changed.');
    }
}
