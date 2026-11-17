<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ProjectMessage extends Model
{
    protected $fillable = ['body'];

    protected function casts(): array
    {
        return ['from_team' => 'boolean', 'read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }

    /** What the chat window needs. "mine" means from the viewer's side, so team members see each other's messages on the right. */
    public function toChat(User $viewer): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'author' => $this->from_team ? $this->user->name.' · Daisy Digital' : $this->user->name,
            'mine' => $this->from_team === $viewer->isAdmin(),
            'time' => $this->created_at->isToday() ? $this->created_at->format('H:i') : $this->created_at->format('j M, H:i'),
            'files' => $this->files->map(fn ($f) => ['name' => $f->original_name, 'size' => $f->sizeLabel(), 'url' => route('files.download', $f)])->all(),
        ];
    }
}
