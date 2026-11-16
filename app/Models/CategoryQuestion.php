<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryQuestion extends Model
{
    public const TYPES = ['text' => 'Short answer', 'textarea' => 'Long answer', 'select' => 'Choose one', 'yesno' => 'Yes or no'];

    protected $fillable = ['service_category_id', 'label', 'type', 'options', 'is_required', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['options' => 'array', 'is_required' => 'boolean', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }
}
