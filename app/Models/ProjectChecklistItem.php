<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProjectChecklistItem extends Model
{
    public const STATUSES = ['open' => 'Needed', 'submitted' => 'Sent, being checked', 'accepted' => 'Received'];

    protected $fillable = ['title', 'note', 'sort_order', 'milestone_id'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
