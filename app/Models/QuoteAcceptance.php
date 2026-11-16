<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuoteAcceptance extends Model
{
    public const METHODS = [
        'email' => 'Email',
        'whatsapp' => 'WhatsApp',
        'phone' => 'Phone call',
        'in_person' => 'In person',
        'signed_copy' => 'Signed copy',
        'other' => 'Other',
    ];

    protected $fillable = ['user_id', 'accepted_name', 'ip', 'user_agent', 'snapshot', 'accepted_at', 'recorded_by', 'method', 'note'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'accepted_at' => 'datetime'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isRecordedByTeam(): bool
    {
        return $this->recorded_by !== null;
    }

    public function methodLabel(): ?string
    {
        return $this->method ? (self::METHODS[$this->method] ?? $this->method) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
