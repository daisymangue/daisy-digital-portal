<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChangeRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectChecklistItem;
use App\Models\ProjectMessage;
use App\Models\ProjectMilestone;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        // Things waiting on the team, most urgent first. Only those with something in them are shown.
        $attention = collect([
            ['Payments to check', Payment::where('status', 'pending')->count(), route('admin.invoices.index'), 'Proof of payment uploaded. Check the bank, then confirm.'],
            ['New requests', ServiceRequest::where('status', 'new')->count(), route('admin.requests.index', ['status' => 'new']), 'Waiting for a first look.'],
            ['Unread client messages', ProjectMessage::where('from_team', false)->whereNull('read_at')->distinct('project_id')->count('project_id'), route('admin.projects.index'), 'Projects with messages you have not opened.'],
            ['Changes asked for', ProjectMilestone::where('status', 'changes_requested')->count() + ChangeRequest::where('status', 'new')->count(), route('admin.projects.index'), 'Feedback on a step, or extra work to quote.'],
            ['Sent by clients to check', ProjectChecklistItem::where('status', 'submitted')->count(), route('admin.projects.index'), 'Content or files from "What we need".'],
            ['Care plan drafts to issue', Invoice::where('type', 'recurring')->where('status', 'draft')->count(), route('admin.invoices.index', ['status' => 'draft']), 'Check and issue them.'],
            ['Overdue invoices', Invoice::overdue()->count(), route('admin.invoices.index', ['status' => 'overdue']), 'Reminders go out automatically. You may want to call.'],
        ])->filter(fn ($row) => $row[1] > 0)->values();

        return view('admin.dashboard', [
            'attention' => $attention,
            'stats' => [
                'New requests' => ServiceRequest::where('status', 'new')->count(),
                'Being reviewed' => ServiceRequest::where('status', 'reviewing')->count(),
                'Awaiting client information' => ServiceRequest::where('status', 'awaiting_client')->count(),
                'Active projects' => Project::whereNotIn('status', ['completed', 'cancelled'])->count(),
                'Quotes awaiting answer' => Quote::where('status', 'sent')->whereDate('valid_until', '>=', today())->count(),
                'Outstanding invoices' => Money::format((int) Invoice::open()->sum(DB::raw('total_cents - paid_cents'))),
                'Overdue invoices' => Invoice::overdue()->count(),
                'Received this month' => Money::format((int) Payment::where('status', 'confirmed')->whereBetween('paid_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount_cents')),
            ],
            'requests' => ServiceRequest::with('client')->withCount('services')
                ->whereIn('status', ['new', 'reviewing', 'awaiting_client'])
                ->latest('submitted_at')->limit(8)->get(),
            'pendingPayments' => Payment::with('invoice.client')->where('status', 'pending')->oldest()->get(),
        ]);
    }
}
