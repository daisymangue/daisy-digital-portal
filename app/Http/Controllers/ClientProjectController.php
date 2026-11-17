<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Project;
use App\Notifications\ProjectNotice;
use App\Support\ProjectWork;
use App\Support\RequestBuilder;
use Illuminate\Http\Request;

class ClientProjectController extends Controller
{
    /** Every lookup goes through the signed-in user's own client, so other clients' projects are a 404. */
    private function find(Request $request, string $id): Project
    {
        return $request->user()->client->projects()->findOrFail($id);
    }

    public function index(Request $request)
    {
        $projects = $request->user()->client->projects()
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('from_team', true)->whereNull('read_at')])
            ->withCount(['checklist as needed_count' => fn ($q) => $q->where('status', 'open')])
            ->orderByRaw("case when status in ('completed','cancelled') then 1 else 0 end")
            ->latest()->get();

        return view('client.projects.index', compact('projects'));
    }

    public function show(Request $request, string $id)
    {
        $project = $this->find($request, $id)->load(['milestones', 'updates.user', 'checklist.files', 'quote']);

        return view('client.projects.show', [
            'project' => $project,
            'messages' => ProjectWork::feed($project, $request->user()),
            'files' => $project->allFiles(),
        ]);
    }

    public function messages(Request $request, string $id)
    {
        $project = $this->find($request, $id);

        return response()->json(['messages' => ProjectWork::feed($project, $request->user(), (int) $request->query('after', 0))]);
    }

    public function postMessage(Request $request, string $id)
    {
        $project = $this->find($request, $id);
        $message = ProjectWork::post($request, $project);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message->toChat($request->user())]);
        }

        return redirect()->to(route('projects.show', $project->id).'#chat')->with('status', 'Message sent.');
    }

    public function approve(Request $request, string $id, int $milestone)
    {
        $project = $this->find($request, $id);
        $m = $project->milestones()->findOrFail($milestone);
        $data = $request->validate(['approved_name' => ['required', 'string', 'min:2', 'max:120']], [
            'approved_name.required' => 'Type your name to approve.',
        ]);

        abort_unless($m->status === 'awaiting_approval', 409, 'This step is not waiting for approval.');

        $m->status = 'done';
        $m->approved_name = $data['approved_name'];
        $m->approved_at = now();
        $m->client_feedback = null;
        $m->save();
        $project->recalculateProgress();

        AuditLog::record('milestone.approved', $project, ['milestone' => $m->title, 'name' => $data['approved_name']]);
        $project->notifyTeam(ProjectNotice::milestoneAnswered($m->setRelation('project', $project)));

        return back()->with('status', "Thank you. \"{$m->title}\" is approved.");
    }

    public function changes(Request $request, string $id, int $milestone)
    {
        $project = $this->find($request, $id);
        $m = $project->milestones()->findOrFail($milestone);
        $data = $request->validate(['client_feedback' => ['required', 'string', 'min:5', 'max:3000']], [
            'client_feedback.required' => 'Tell us what you would like changed.',
            'client_feedback.min' => 'Tell us a bit more about what to change.',
        ]);

        abort_unless($m->status === 'awaiting_approval', 409, 'This step is not waiting for approval.');

        $m->status = 'changes_requested';
        $m->client_feedback = $data['client_feedback'];
        $m->save();

        AuditLog::record('milestone.changes_requested', $project, ['milestone' => $m->title]);
        $project->notifyTeam(ProjectNotice::milestoneAnswered($m->setRelation('project', $project)));

        return back()->with('status', 'Thanks, we have your feedback and will make the changes.');
    }

    public function submitItem(Request $request, string $id, int $item)
    {
        $project = $this->find($request, $id);
        $i = $project->checklist()->findOrFail($item);

        abort_if($i->status === 'accepted', 409, 'We already have this one.');

        $request->validate(['client_reply' => ['nullable', 'string', 'max:3000', 'required_without:files']] + ProjectWork::fileRules(), [
            'client_reply.required_without' => 'Add a file or write a reply.',
        ] + RequestBuilder::messages());

        $i->client_reply = trim((string) $request->input('client_reply')) ?: $i->client_reply;
        $i->status = 'submitted';
        $i->submitted_at = now();
        $i->save();
        ProjectWork::storeFiles($request, $project, $i);

        AuditLog::record('checklist.submitted', $project, ['item' => $i->title]);
        $project->notifyTeam(ProjectNotice::checklistSubmitted($i->setRelation('project', $project)));

        return back()->with('status', "Sent. We will check \"{$i->title}\" and let you know.");
    }

    public function uploadFiles(Request $request, string $id)
    {
        $project = $this->find($request, $id);
        $request->validate(['files' => ['required', 'array', 'min:1', 'max:8'], 'files.*' => RequestBuilder::rules()['files.*']],
            RequestBuilder::messages() + ['files.required' => 'Choose at least one file.']);

        $count = ProjectWork::storeFiles($request, $project, $project);
        AuditLog::record('project.files_added', $project, ['count' => $count]);

        return back()->with('status', $count === 1 ? 'File shared with our team.' : "{$count} files shared with our team.");
    }
}
