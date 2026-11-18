<?php

namespace App\Support;

use App\Models\CarePlan;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/** The four numbers at the top of the admin Billing page. */
class BillingSummary
{
    public static function admin(): array
    {
        $active = CarePlan::where('status', 'active')->get();

        // Every bill the active plans will raise in the next 30 days, excluding VAT.
        $until = today()->addDays(30);
        $upcoming = 0;
        foreach ($active as $plan) {
            for ($d = $plan->next_invoice_on->copy(), $i = 0; $d->lte($until) && $i < 12; $d = $plan->after($d), $i++) {
                if ($plan->ends_on && $d->gt($plan->ends_on)) {
                    break;
                }
                $upcoming += $plan->amount_cents;
            }
        }

        return [
            'monthly' => $active->sum(fn (CarePlan $p) => $p->monthlyCents()),
            'upcoming' => $upcoming,
            'overdue' => (int) Invoice::overdue()->sum(DB::raw('total_cents - paid_cents')),
            'overdueCount' => Invoice::overdue()->count(),
            'paidThisMonth' => (int) Payment::where('status', 'confirmed')
                ->whereBetween('paid_on', [today()->startOfMonth(), today()->endOfMonth()])->sum('amount_cents'),
        ];
    }
}
