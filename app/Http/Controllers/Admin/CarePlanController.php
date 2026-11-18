<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CarePlan;
use App\Models\CarePlanTemplate;
use App\Models\Client;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CarePlanController extends Controller
{
    public function index(Request $request)
    {
        // Old "edit" links now open the plan's own page.
        if ($request->query('edit')) {
            return redirect()->route('admin.care-plans.show', (int) $request->query('edit'));
        }

        $plans = CarePlan::with('client')->withBillCounts()
            ->orderByRaw("case status when 'active' then 0 when 'paused' then 1 else 2 end")
            ->orderBy('next_invoice_on')->get();

        return view('admin.care-plans.index', [
            'plans' => $plans,
            'templates' => CarePlanTemplate::orderBy('sort_order')->orderBy('name')->get(),
            'clients' => Client::whereNull('archived_at')->orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }

    public function show(CarePlan $carePlan)
    {
        $carePlan->load('client');

        return view('admin.care-plans.show', [
            'plan' => $carePlan,
            'bills' => $carePlan->invoices()->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'string', 'max:20'],
            'interval' => ['required', Rule::in(array_keys(CarePlan::INTERVALS))],
            'next_invoice_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after:next_invoice_on'],
        ], [
            'next_invoice_on.required' => 'Choose the date of the first invoice.',
            'ends_on.after' => 'The end date must be after the next invoice date.',
        ]);

        $cents = Money::toCents($data['amount']);
        if (! $cents || $cents <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter the price per period, excluding VAT.']);
        }
        unset($data['amount']);

        return $data + ['amount_cents' => $cents, 'auto_issue' => $request->boolean('auto_issue')];
    }

    public function store(Request $request)
    {
        $request->validate(['client_id' => ['required', 'uuid', 'exists:clients,id']]);
        $plan = new CarePlan($this->validated($request));
        $plan->client_id = $request->input('client_id');
        $plan->status = 'active';
        $plan->save();

        AuditLog::record('care_plan.created', $plan, ['name' => $plan->name]);

        $note = $plan->next_invoice_on->lte(today()) ? ' The first invoice will go out within the hour, or use "Bill now".' : ' The first invoice goes out on '.$plan->next_invoice_on->format('j M Y').'.';

        return redirect()->route('admin.care-plans.show', $plan)->with('status', "Subscription added.{$note}");
    }

    public function update(Request $request, CarePlan $carePlan)
    {
        $carePlan->fill($this->validated($request));
        $carePlan->status = $request->validate(['status' => ['required', Rule::in(array_keys(CarePlan::STATUSES))]])['status'];
        $carePlan->save();

        AuditLog::record('care_plan.updated', $carePlan, ['status' => $carePlan->status]);

        return redirect()->route('admin.care-plans.show', $carePlan)->with('status', 'Subscription saved.');
    }

    public function bill(CarePlan $carePlan)
    {
        abort_unless($carePlan->status === 'active', 409, 'Only active plans can be billed.');

        $invoices = $carePlan->billDue();

        return back()->with('status', match (count($invoices)) {
            0 => 'Nothing due yet. The next invoice is on '.$carePlan->next_invoice_on->format('j M Y').'.',
            1 => 'Invoice '.($invoices[0]->number ?? 'draft').' created.',
            default => count($invoices).' invoices created to catch up.',
        });
    }
}
