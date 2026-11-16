<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Notifications\RequestStatusChanged;
use App\Support\RequestBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class RequestController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = trim((string) $request->query('q'));

        $requests = ServiceRequest::with('client', 'user')->withCount('services')
            ->when(isset(ServiceRequest::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($c) => $c->where('business_name', 'like', "%{$search}%"))
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->latest('submitted_at')->paginate(20)->withQueryString();

        return view('admin.requests.index', ['requests' => $requests, 'status' => $status, 'search' => $search]);
    }

    /** Capture a request for a client who called, emailed or messaged instead of using the portal. */
    public function create(Request $request)
    {
        return view('admin.requests.create', RequestBuilder::formData() + [
            'clients' => Client::orderBy('business_name')->get(['id', 'business_name']),
            'selected' => (string) $request->query('client', ''),
        ]);
    }

    public function store(Request $request)
    {
        $sources = array_keys(array_diff_key(ServiceRequest::SOURCES, ['portal' => true]));
        $data = $request->validate(RequestBuilder::rules() + [
            'client_id' => ['required', 'uuid', 'exists:clients,id'],
            'source' => ['required', Rule::in($sources)],
        ], RequestBuilder::messages());

        $serviceRequest = RequestBuilder::create(
            Client::findOrFail($data['client_id']), $request->user(), $data, $request->file('files', []), false, $data['source'], 'reviewing',
        );

        AuditLog::record('request.captured', $serviceRequest, ['number' => $serviceRequest->number, 'source' => $data['source']]);

        return redirect()->route('admin.requests.show', $serviceRequest)->with('status', "Request {$serviceRequest->number} saved. You can create the quote from here.");
    }

    public function show(ServiceRequest $serviceRequest)
    {
        $serviceRequest->load(['client.users', 'user', 'services', 'answers', 'files', 'notes.user']);

        return view('admin.requests.show', [
            'serviceRequest' => $serviceRequest,
            'pastCount' => $serviceRequest->client->serviceRequests()->where('id', '!=', $serviceRequest->id)->count(),
            'quotes' => Quote::where('service_request_id', $serviceRequest->id)->orderBy('number')->orderByDesc('version')->get(),
        ]);
    }

    public function status(Request $request, ServiceRequest $serviceRequest)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ServiceRequest::STATUSES))]]);

        $old = $serviceRequest->status;
        if ($old === $data['status']) {
            return back();
        }

        $serviceRequest->status = $data['status'];
        $serviceRequest->save();
        AuditLog::record('request.status_changed', $serviceRequest, ['number' => $serviceRequest->number, 'from' => $old, 'to' => $data['status']]);

        $users = $serviceRequest->client->users;
        try {
            Notification::send($users, new RequestStatusChanged($serviceRequest));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('status', $users->isEmpty()
            ? 'Status updated. This client has no portal login, so they were not emailed.'
            : 'Status updated. The client has been notified.');
    }

    public function note(Request $request, ServiceRequest $serviceRequest)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $serviceRequest->notes()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        AuditLog::record('request.note_added', $serviceRequest, ['number' => $serviceRequest->number]);

        return back()->with('status', 'Note saved. Only your team can see it.');
    }
}
