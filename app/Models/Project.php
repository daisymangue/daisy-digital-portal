<?php

namespace App\Models;

use App\Notifications\ProjectNotice;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class Project extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUSES = [
        'active' => 'In progress',
        'awaiting_client' => 'Waiting on client',
        'on_hold' => 'On hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    public const CLIENT_LABELS = [
        'active' => 'In progress',
        'awaiting_client' => 'Waiting on you',
        'on_hold' => 'On hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

    /** A starting set the admin can add in one click, then rename or remove. */
    public const STANDARD_MILESTONES = [
        ['Kick-off and gathering content', false],
        ['First design or draft', true],
        ['Build', false],
        ['Final review', true],
        ['Launch and handover', false],
    ];

    protected $fillable = ['title', 'description', 'status', 'start_date', 'due_date'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'progress' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('sort_order')->orderBy('id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ProjectUpdate::class)->latest('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ProjectMessage::class)->orderBy('id');
    }

    public function checklist(): HasMany
    {
        return $this->hasMany(ProjectChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable')->latest();
    }

    /** Every file shared in this project: uploaded directly, in chat or against a checklist item. */
    public function allFiles()
    {
        return File::where(function ($q) {
            $q->where(fn ($q) => $q->where('fileable_type', self::class)->where('fileable_id', $this->id))
                ->orWhere(fn ($q) => $q->where('fileable_type', ProjectMessage::class)->whereIn('fileable_id', $this->messages()->select('id')))
                ->orWhere(fn ($q) => $q->where('fileable_type', ProjectChecklistItem::class)->whereIn('fileable_id', $this->checklist()->select('id')));
        })->with('uploader')->latest()->get();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function clientStatusLabel(): string
    {
        return self::CLIENT_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function isClosed(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    /** Progress follows the milestones once there are any. */
    public function recalculateProgress(): void
    {
        $total = $this->milestones()->count();
        if ($total > 0) {
            $this->progress = (int) round($this->milestones()->where('status', 'done')->count() / $total * 100);
            $this->save();
        }
    }

    public function unreadFor(User $user): int
    {
        return $this->messages()->where('from_team', $user->isClient())->whereNull('read_at')->count();
    }

    /**
     * Opens a project for a quote. Used when the deposit or full invoice is paid, and when the
     * admin starts one by hand. Returns the existing project if the quote already has one.
     */
    public static function startForQuote(Quote $quote, ?User $by = null, ?string $reason = null): self
    {
        $project = DB::transaction(function () use ($quote, $by, $reason) {
            $existing = self::where('quote_id', $quote->id)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $p = new self([
                'title' => $quote->title,
                'description' => $quote->scope,
                'status' => 'active',
                'start_date' => today(),
            ]);
            $p->number = 'P-'.str_pad((string) Counter::next('project'), 4, '0', STR_PAD_LEFT);
            $p->client_id = $quote->client_id;
            $p->quote_id = $quote->id;
            $p->service_request_id = $quote->service_request_id;
            $p->start_reason = $reason;
            $p->started_by = $by?->id;
            $p->save();
            $p->wasRecentlyCreated = true;

            return $p;
        });

        if ($project->wasRecentlyCreated) {
            AuditLog::record('project.started', $project, ['number' => $project->number, 'quote' => $quote->reference(), 'reason' => $reason], $by?->id);
            $project->notifyClient(ProjectNotice::started($project));
        }

        return $project;
    }

    /** Starts a project that has no quote, for work agreed outside the portal. */
    public static function startStandalone(Client $client, string $title, User $by, string $reason): self
    {
        $p = DB::transaction(function () use ($client, $title, $by, $reason) {
            $p = new self(['title' => $title, 'status' => 'active', 'start_date' => today()]);
            $p->number = 'P-'.str_pad((string) Counter::next('project'), 4, '0', STR_PAD_LEFT);
            $p->client_id = $client->id;
            $p->start_reason = $reason;
            $p->started_by = $by->id;
            $p->save();

            return $p;
        });

        AuditLog::record('project.started', $p, ['number' => $p->number, 'reason' => $reason], $by->id);
        $p->notifyClient(ProjectNotice::started($p));

        return $p;
    }

    public function notifyClient($notification): void
    {
        try {
            Notification::send($this->client->users, $notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function notifyTeam($notification): void
    {
        try {
            Notification::send(User::where('role', User::ROLE_ADMIN)->get(), $notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
