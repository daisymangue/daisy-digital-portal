<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CarePlan;
use App\Models\CarePlanTemplate;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The plan menu: ready-made subscriptions to pick from when adding one for a client. */
class CarePlanTemplateController extends Controller
{
    public function index()
    {
        return view('admin.care-plans.templates', [
            'templates' => CarePlanTemplate::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    private function data(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'string', 'max:20'],
            'interval' => ['required', Rule::in(array_keys(CarePlan::INTERVALS))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $cents = Money::toCents($data['amount']);
        if (! $cents || $cents <= 0) {
            throw ValidationException::withMessages(['amount' => 'Enter the price per period, excluding VAT.']);
        }
        unset($data['amount']);

        return $data + ['amount_cents' => $cents, 'sort_order' => (int) ($data['sort_order'] ?? 0)];
    }

    public function store(Request $request)
    {
        $template = CarePlanTemplate::create($this->data($request));
        AuditLog::record('plan_template.created', $template, ['name' => $template->name]);

        return back()->with('status', 'Added to the plan menu.');
    }

    public function update(Request $request, CarePlanTemplate $planTemplate)
    {
        $planTemplate->update($this->data($request));

        return back()->with('status', 'Plan saved. Existing subscriptions keep their own price.');
    }

    public function destroy(CarePlanTemplate $planTemplate)
    {
        $planTemplate->delete();

        return back()->with('status', 'Removed from the plan menu. Existing subscriptions are not affected.');
    }
}
