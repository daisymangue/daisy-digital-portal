<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Quote extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUSES = [
        'draft' => 'Draft',
        'sent' => 'Sent',
        'changes_requested' => 'Changes requested',
        'accepted' => 'Accepted',
        'declined' => 'Declined',
        'expired' => 'Expired',
        'superseded' => 'Replaced by a newer version',
    ];

    public const CLIENT_LABELS = [
        'sent' => 'Awaiting your answer',
        'changes_requested' => 'Changes requested',
        'accepted' => 'Accepted',
        'declined' => 'Declined',
        'expired' => 'Expired',
        'superseded' => 'Replaced by a newer version',
    ];

    protected $fillable = ['title', 'scope', 'timeline', 'terms', 'discount_cents', 'vat_rate', 'deposit_percent', 'valid_until'];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
            'vat_rate' => 'float',
            'deposit_percent' => 'float',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'vat_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function acceptance(): HasOne
    {
        return $this->hasOne(QuoteAcceptance::class);
    }

    /** Quotes a client is allowed to see: never drafts. */
    public function scopeVisibleToClient(Builder $query): Builder
    {
        return $query->where('status', '!=', 'draft');
    }

    public function reference(): string
    {
        return $this->number.($this->version > 1 ? ' v'.$this->version : '');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /** A sent quote past its valid-until date counts as expired even before the nightly job marks it. */
    /** Statuses a client can still move on from: they may accept after asking for changes or declining. */
    public const OPEN_STATUSES = ['sent', 'changes_requested', 'declined'];

    public function isPastValidity(): bool
    {
        return $this->valid_until && $this->valid_until->endOfDay()->isPast();
    }

    public function effectiveStatus(): string
    {
        return $this->status === 'sent' && $this->isPastValidity() ? 'expired' : $this->status;
    }

    /**
     * Can still be accepted (or answered again): sent, or the client asked for changes or declined
     * and has changed their mind, as long as it is still valid and no newer version replaced it.
     */
    public function canBeAnswered(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true) && ! $this->isPastValidity();
    }

    /** Valid date passed without a yes: the team can extend it, and the client can ask them to. */
    public function canBeExtended(): bool
    {
        return in_array($this->status, ['sent', 'expired', 'changes_requested', 'declined'], true) && $this->isPastValidity();
    }

    public function canBeRevised(): bool
    {
        return in_array($this->effectiveStatus(), ['sent', 'changes_requested', 'declined', 'expired'], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->effectiveStatus()];
    }

    public function clientStatusLabel(): string
    {
        return self::CLIENT_LABELS[$this->effectiveStatus()] ?? $this->statusLabel();
    }

    public function depositCents(): int
    {
        return (int) round($this->total_cents * $this->deposit_percent / 100);
    }

    /** Recalculate every amount from the line items. The server is the only place totals are worked out. */
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
     * Accept the quote and freeze what was agreed. Used by the client in the portal, and by the
     * admin recording an acceptance received another way. Returns null if it can no longer be accepted.
     */
    public function accept(User $by, string $acceptedName, array $extra = []): ?QuoteAcceptance
    {
        return DB::transaction(function () use ($by, $acceptedName, $extra) {
            // Locked re-read, so two clicks or two people cannot both accept.
            $locked = self::whereKey($this->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->canBeAnswered()) {
                return null;
            }

            $locked->status = 'accepted';
            $locked->responded_at = now();
            $locked->save();

            $acceptance = $locked->acceptance()->create([
                'user_id' => $by->id,
                'accepted_name' => $acceptedName,
                'snapshot' => $locked->load('items')->snapshot(),
                'accepted_at' => now(),
            ] + $extra);

            if ($locked->serviceRequest && in_array($locked->serviceRequest->status, ['new', 'reviewing', 'awaiting_client', 'quoted'], true)) {
                $locked->serviceRequest->status = 'approved';
                $locked->serviceRequest->save();
            }

            $this->refresh();

            return $acceptance;
        });
    }

    /** Everything the client agreed to, for the acceptance record. */
    public function snapshot(): array
    {
        return [
            'reference' => $this->reference(),
            'title' => $this->title,
            'scope' => $this->scope,
            'timeline' => $this->timeline,
            'terms' => $this->terms,
            'valid_until' => $this->valid_until?->toDateString(),
            'items' => $this->items->map(fn (QuoteItem $i) => [
                'description' => $i->description,
                'quantity' => (float) $i->quantity,
                'unit' => $i->unit,
                'unit_price_cents' => $i->unit_price_cents,
                'line_total_cents' => $i->line_total_cents,
            ])->all(),
            'subtotal_cents' => $this->subtotal_cents,
            'discount_cents' => $this->discount_cents,
            'vat_rate' => $this->vat_rate,
            'vat_cents' => $this->vat_cents,
            'total_cents' => $this->total_cents,
            'deposit_percent' => $this->deposit_percent,
            'deposit_cents' => $this->depositCents(),
            'total_label' => Money::format($this->total_cents),
        ];
    }
}
