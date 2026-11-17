<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = ['business_name', 'phone', 'contact_name', 'email', 'address', 'notes'];

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function hasPortalAccess(): bool
    {
        return $this->users()->exists();
    }

    /** Who documents are addressed to: the first portal user, or the contact the admin entered. */
    public function contactName(): ?string
    {
        return $this->users->first()?->name ?? $this->contact_name;
    }

    public function contactEmail(): ?string
    {
        return $this->users->first()?->email ?? $this->email;
    }

    public function carePlans(): HasMany
    {
        return $this->hasMany(CarePlan::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }
}
