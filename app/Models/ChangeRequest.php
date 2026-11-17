<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ChangeRequest extends Model
{
    public const STATUSES = [
        'new' => 'New',
        'quoted' => 'Quoted',
        'included' => 'Included, no charge',
        'declined' => 'Declined',
        'done' => 'Done',
    ];

    public const CLIENT_LABELS = [
        'new' => 'We are looking at it',
        'quoted' => 'Quote sent',
        'included' => 'Included, no extra cost',
        'declined' => 'Not possible',
        'done' => 'Done',
    ];

    protected $fillable = ['title', 'details'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function clientStatusLabel(): string
    {
        return self::CLIENT_LABELS[$this->status] ?? $this->status;
    }
}
