<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\QuoteAnswered;
use App\Support\QuotePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ClientQuoteController extends Controller
{
    public function index(Request $request)
    {
        return view('client.quotes.index', [
            'quotes' => Quote::where('client_id', $request->user()->client_id)->visibleToClient()->latest('sent_at')->paginate(15),
        ]);
    }

    public function show(Request $request, string $id)
    {
        return view('client.quotes.show', ['quote' => $this->find($request, $id), 'settings' => Setting::values()]);
    }

    public function pdf(Request $request, string $id)
    {
        return QuotePdf::download($this->find($request, $id));
    }

    public function accept(Request $request, string $id)
    {
        $quote = $this->find($request, $id);

        $data = $request->validate([
            'accepted_name' => ['required', 'string', 'min:2', 'max:120'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'Please tick the box to confirm you accept this quote.']);

        if (! $quote->canBeAnswered()) {
            return back()->withErrors(['quote' => 'This quote can no longer be accepted. It may have expired or been replaced. Please contact us for an updated quote.']);
        }

        $accepted = $quote->accept($request->user(), $data['accepted_name'], [
            'ip' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
        ]);
        if (! $accepted) {
            return back()->withErrors(['quote' => 'This quote can no longer be accepted. Please contact us.']);
        }

        AuditLog::record('quote.accepted', $quote, ['number' => $quote->reference(), 'total_cents' => $quote->total_cents]);
        $this->tellAdmins($quote->fresh(), 'accepted');

        return redirect()->route('quotes.show', $quote->id)->with('status', 'Thank you. You have accepted this quote. We will send your invoice next.');
    }

    public function decline(Request $request, string $id)
    {
        return $this->respond($request, $id, 'declined', 'You have declined this quote. If something was not right, let us know and we can revise it.');
    }

    public function changes(Request $request, string $id)
    {
        $request->validate(['message' => ['required', 'string', 'min:5', 'max:3000']], ['message.required' => 'Please tell us what you would like changed.']);

        return $this->respond($request, $id, 'changes_requested', 'Thank you. We have your notes and will send a revised quote.');
    }

    private function respond(Request $request, string $id, string $status, string $flash)
    {
        $quote = $this->find($request, $id);
        $data = $request->validate(['message' => ['nullable', 'string', 'max:3000']]);

        if (! $quote->canBeAnswered()) {
            return back()->withErrors(['quote' => 'This quote can no longer be answered. Please contact us.']);
        }

        $quote->status = $status;
        $quote->responded_at = now();
        $quote->client_message = $data['message'] ?? null;
        $quote->save();

        AuditLog::record('quote.'.$status, $quote, ['number' => $quote->reference()]);
        $this->tellAdmins($quote, $status);

        return redirect()->route('quotes.show', $quote->id)->with('status', $flash);
    }

    /** Looked up through the signed-in client and never a draft, so anything else is a 404. */
    private function find(Request $request, string $id): Quote
    {
        return Quote::where('client_id', $request->user()->client_id)->visibleToClient()
            ->with('items', 'client', 'acceptance')->findOrFail($id);
    }

    private function tellAdmins(Quote $quote, string $answer): void
    {
        try {
            Notification::send(User::where('role', User::ROLE_ADMIN)->get(), new QuoteAnswered($quote, $answer));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
