<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Testimonial extends Model
{
    protected $fillable = ['rating', 'body', 'display_name', 'can_publish'];

    protected function casts(): array
    {
        return ['can_publish' => 'boolean', 'requested_at' => 'datetime', 'received_at' => 'datetime', 'rating' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
