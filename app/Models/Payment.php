<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Payment extends Model
{
    use HasUuids;

    public const METHODS = ['eft' => 'EFT / bank transfer', 'card' => 'Card', 'cash' => 'Cash', 'other' => 'Other'];

    public const STATUSES = ['pending' => 'Waiting to be checked', 'confirmed' => 'Confirmed', 'rejected' => 'Not confirmed'];

    protected $fillable = ['amount_cents', 'method', 'reference', 'paid_on', 'note'];

    protected function casts(): array
    {
        return ['paid_on' => 'date', 'reviewed_at' => 'datetime', 'amount_cents' => 'integer'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Proof of payment uploads. */
    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->method] ?? $this->method;
    }
}
