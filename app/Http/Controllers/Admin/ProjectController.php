<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Counter;
use App\Models\Project;
use App\Models\ProjectChecklistItem;
use App\Models\ProjectMilestone;
use App\Models\ProjectUpdate;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Notifications\ProjectNotice;
use App\Support\ProjectWork;
use App\Support\RequestBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');
        $search = trim((string) $request->query('q'));

        $projects = Project::with('client')
            ->withCount(['messages as unread_count' => fn ($q) => $q->where('from_team', false)->whereNull('read_at')])
            ->withCount(['checklist as needed_count' => fn ($q) => $q->where('status', 'open')])
            ->withCount(['checklist as submitted_count' => fn ($q) => $q->where('status', 'submitted')])
            ->when($status === 'open', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->when($status !== 'open' && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($c) => $c->where('business_name', 'like', "%{$search}%"))))
            ->latest('updated_at')->paginate(20)->withQueryString();

        return view('admin.projects.index', [
            'projects' => $projects,
            'status' => $status,
            'search' => $search,
            'clients' => Client::whereNull('archived_at')->orderBy('business_name')->get(['id', 'business_name']),
        ]);
    }

    /** Start by hand: from an accepted quote whose deposit is not paid yet, or with no quote at all. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'quote_id' => ['nullable', 'uuid', 'exists:quotes,id'],
            'client_id' => ['required_without:quote_id', 'nullable', 'uuid', 'exists:clients,id'],
            'title' => ['required_without:quote_id', 'nullable', 'string', 'max:200'],
            'start_reason' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'start_reason.required' => 'Say why the project is starting without a paid deposit, for example "deposit waived, long-standing client".',
            'client_id.required_without' => 'Choose the client.',
            'title.required_without' => 'Give the project a name.',
        ]);

        if (! empty($data['quote_id'])) {
            $quote = Quote::findOrFail($data['quote_id']);
            abort_unless($quote->status === 'accepted', 409, 'Only an accepted quote can become a project.');
            $existing = Project::where('quote_id', $quote->id)->first();
            if ($existing) {
                return redirect()->route('admin.projects.show', $existing)->with('status', 'This quote already has a project.');
            }
            $project = Project::startForQuote($quote, $request->user(), $data['start_reason']);
        } else {
            $project = Project::startStandalone(Client::findOrFail($data['client_id']), $data['title'], $request->user(), $data['start_reason']);
        }

        return redirect()->route('admin.projects.show', $project)->with('status', "Project {$project->number} started.");
    }

    public function show(Request $request, Project $project)
    {
        $project->load(['client.users', 'quote', 'serviceRequest', 'milestones', 'updates.user', 'checklist.files', 'starter', 'changeRequests.requester', 'changeRequests.files', 'changeRequests.quote', 'handoverFiles', 'testimonial']);

        return view('admin.projects.show', [
            'project' => $project,
            'unread' => $project->unreadFor($request->user()),
            'messages' => ProjectWork::feed($project, $request->user()),
            'files' => $project->allFiles(),
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'due_date' => ['nullable', 'date'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $was = $project->status;
        $project->fill($data);
        if ($project->milestones()->doesntExist() && isset($data['progress'])) {
            $project->progress = $data['progress'];
        }
        if ($data['status'] === 'completed' && $was !== 'completed') {
            $project->completed_at = now();
            $project->progress = 100;
        } elseif ($data['status'] !== 'completed') {
            $project->completed_at = null;
        }
        $project->save();

        AuditLog::record('project.updated', $project, ['status' => $project->status]);

        return back()->with('status', 'Project saved.');
    }

    public function messages(Request $request, Project $project)
    {
        return response()->json(['messages' => ProjectWork::feed($project, $request->user(), (int) $request->query('after', 0))]);
    }

    public function postMessage(Request $request, Project $project)
    {
        $message = ProjectWork::post($request, $project);

        if ($request->expectsJson()) {
            return response()->json(['message' => $message->toChat($request->user())]);
        }

        return redirect()->to(route('admin.projects.show', $project).'#chat')->with('status', 'Message sent.');
    }

    public function postUpdate(Request $request, Project $project)
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:3', 'max:5000']]);

        $update = new ProjectUpdate($data);
        $update->project_id = $project->id;
        $update->user_id = $request->user()->id;
        $update->save();
        $project->touch();

        AuditLog::record('project.update_posted', $project);
        $project->notifyClient(ProjectNotice::update($project));

        return back()->with('status', 'Update posted. The client has been told.');
    }

    public function addMilestone(Request $request, Project $project)
    {
        if ($request->boolean('standard')) {
            $start = (int) $project->milestones()->max('sort_order');
            foreach (Project::STANDARD_MILESTONES as $n => [$title, $approval]) {
                $project->milestones()->create(['title' => $title, 'needs_approval' => $approval, 'sort_order' => $start + $n + 1]);
            }
            $project->recalculateProgress();

            return back()->with('status', 'Standard steps added. Rename or remove any that do not fit.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'due_date' => ['nullable', 'date'],
            'needs_approval' => ['nullable', 'boolean'],
        ]);
        $project->milestones()->create($data + ['needs_approval' => false, 'sort_order' => (int) $project->milestones()->max('sort_order') + 1]);
        $project->recalculateProgress();

        return back()->with('status', 'Step added.');
    }

    public function updateMilestone(Request $request, Project $project, ProjectMilestone $milestone)
    {
        abort_unless($milestone->project_id === $project->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'due_date' => ['nullable', 'date'],
            'needs_approval' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(array_keys(ProjectMilestone::STATUSES))],
        ]);

        // Approval is the client's decision. The team asks for it; only the client can give it.
        if ($milestone->needs_approval && $data['status'] === 'done' && $milestone->status !== 'done' && ! $request->boolean('skip_approval')) {
            $data['status'] = 'awaiting_approval';
        }

        $askedNow = $data['status'] === 'awaiting_approval' && $milestone->status !== 'awaiting_approval';

        $milestone->fill(['title' => $data['title'], 'due_date' => $data['due_date'] ?? null, 'needs_approval' => $request->boolean('needs_approval')]);
        $milestone->status = $data['status'];
        if ($data['status'] !== 'done') {
            $milestone->approved_at = null;
            $milestone->approved_name = null;
        }
        $milestone->save();
        $project->recalculateProgress();

        if ($askedNow) {
            $project->notifyClient(ProjectNotice::approvalNeeded($milestone->setRelation('project', $project)));
            AuditLog::record('milestone.approval_requested', $project, ['milestone' => $milestone->title]);

            return back()->with('status', "Sent to the client to approve \"{$milestone->title}\".");
        }

        return back()->with('status', 'Step saved.');
    }

    public function destroyMilestone(Project $project, ProjectMilestone $milestone)
    {
        abort_unless($milestone->project_id === $project->id, 404);
        $milestone->delete();
        $project->recalculateProgress();

        return back()->with('status', 'Step removed.');
    }

    public function addChecklist(Request $request, Project $project)
    {
        $data = $request->validate([
            'items' => ['required', 'string', 'max:3000'],
            'note' => ['nullable', 'string', 'max:500'],
        ], ['items.required' => 'Add at least one thing you need.']);

        $titles = collect(preg_split('/\r\n|\r|\n/', $data['items']))->map(fn ($t) => trim($t, " \t-•*"))->filter()->take(20);
        $order = (int) $project->checklist()->max('sort_order');
        foreach ($titles as $title) {
            $project->checklist()->create(['title' => mb_substr($title, 0, 200), 'note' => $titles->count() === 1 ? ($data['note'] ?? null) : null, 'sort_order' => ++$order]);
        }

        AuditLog::record('checklist.requested', $project, ['count' => $titles->count()]);
        $project->notifyClient(ProjectNotice::checklistRequested($project, $titles->count()));

        return back()->with('status', $titles->count() === 1 ? 'Added. The client has been asked for it.' : "{$titles->count()} items added. The client has been asked for them.");
    }

    public function checklistStatus(Request $request, Project $project, ProjectChecklistItem $item)
    {
        abort_unless($item->project_id === $project->id, 404);
        $data = $request->validate(['status' => ['required', Rule::in(['open', 'accepted'])]]);

        $item->status = $data['status'];
        $item->save();
        AuditLog::record('checklist.'.$data['status'], $project, ['item' => $item->title]);

        return back()->with('status', $data['status'] === 'accepted' ? "Marked \"{$item->title}\" as received." : "Asked for \"{$item->title}\" again.");
    }

    public function destroyChecklist(Project $project, ProjectChecklistItem $item)
    {
        abort_unless($item->project_id === $project->id, 404);
        $item->delete();

        return back()->with('status', 'Item removed.');
    }

    public function uploadFiles(Request $request, Project $project)
    {
        $request->validate(['files' => ['required', 'array', 'min:1', 'max:8'], 'files.*' => RequestBuilder::rules()['files.*']],
            RequestBuilder::messages() + ['files.required' => 'Choose at least one file.']);

        $count = ProjectWork::storeFiles($request, $project, $project);
        AuditLog::record('project.files_added', $project, ['count' => $count]);

        return back()->with('status', $count === 1 ? 'File added.' : "{$count} files added.");
    }

    public function answerChange(Request $request, Project $project, ChangeRequest $change)
    {
        abort_unless($change->project_id === $project->id, 404);

        $data = $request->validate([
            'action' => ['required', Rule::in(['quote', 'included', 'declined', 'done'])],
            'team_reply' => ['nullable', 'string', 'max:3000', 'required_if:action,declined'],
        ], ['team_reply.required_if' => 'Tell the client why, so they understand.']);

        if ($data['action'] === 'quote') {
            if ($change->quote_id) {
                return redirect()->route('admin.quotes.edit', $change->quote_id);
            }

            $quote = DB::transaction(function () use ($project, $change, $request) {
                $quote = new Quote([
                    'title' => 'Change: '.$change->title,
                    'scope' => $change->details,
                    'terms' => Setting::get('quote_terms'),
                    'vat_rate' => (float) Setting::get('vat_rate'),
                    'deposit_percent' => 0,
                    'valid_until' => now()->addDays((int) Setting::get('quote_valid_days'))->toDateString(),
                ]);
                $quote->number = 'Q-'.str_pad((string) Counter::next('quote'), 4, '0', STR_PAD_LEFT);
                $quote->version = 1;
                $quote->client_id = $project->client_id;
                $quote->service_request_id = $project->service_request_id;
                $quote->created_by = $request->user()->id;
                $quote->status = 'draft';
                $quote->save();

                $change->quote_id = $quote->id;
                $change->status = 'quoted';
                $change->team_reply = $request->input('team_reply') ?: $change->team_reply;
                $change->save();

                return $quote;
            });

            AuditLog::record('change.quoted', $project, ['title' => $change->title, 'quote' => $quote->number]);

            return redirect()->route('admin.quotes.edit', $quote)->with('status', 'Draft quote created for the change. Add the price and send it. When it is paid it stays part of this project.');
        }

        $change->status = $data['action'];
        $change->team_reply = $data['team_reply'] ?? $change->team_reply;
        $change->save();

        AuditLog::record('change.'.$data['action'], $project, ['title' => $change->title]);
        $project->notifyClient(ProjectNotice::changeAnswered($change->setRelation('project', $project)));

        return back()->with('status', 'Change request updated. The client has been told.');
    }

    public function handover(Request $request, Project $project)
    {
        $request->validate(['handover_notes' => ['nullable', 'string', 'max:10000']] + ProjectWork::fileRules());

        $project->handover_notes = $request->input('handover_notes');
        $project->save();
        if ($request->file('files')) {
            foreach ($request->file('files') as $upload) {
                $project->handoverFiles()->create([
                    'client_id' => $project->client_id,
                    'uploaded_by' => $request->user()->id,
                    'collection' => 'handover',
                    'original_name' => mb_substr($upload->getClientOriginalName(), 0, 200),
                    'path' => $upload->store("clients/{$project->client_id}/projects/{$project->id}/handover", 'local'),
                    'mime' => $upload->getMimeType(),
                    'size' => $upload->getSize(),
                ]);
            }
        }

        if ($request->input('send') === '1') {
            if (! $project->handover_notes && $project->handoverFiles()->doesntExist()) {
                return back()->withErrors(['handover_notes' => 'Add some notes or files before sending the handover.']);
            }
            $project->handover_sent_at = now();
            $project->handover_confirmed_at = null;
            $project->handover_confirmed_name = null;
            $project->save();
            AuditLog::record('project.handover_sent', $project);
            $project->notifyClient(ProjectNotice::handover($project));

            return back()->with('status', 'Handover sent. The client will be asked to confirm they have everything.');
        }

        return back()->with('status', 'Handover saved. The client cannot see it until you send it.');
    }

    public function requestTestimonial(Project $project)
    {
        $t = $project->testimonial;
        if ($t && $t->status !== 'requested') {
            return back()->with('status', 'This client already left a testimonial.');
        }

        if (! $t) {
            $t = new Testimonial;
            $t->client_id = $project->client_id;
            $t->project_id = $project->id;
            $t->status = 'requested';
        }
        $t->requested_at = now();
        $t->save();

        $project->notifyClient(ProjectNotice::testimonialRequested($project));
        AuditLog::record('testimonial.requested', $project);

        return back()->with('status', 'Testimonial request sent.');
    }
}
