<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectMilestone extends Model
{
    public const STATUSES = [
        'pending' => 'Not started',
        'in_progress' => 'In progress',
        'awaiting_approval' => 'Waiting for approval',
        'changes_requested' => 'Changes requested',
        'done' => 'Done',
    ];

    protected $fillable = ['title', 'due_date', 'needs_approval', 'sort_order'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'approved_at' => 'datetime', 'needs_approval' => 'boolean'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
