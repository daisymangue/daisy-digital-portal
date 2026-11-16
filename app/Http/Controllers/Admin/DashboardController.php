<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
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

        return view('admin.dashboard', [
            'stats' => [
                'New requests' => ServiceRequest::where('status', 'new')->count(),
                'Being reviewed' => ServiceRequest::where('status', 'reviewing')->count(),
                'Awaiting client information' => ServiceRequest::where('status', 'awaiting_client')->count(),
                'Total clients' => Client::count(),
                'Quotes awaiting answer' => Quote::where('status', 'sent')->whereDate('valid_until', '>=', today())->count(),
                'Outstanding invoices' => Money::format((int) Invoice::open()->sum(DB::raw('total_cents - paid_cents'))),
                'Overdue invoices' => Invoice::overdue()->count(),
                'Received this month' => Money::format((int) Payment::where('status', 'confirmed')->whereBetween('paid_on', [now()->startOfMonth(), now()->endOfMonth()])->sum('amount_cents')),
            ],
            'requests' => ServiceRequest::with('client')->withCount('services')
                ->whereIn('status', ['new', 'reviewing', 'awaiting_client'])
                ->latest('submitted_at')->limit(8)->get(),
            'pendingPayments' => Payment::with('invoice.client')->where('status', 'pending')->oldest()->get(),
            'activity' => AuditLog::with('user')->whereNotIn('action', ['file.downloaded', 'login.failed'])->latest('id')->limit(8)->get(),
        ]);
    }
}
