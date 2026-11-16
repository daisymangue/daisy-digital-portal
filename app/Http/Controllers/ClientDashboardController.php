<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Quote;
use Illuminate\Http\Request;

class ClientDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $client = $request->user()->client;
        $requests = $client->serviceRequests();

        return view('client.dashboard', [
            'client' => $client,
            'openCount' => (clone $requests)->whereNotIn('status', ['converted', 'declined'])->count(),
            'waitingCount' => (clone $requests)->where('status', 'awaiting_client')->count(),
            'totalCount' => (clone $requests)->count(),
            'invoicesDue' => Invoice::where('client_id', $client->id)->open()->orderBy('due_date')->get(),
            'quotesWaiting' => Quote::where('client_id', $client->id)->where('status', 'sent')->whereDate('valid_until', '>=', today())->latest('sent_at')->get(),
            'recent' => (clone $requests)->withCount('services')->latest('submitted_at')->limit(5)->get(),
            'notifications' => $request->user()->notifications()->limit(5)->get(),
        ]);
    }
}
