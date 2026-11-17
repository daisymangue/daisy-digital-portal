<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/** The full activity log: who did what and when. Kept off the dashboard so that stays clean. */
class ActivityController extends Controller
{
    /** Groups for the filter, by the start of the action name. */
    public const GROUPS = [
        'sign-ins' => ['Sign-ins', ['login.', 'admin.created', 'account.']],
        'requests' => ['Requests', ['request.']],
        'quotes' => ['Quotes', ['quote.']],
        'money' => ['Invoices and payments', ['invoice.', 'payment.', 'care_plan.']],
        'projects' => ['Projects', ['project.', 'milestone.', 'checklist.', 'change.', 'testimonial.']],
        'clients' => ['Clients', ['client.']],
        'files' => ['File downloads', ['file.']],
        'setup' => ['Services and settings', ['catalogue.', 'settings.']],
    ];

    public function index(Request $request)
    {
        $group = $request->query('group');
        $who = $request->query('who');
        $search = trim((string) $request->query('q'));

        $logs = AuditLog::with('user')
            ->when(isset(self::GROUPS[$group]), fn ($q) => $q->where(function ($w) use ($group) {
                foreach (self::GROUPS[$group][1] as $prefix) {
                    $w->orWhere('action', 'like', $prefix.'%');
                }
            }))
            // Routine noise is hidden unless asked for by its group.
            ->when(! $group, fn ($q) => $q->whereNotIn('action', ['file.downloaded']))
            ->when($who === 'system', fn ($q) => $q->whereNull('user_id'))
            ->when($who && $who !== 'system', fn ($q) => $q->where('user_id', (int) $who))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('action', 'like', "%{$search}%")->orWhere('meta', 'like', "%{$search}%")))
            ->latest('id')->paginate(50)->withQueryString();

        return view('admin.activity.index', [
            'logs' => $logs,
            'group' => $group,
            'who' => $who,
            'search' => $search,
            'people' => User::whereIn('id', AuditLog::whereNotNull('user_id')->distinct()->pluck('user_id'))->orderBy('name')->get(['id', 'name', 'role']),
        ]);
    }
}
