<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Service extends Model
{
    public const PRICING_TYPES = ['fixed' => 'Fixed', 'from' => 'From', 'hourly' => 'Hourly', 'monthly' => 'Monthly', 'quote' => 'Quote'];

    protected $fillable = ['service_category_id', 'name', 'pricing_type', 'price_cents', 'is_from', 'unit', 'sort_order', 'is_active', 'is_popular'];

    protected function casts(): array
    {
        return ['is_from' => 'boolean', 'is_active' => 'boolean', 'is_popular' => 'boolean', 'price_cents' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function priceLabel(): string
    {
        return Money::label($this->price_cents, $this->is_from, $this->unit);
    }
}
