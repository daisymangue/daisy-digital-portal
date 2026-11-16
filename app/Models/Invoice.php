<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUSES = [
        'draft' => 'Draft',
        'issued' => 'Issued',
        'partially_paid' => 'Partially paid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled',
    ];

    public const CLIENT_LABELS = [
        'issued' => 'Awaiting payment',
        'partially_paid' => 'Partially paid',
        'paid' => 'Paid',
        'overdue' => 'Overdue',
        'cancelled' => 'Cancelled',
    ];

    public const TYPES = ['deposit' => 'Deposit', 'balance' => 'Balance', 'full' => 'Full amount', 'custom' => 'Invoice'];

    protected $fillable = ['title', 'notes', 'due_date', 'discount_cents', 'vat_rate'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'vat_rate' => 'float',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
            'paid_cents' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    /** Invoices a client is allowed to see: never drafts. */
    public function scopeVisibleToClient(Builder $query): Builder
    {
        return $query->where('status', '!=', 'draft');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', ['issued', 'partially_paid']);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()->whereDate('due_date', '<', today());
    }

    public function reference(): string
    {
        return $this->number ?? 'Draft';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** Came from a quote, so its amounts are fixed to what the client accepted. */
    public function isFromQuote(): bool
    {
        return $this->quote_id !== null && $this->type !== 'custom';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['issued', 'partially_paid'], true);
    }

    public function effectiveStatus(): string
    {
        return $this->isOpen() && $this->due_date && $this->due_date->endOfDay()->isPast() ? 'overdue' : $this->status;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->effectiveStatus()];
    }

    public function clientStatusLabel(): string
    {
        return self::CLIENT_LABELS[$this->effectiveStatus()] ?? $this->statusLabel();
    }

    public function balanceCents(): int
    {
        return max(0, $this->total_cents - $this->paid_cents);
    }

    /** Client-submitted payments still waiting for the admin to check the bank. */
    public function pendingCents(): int
    {
        return (int) $this->payments()->where('status', 'pending')->sum('amount_cents');
    }

    /** For invoices built line by line. Invoices made from a quote keep the quote's exact amounts instead. */
    public function recalculate(): void
    {
        $subtotal = (int) $this->items()->sum('line_total_cents');
        $discount = min((int) $this->discount_cents, $subtotal);
        $vat = (int) round(($subtotal - $discount) * $this->vat_rate / 100);

        $this->subtotal_cents = $subtotal;
        $this->discount_cents = $discount;
        $this->vat_cents = $vat;
        $this->total_cents = $subtotal - $discount + $vat;
    }

    /**
     * Only confirmed payments count. Clicking "I have paid" never marks an invoice paid:
     * an admin has to confirm the money arrived. Call inside a transaction.
     */
    public function syncPaymentStatus(): void
    {
        $paid = (int) $this->payments()->where('status', 'confirmed')->sum('amount_cents');
        $this->paid_cents = $paid;

        if (in_array($this->status, ['draft', 'cancelled'], true)) {
            $this->save();

            return;
        }

        if ($paid >= $this->total_cents && $this->total_cents > 0) {
            $this->status = 'paid';
            $this->paid_at ??= now();
        } elseif ($paid > 0) {
            $this->status = 'partially_paid';
            $this->paid_at = null;
        } else {
            $this->status = 'issued';
            $this->paid_at = null;
        }
        $this->save();
    }
}
