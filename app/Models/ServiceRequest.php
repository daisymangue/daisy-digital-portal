<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRequest extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /** Status => label shown to admins. */
    public const STATUSES = [
        'new' => 'New',
        'reviewing' => 'Reviewing',
        'awaiting_client' => 'Awaiting client information',
        'quoted' => 'Quoted',
        'approved' => 'Approved',
        'converted' => 'Converted to project',
        'declined' => 'Declined',
    ];

    /** How a request reached us. "portal" means the client submitted it themselves. */
    public const SOURCES = [
        'portal' => 'Client portal',
        'phone' => 'Phone call',
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'in_person' => 'In person',
        'other' => 'Other',
    ];

    /** Plain wording shown to clients. */
    public const CLIENT_LABELS = [
        'new' => 'Received',
        'reviewing' => 'Being reviewed',
        'awaiting_client' => 'We need more information',
        'quoted' => 'Quote sent',
        'approved' => 'Approved',
        'converted' => 'Project started',
        'declined' => 'Closed',
    ];

    protected $fillable = ['description', 'package_id', 'package_name'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(RequestService::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(RequestAnswer::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(RequestNote::class)->latest();
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
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
