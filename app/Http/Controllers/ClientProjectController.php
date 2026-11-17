<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Testimonial;
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
        $project = $this->find($request, $id)->load(['milestones', 'updates.user', 'checklist.files', 'quote', 'changeRequests.quote', 'changeRequests.files', 'handoverFiles', 'testimonial']);

        return view('client.projects.show', [
            'project' => $project,
            'unread' => $project->unreadFor($request->user()),
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

        // After asking for changes, the client may decide it is fine as it is.
        abort_unless(in_array($m->status, ['awaiting_approval', 'changes_requested'], true), 409, 'This step is not waiting for approval.');

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

    public function requestChange(Request $request, string $id)
    {
        $project = $this->find($request, $id);
        abort_if($project->status === 'cancelled', 409, 'This project is closed.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'details' => ['required', 'string', 'min:10', 'max:5000'],
        ] + ProjectWork::fileRules(), [
            'title.required' => 'Give the change a short name.',
            'details.required' => 'Describe the change you would like.',
            'details.min' => 'Tell us a bit more so we can quote it properly.',
        ] + RequestBuilder::messages());

        $change = new ChangeRequest(['title' => $data['title'], 'details' => $data['details']]);
        $change->project_id = $project->id;
        $change->requested_by = $request->user()->id;
        $change->status = 'new';
        $change->save();
        ProjectWork::storeFiles($request, $project, $change);

        AuditLog::record('change.requested', $project, ['title' => $change->title]);
        $project->notifyTeam(ProjectNotice::changeRequested($change->setRelation('project', $project)));

        return back()->with('status', 'Change request sent. We will reply with a quote, or let you know if it is included.');
    }

    public function confirmHandover(Request $request, string $id)
    {
        $project = $this->find($request, $id);
        abort_unless($project->handover_sent_at && ! $project->handover_confirmed_at, 409, 'There is no handover waiting for you.');

        $data = $request->validate(['confirmed_name' => ['required', 'string', 'min:2', 'max:120']], ['confirmed_name.required' => 'Type your name to confirm.']);

        $project->handover_confirmed_at = now();
        $project->handover_confirmed_name = $data['confirmed_name'];
        $project->save();

        AuditLog::record('project.handover_confirmed', $project, ['name' => $data['confirmed_name']]);
        $project->notifyTeam(ProjectNotice::handoverConfirmed($project));

        if (Setting::get('ask_for_testimonial') === '1' && ! $project->testimonial()->exists()) {
            $t = new Testimonial;
            $t->client_id = $project->client_id;
            $t->project_id = $project->id;
            $t->status = 'requested';
            $t->requested_at = now();
            $t->save();

            return back()->with('status', 'Thank you for confirming. If you have a minute, tell us how we did.');
        }

        return back()->with('status', 'Thank you for confirming.');
    }

    public function testimonial(Request $request, string $id)
    {
        $project = $this->find($request, $id);
        $t = $project->testimonial;
        abort_unless($t && $t->status === 'requested', 409, 'Thank you, we already have your feedback.');

        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:2000'],
            'display_name' => ['nullable', 'string', 'max:120'],
            'can_publish' => ['nullable', 'boolean'],
        ], ['rating.required' => 'Choose a rating from 1 to 5 stars.']);

        $t->fill($data + ['can_publish' => false]);
        $t->can_publish = $request->boolean('can_publish');
        $t->status = 'received';
        $t->received_at = now();
        $t->save();

        AuditLog::record('testimonial.received', $project, ['rating' => $t->rating]);
        $project->notifyTeam(ProjectNotice::testimonialReceived($t->setRelation('project', $project)));

        return back()->with('status', 'Thank you so much for the feedback.')->with('review_prompt', $t->rating >= 4 && Setting::get('google_review_url') !== '');
    }
}
