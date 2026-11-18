<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Package extends Model
{
    protected $fillable = ['name', 'price_cents', 'sale_price_cents', 'sale_ends_on', 'is_from', 'unit', 'includes', 'sort_order', 'is_active', 'is_featured'];

    protected function casts(): array
    {
        return ['includes' => 'array', 'is_from' => 'boolean', 'is_active' => 'boolean', 'is_featured' => 'boolean', 'price_cents' => 'integer', 'sale_price_cents' => 'integer', 'sale_ends_on' => 'date'];
    }

    /** On sale: a sale price lower than the normal one, and the end date (if any) not passed yet. */
    public function onSale(): bool
    {
        return $this->sale_price_cents !== null && $this->price_cents !== null
            && $this->sale_price_cents < $this->price_cents
            && ($this->sale_ends_on === null || ! $this->sale_ends_on->endOfDay()->isPast());
    }

    /** What a client pays right now. */
    public function currentPriceCents(): ?int
    {
        return $this->onSale() ? $this->sale_price_cents : $this->price_cents;
    }

    public function savingCents(): int
    {
        return $this->onSale() ? $this->price_cents - $this->sale_price_cents : 0;
    }

    public function priceLabel(): string
    {
        return Money::label($this->currentPriceCents(), $this->is_from, $this->unit);
    }

    public function regularPriceLabel(): string
    {
        return Money::label($this->price_cents, false, $this->unit);
    }

    public function stepSet(): BelongsTo
    {
        return $this->belongsTo(StepSet::class);
    }
}
