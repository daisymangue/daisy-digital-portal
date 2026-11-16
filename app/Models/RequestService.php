<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class RequestService extends Model
{
    protected $fillable = ['service_id', 'category_name', 'name', 'pricing_type', 'price_cents', 'is_from', 'unit'];

    protected function casts(): array
    {
        return ['is_from' => 'boolean', 'price_cents' => 'integer'];
    }

    public function priceLabel(): string
    {
        return Money::label($this->price_cents, $this->is_from, $this->unit);
    }
}
