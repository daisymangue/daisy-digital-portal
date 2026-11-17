<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'meta', 'ip'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $subject = null, array $meta = [], ?int $userId = null): self
    {
        return self::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    /** "Quote sent", "Payment confirmed": the action in plain words. */
    public function label(): string
    {
        $words = [
            'login.password' => 'Signed in with password',
            'login.link' => 'Signed in with email link',
            'login.failed' => 'Sign-in failed',
            'file.downloaded' => 'File downloaded',
            'project.message' => 'Message sent',
            'invoice.reminder' => 'Payment reminder sent',
            'client.created_by_admin' => 'Client added by the team',
            'request.captured' => 'Request logged by the team',
            'quote.accepted_on_behalf' => 'Quote acceptance recorded',
            'quote.declined_on_behalf' => 'Quote decline recorded',
        ];
        if (isset($words[$this->action])) {
            return $words[$this->action];
        }

        return Str::of($this->action)->replace(['.', '_'], ' ')->replace('  ', ' ')->trim()->ucfirst()->toString();
    }

    /** The number or name the entry is about, if it recorded one. */
    public function reference(): ?string
    {
        $m = $this->meta ?? [];

        return $m['number'] ?? $m['invoice'] ?? $m['quote'] ?? $m['title'] ?? $m['milestone'] ?? $m['item'] ?? $m['name'] ?? null;
    }

    /** Where to go to see it, for the record types that have a page. */
    public function url(): ?string
    {
        if (! $this->subject_id) {
            return null;
        }

        return match ($this->subject_type) {
            'ServiceRequest' => route('admin.requests.show', $this->subject_id),
            'Quote' => route('admin.quotes.show', $this->subject_id),
            'Invoice' => route('admin.invoices.show', $this->subject_id),
            'Project' => route('admin.projects.show', $this->subject_id),
            'Client' => route('admin.clients.show', $this->subject_id),
            default => null,
        };
    }
}
