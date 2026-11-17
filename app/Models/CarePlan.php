<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class CarePlan extends Model
{
    public const INTERVALS = ['monthly' => 'Monthly', 'quarterly' => 'Every 3 months', 'yearly' => 'Yearly'];

    public const STATUSES = ['active' => 'Active', 'paused' => 'Paused', 'cancelled' => 'Cancelled'];

    protected $fillable = ['name', 'description', 'amount_cents', 'interval', 'next_invoice_on', 'auto_issue', 'ends_on'];

    protected function casts(): array
    {
        return ['next_invoice_on' => 'date', 'ends_on' => 'date', 'auto_issue' => 'boolean', 'amount_cents' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->latest('period_start');
    }

    public function intervalLabel(): string
    {
        return self::INTERVALS[$this->interval] ?? $this->interval;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function after(CarbonInterface $date): CarbonInterface
    {
        return match ($this->interval) {
            'quarterly' => $date->copy()->addMonthsNoOverflow(3),
            'yearly' => $date->copy()->addYearNoOverflow(),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }

    /** "November 2026", "Nov 2026 to Jan 2027" or "2026 to 2027". */
    public function periodLabel(CarbonInterface $start): string
    {
        $end = $this->after($start)->subDay();

        return match ($this->interval) {
            'monthly' => $start->format('F Y'),
            default => $start->format('j M Y').' to '.$end->format('j M Y'),
        };
    }

    public function vatInclusiveCents(): int
    {
        return $this->amount_cents + (int) round($this->amount_cents * (float) Setting::get('vat_rate') / 100);
    }

    /**
     * Raises every invoice that has fallen due, oldest first. Safe to run more than once a day:
     * one invoice per plan and period, enforced by a unique index as well.
     * Returns the invoices created.
     */
    public function billDue(?CarbonInterface $today = null): array
    {
        $today ??= today();
        $created = [];

        for ($i = 0; $i < 12 && $this->status === 'active' && $this->next_invoice_on->lte($today); $i++) {
            $start = $this->next_invoice_on->copy();

            if ($this->ends_on && $start->gt($this->ends_on)) {
                $this->status = 'cancelled';
                $this->save();
                break;
            }

            $invoice = DB::transaction(function () use ($start) {
                if (Invoice::where('care_plan_id', $this->id)->whereDate('period_start', $start)->exists()) {
                    return null;
                }

                $invoice = new Invoice(['title' => $this->name.', '.$this->periodLabel($start), 'notes' => $this->description, 'vat_rate' => (float) Setting::get('vat_rate')]);
                $invoice->client_id = $this->client_id;
                $invoice->care_plan_id = $this->id;
                $invoice->period_start = $start;
                $invoice->type = 'recurring';
                $invoice->status = 'draft';
                $invoice->created_by = User::where('role', User::ROLE_ADMIN)->orderBy('id')->value('id');
                $invoice->due_date = today()->addDays((int) Setting::get('invoice_due_days'));
                $invoice->save();
                $invoice->items()->create([
                    'description' => $this->name.' ('.$this->periodLabel($start).')',
                    'quantity' => 1,
                    'unit_price_cents' => $this->amount_cents,
                    'line_total_cents' => $this->amount_cents,
                    'sort_order' => 1,
                ]);
                $invoice->recalculate();
                $invoice->save();

                return $invoice;
            });

            $this->next_invoice_on = $this->after($start);
            $this->save();

            if ($invoice) {
                AuditLog::record('care_plan.billed', $this, ['period' => $start->toDateString()], $invoice->created_by);
                if ($this->auto_issue) {
                    $invoice->issueNow();
                }
                $created[] = $invoice;
            }
        }

        return $created;
    }
}
