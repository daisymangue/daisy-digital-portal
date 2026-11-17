<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\ProjectChecklistItem;
use App\Models\ProjectMilestone;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Every project email and dashboard update. One class with named constructors keeps the wording
 * in one place. $forTeam decides whether the link points at the admin or the client page.
 */
class ProjectNotice extends Notification
{
    private function __construct(
        private Project $project,
        private string $subject,
        private array $lines,
        private string $action,
        private bool $forTeam,
    ) {}

    public static function started(Project $p): self
    {
        return new self($p, "Your project {$p->number} has started", [
            "Work on \"{$p->title}\" has started.",
            'Your project page is where you can message our team, share files, see progress and approve work.',
        ], 'Open your project', false);
    }

    public static function message(Project $p, string $from, bool $toTeam): self
    {
        return new self($p, "New message on {$p->number}", [
            "{$from} sent you a message about \"{$p->title}\".",
        ], 'Read and reply', $toTeam);
    }

    public static function update(Project $p): self
    {
        return new self($p, "Progress update on {$p->number}", [
            "There is a new progress update on \"{$p->title}\".",
        ], 'See the update', false);
    }

    public static function approvalNeeded(ProjectMilestone $m): self
    {
        return new self($m->project, "Please review: {$m->title}", [
            "\"{$m->title}\" on {$m->project->number} is ready for you to review.",
            'You can approve it, or tell us what to change.',
        ], 'Review now', false);
    }

    public static function milestoneAnswered(ProjectMilestone $m): self
    {
        $verdict = $m->status === 'done' ? 'approved' : 'asked for changes to';

        return new self($m->project, "{$m->project->client->business_name} {$verdict} \"{$m->title}\"", array_filter([
            "On {$m->project->number}.",
            $m->status !== 'done' ? 'Feedback: '.$m->client_feedback : null,
        ]), 'Open project', true);
    }

    public static function checklistRequested(Project $p, int $count): self
    {
        return new self($p, "We need something from you for {$p->number}", [
            $count === 1 ? 'We have added an item we need from you.' : "We have added {$count} items we need from you.",
            'Send them from your project page so we can keep going.',
        ], 'See what we need', false);
    }

    public static function checklistSubmitted(ProjectChecklistItem $i): self
    {
        return new self($i->project, "{$i->project->client->business_name} sent: {$i->title}", [
            "For {$i->project->number}. Check it and mark it as received.",
        ], 'Open project', true);
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    private function url(bool $absolute = true): string
    {
        return $this->forTeam
            ? route('admin.projects.show', $this->project, $absolute)
            : route('projects.show', $this->project->id, $absolute);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->greeting('Hi '.$notifiable->name.',');
        foreach ($this->lines as $line) {
            $mail->line($line);
        }

        return $mail->action($this->action, $this->url());
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => $this->subject, 'body' => $this->project->title, 'url' => $this->url(false)];
    }
}
