<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\User;
use App\Notifications\ProjectNotice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** The parts of a project both sides use the same way: chat and file uploads. */
class ProjectWork
{
    public static function fileRules(int $max = 8): array
    {
        return [
            'files' => ['nullable', 'array', 'max:'.$max],
            'files.*' => RequestBuilder::rules()['files.*'],
        ];
    }

    public static function storeFiles(Request $request, Project $project, Model $owner): int
    {
        $count = 0;
        foreach ((array) $request->file('files', []) as $upload) {
            $owner->files()->create([
                'client_id' => $project->client_id,
                'uploaded_by' => $request->user()->id,
                'original_name' => mb_substr($upload->getClientOriginalName(), 0, 200),
                'path' => $upload->store("clients/{$project->client_id}/projects/{$project->id}", 'local'),
                'mime' => $upload->getMimeType(),
                'size' => $upload->getSize(),
            ]);
            $count++;
        }

        return $count;
    }

    /** New messages after $after, oldest first. Reading them marks the other side's messages as read. */
    public static function feed(Project $project, User $viewer, int $after = 0): array
    {
        $messages = $project->messages()->with(['user', 'files'])->where('id', '>', $after)->limit(200)->get();

        $project->messages()->where('from_team', $viewer->isClient())->whereNull('read_at')
            ->where('id', '<=', $messages->max('id') ?? $after)
            ->update(['read_at' => now()]);

        return $messages->map(fn ($m) => $m->toChat($viewer))->all();
    }

    public static function post(Request $request, Project $project): ProjectMessage
    {
        $request->validate(['body' => ['nullable', 'string', 'max:5000', 'required_without:files']] + self::fileRules(5), [
            'body.required_without' => 'Write a message or attach a file.',
        ] + RequestBuilder::messages());

        abort_if($project->status === 'cancelled', 409, 'This project is closed.');

        $user = $request->user();
        $fromTeam = $user->isAdmin();

        // Only the first unread message sends an email, so a burst of messages is one email.
        $alreadyWaiting = $project->messages()->where('from_team', $fromTeam)->whereNull('read_at')->exists();

        $message = new ProjectMessage(['body' => trim((string) $request->input('body')) ?: null]);
        $message->project_id = $project->id;
        $message->user_id = $user->id;
        $message->from_team = $fromTeam;
        $message->save();
        self::storeFiles($request, $project, $message);

        AuditLog::record('project.message', $project, ['number' => $project->number]);

        if (! $alreadyWaiting) {
            $fromTeam
                ? $project->notifyClient(ProjectNotice::message($project, 'Our team', false))
                : $project->notifyTeam(ProjectNotice::message($project, $user->name.' ('.$project->client->business_name.')', true));
        }

        return $message->load(['user', 'files']);
    }
}
