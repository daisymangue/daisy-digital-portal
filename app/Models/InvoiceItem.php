<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = ['description', 'quantity', 'unit', 'unit_price_cents', 'line_total_cents', 'sort_order'];

    protected function casts(): array
    {
        return ['quantity' => 'float', 'unit_price_cents' => 'integer', 'line_total_cents' => 'integer'];
    }

    public function quantityLabel(): string
    {
        $qty = rtrim(rtrim(number_format($this->quantity, 2, '.', ''), '0'), '.');

        return $this->unit ? "{$qty} × {$this->unit}" : $qty;
    }
}
