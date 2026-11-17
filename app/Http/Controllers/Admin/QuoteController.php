<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Counter;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteAcceptance;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Notifications\QuoteSent;
use App\Support\Money;
use App\Support\QuotePdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $quotes = Quote::with('client')
            ->when(isset(Quote::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($c) => $c->where('business_name', 'like', "%{$search}%"))))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.quotes.index', [
            'quotes' => $quotes,
            'status' => $status,
            'search' => $search,
            'clients' => Client::orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }

    /** Start a blank quote for a client. */
    public function store(Request $request)
    {
        $data = $request->validate(['client_id' => ['required', 'uuid', 'exists:clients,id']]);

        $quote = $this->newDraft(Client::findOrFail($data['client_id']), null, 'New quote');

        return redirect()->route('admin.quotes.edit', $quote);
    }

    /** Start a quote from a request, with its services already added as line items. */
    public function storeFromRequest(ServiceRequest $serviceRequest)
    {
        $serviceRequest->load('services', 'package', 'client');

        $quote = DB::transaction(function () use ($serviceRequest) {
            $title = $serviceRequest->package_name ?: ($serviceRequest->services->first()?->category_name ?? ($serviceRequest->isSpecial() ? 'Special request' : 'Services'));
            $quote = $this->newDraft($serviceRequest->client, $serviceRequest, $title);
            $order = 0;

            if ($serviceRequest->package) {
                $p = $serviceRequest->package;
                $quote->items()->create($this->line($p->name, 1, $p->price_cents ?? 0, $p->unit, null, ++$order));
            }
            foreach ($serviceRequest->services as $s) {
                $quote->items()->create($this->line($s->name, 1, $s->price_cents ?? 0, $s->unit, $s->service_id, ++$order));
            }

            $quote->recalculate();
            $quote->save();

            return $quote;
        });

        return redirect()->route('admin.quotes.edit', $quote)
            ->with('status', 'Draft created from the request. "From", hourly and quote-only prices are starting points, so check each line.');
    }

    public function show(Quote $quote)
    {
        $quote->load('client.users', 'items', 'acceptance.user', 'serviceRequest');

        $invoices = Invoice::where('quote_id', $quote->id)->where('status', '!=', 'cancelled')->oldest()->get();

        return view('admin.quotes.show', [
            'quote' => $quote,
            'invoices' => $invoices,
            'invoiced' => (int) $invoices->sum('total_cents'),
            'versions' => Quote::where('number', $quote->number)->orderByDesc('version')->get(['id', 'version', 'status', 'valid_until', 'total_cents', 'created_at']),
        ]);
    }

    public function edit(Quote $quote)
    {
        if (! $quote->isDraft()) {
            return redirect()->route('admin.quotes.show', $quote)->with('status', 'This quote has been sent, so it is locked. Use "Revise" to make a new version.');
        }

        $quote->load('client', 'items');

        return view('admin.quotes.edit', [
            'quote' => $quote,
            'categories' => ServiceCategory::where('is_active', true)->with(['services' => fn ($q) => $q->where('is_active', true)])->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Quote $quote)
    {
        abort_unless($quote->isDraft(), 409, 'A sent quote cannot be edited.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'scope' => ['nullable', 'string', 'max:10000'],
            'timeline' => ['nullable', 'string', 'max:200'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'discount' => ['nullable', 'string', 'max:20'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            'deposit_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'valid_until' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.price' => ['nullable', 'string', 'max:20'],
            'items.*.service_id' => ['nullable', 'integer', 'exists:services,id'],
        ], ['items.required' => 'Add at least one line item.', 'items.min' => 'Add at least one line item.']);

        DB::transaction(function () use ($quote, $data) {
            $quote->fill([
                'title' => $data['title'],
                'scope' => $data['scope'] ?? null,
                'timeline' => $data['timeline'] ?? null,
                'terms' => $data['terms'] ?? null,
                'discount_cents' => Money::toCents($data['discount'] ?? null) ?? 0,
                'vat_rate' => $data['vat_rate'],
                'deposit_percent' => $data['deposit_percent'],
                'valid_until' => $data['valid_until'],
            ]);

            $quote->items()->delete();
            $order = 0;
            foreach ($data['items'] as $item) {
                $quote->items()->create($this->line(
                    $item['description'], (float) $item['quantity'], Money::toCents($item['price'] ?? null) ?? 0,
                    $item['unit'] ?? null, $item['service_id'] ?? null, ++$order,
                ));
            }

            $quote->recalculate();
            $quote->save();
        });

        AuditLog::record('quote.updated', $quote, ['number' => $quote->reference()]);

        if ($request->input('action') === 'send') {
            return $this->send($quote);
        }

        return redirect()->route('admin.quotes.edit', $quote)->with('status', 'Draft saved. The client cannot see it until you send it.');
    }

    public function send(Quote $quote)
    {
        abort_unless($quote->isDraft(), 409);

        if ($quote->items()->count() === 0 || $quote->total_cents <= 0) {
            return redirect()->route('admin.quotes.edit', $quote)->withErrors(['items' => 'Add at least one priced line item before sending.']);
        }

        DB::transaction(function () use ($quote) {
            // Earlier versions of the same quote can no longer be accepted.
            Quote::where('number', $quote->number)->where('id', '!=', $quote->id)
                ->whereIn('status', ['sent', 'changes_requested', 'declined', 'expired'])
                ->update(['status' => 'superseded']);

            $quote->status = 'sent';
            $quote->sent_at = now();
            $quote->save();

            if ($quote->serviceRequest && in_array($quote->serviceRequest->status, ['new', 'reviewing', 'awaiting_client'], true)) {
                $quote->serviceRequest->status = 'quoted';
                $quote->serviceRequest->save();
            }
        });

        AuditLog::record('quote.sent', $quote, ['number' => $quote->reference(), 'total_cents' => $quote->total_cents]);

        try {
            Notification::send($quote->client->users, new QuoteSent($quote));
        } catch (\Throwable $e) {
            report($e);
        }

        $message = $quote->client->users()->exists()
            ? "Quote {$quote->reference()} sent. The client has been notified and it is now locked."
            : "Quote {$quote->reference()} is final and locked. This client has no portal login, so download the PDF and send it to them yourself.";

        return redirect()->route('admin.quotes.show', $quote)->with('status', $message);
    }

    /** Record an answer the client gave outside the portal: by email, WhatsApp, phone or a signed copy. */
    public function recordAnswer(Request $request, Quote $quote)
    {
        $data = $request->validate([
            'answer' => ['required', Rule::in(['accepted', 'declined'])],
            'accepted_name' => ['required_if:answer,accepted', 'nullable', 'string', 'min:2', 'max:120'],
            'method' => ['required', Rule::in(array_keys(QuoteAcceptance::METHODS))],
            'note' => ['nullable', 'string', 'max:500'],
            'confirm' => ['required_if:answer,accepted', 'accepted_if:answer,accepted'],
        ], [
            'accepted_name.required_if' => 'Enter the name of the person who accepted.',
            'confirm.accepted_if' => 'Please confirm you have the client\'s acceptance.',
            'confirm.required_if' => 'Please confirm you have the client\'s acceptance.',
        ]);

        if (! $quote->canBeAnswered()) {
            return back()->withErrors(['answer' => 'Only a sent quote that has not expired can be answered. Revise it to make a new version.']);
        }

        if ($data['answer'] === 'accepted') {
            $accepted = $quote->accept($request->user(), $data['accepted_name'], [
                'recorded_by' => $request->user()->id,
                'method' => $data['method'],
                'note' => $data['note'] ?? null,
                'ip' => $request->ip(),
            ]);
            if (! $accepted) {
                return back()->withErrors(['answer' => 'This quote can no longer be accepted.']);
            }
            AuditLog::record('quote.accepted_on_behalf', $quote, ['number' => $quote->reference(), 'method' => $data['method']]);

            return back()->with('status', 'Acceptance recorded. You can now create the invoice.');
        }

        $quote->status = 'declined';
        $quote->responded_at = now();
        $quote->client_message = trim(QuoteAcceptance::METHODS[$data['method']].': '.($data['note'] ?? ''), ': ');
        $quote->save();
        AuditLog::record('quote.declined_on_behalf', $quote, ['number' => $quote->reference(), 'method' => $data['method']]);

        return back()->with('status', 'Recorded that the client declined.');
    }

    /** Copy a sent quote into a new draft version. The original stays untouched as the record. */
    public function revise(Quote $quote)
    {
        abort_unless($quote->canBeRevised(), 409, 'This quote cannot be revised.');

        $existingDraft = Quote::where('number', $quote->number)->where('status', 'draft')->first();
        if ($existingDraft) {
            return redirect()->route('admin.quotes.edit', $existingDraft)->with('status', 'There is already a draft revision of this quote.');
        }

        $draft = DB::transaction(function () use ($quote) {
            $draft = $quote->replicate(['status', 'sent_at', 'responded_at', 'client_message', 'version', 'valid_until']);
            $draft->number = $quote->number;
            $draft->client_id = $quote->client_id;
            $draft->service_request_id = $quote->service_request_id;
            $draft->created_by = auth()->id();
            $draft->version = Quote::withTrashed()->where('number', $quote->number)->max('version') + 1;
            $draft->status = 'draft';
            $draft->valid_until = now()->addDays((int) Setting::get('quote_valid_days'))->toDateString();
            $draft->save();

            foreach ($quote->items as $item) {
                $draft->items()->create($item->only(['service_id', 'description', 'quantity', 'unit', 'unit_price_cents', 'line_total_cents', 'sort_order']));
            }

            return $draft;
        });

        AuditLog::record('quote.revised', $draft, ['number' => $draft->reference()]);

        return redirect()->route('admin.quotes.edit', $draft)->with('status', "Version {$draft->version} started. The earlier version stays as it was until you send this one.");
    }

    public function destroy(Quote $quote)
    {
        abort_unless($quote->isDraft(), 409, 'Only drafts can be deleted.');

        AuditLog::record('quote.draft_deleted', $quote, ['number' => $quote->reference()]);
        $quote->delete();

        return redirect()->route('admin.quotes.index')->with('status', 'Draft deleted.');
    }

    public function pdf(Quote $quote)
    {
        return QuotePdf::download($quote);
    }

    private function newDraft(Client $client, ?ServiceRequest $serviceRequest, string $title): Quote
    {
        return DB::transaction(function () use ($client, $serviceRequest, $title) {
            $quote = new Quote([
                'title' => $title,
                'terms' => Setting::get('quote_terms'),
                'vat_rate' => (float) Setting::get('vat_rate'),
                'deposit_percent' => (float) Setting::get('deposit_percent'),
                'valid_until' => now()->addDays((int) Setting::get('quote_valid_days'))->toDateString(),
            ]);
            $quote->number = 'Q-'.str_pad((string) Counter::next('quote'), 4, '0', STR_PAD_LEFT);
            $quote->version = 1;
            $quote->client_id = $client->id;
            $quote->service_request_id = $serviceRequest?->id;
            $quote->created_by = auth()->id();
            $quote->status = 'draft';
            $quote->save();

            AuditLog::record('quote.created', $quote, ['number' => $quote->number]);

            return $quote;
        });
    }

    private function line(string $description, float $quantity, int $priceCents, ?string $unit, ?int $serviceId, int $order): array
    {
        return [
            'service_id' => $serviceId,
            'description' => $description,
            'quantity' => $quantity,
            'unit' => $unit ?: null,
            'unit_price_cents' => $priceCents,
            'line_total_cents' => (int) round($quantity * $priceCents),
            'sort_order' => $order,
        ];
    }
}
