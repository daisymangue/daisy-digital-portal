<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = ['name', 'price_cents', 'is_from', 'unit', 'includes', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['includes' => 'array', 'is_from' => 'boolean', 'is_active' => 'boolean', 'price_cents' => 'integer'];
    }

    public function priceLabel(): string
    {
        return Money::label($this->price_cents, $this->is_from, $this->unit);
    }
}
