<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A plan on the set menu. Copied into a client's CarePlan when picked, so later edits here never change existing plans. */
class CarePlanTemplate extends Model
{
    protected $fillable = ['name', 'description', 'amount_cents', 'interval', 'sort_order'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'sort_order' => 'integer'];
    }

    public function intervalLabel(): string
    {
        return CarePlan::INTERVALS[$this->interval] ?? $this->interval;
    }
}
