<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\NewRequestSubmitted;
use App\Notifications\RequestFilesAdded;
use App\Support\RequestBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class ClientRequestController extends Controller
{
    public function index(Request $request)
    {
        return view('client.requests.index', [
            'requests' => $request->user()->client->serviceRequests()->withCount('services')->latest('submitted_at')->paginate(15),
        ]);
    }

    public function create()
    {
        return view('client.requests.create', RequestBuilder::formData());
    }

    public function store(Request $request)
    {
        $data = $request->validate(RequestBuilder::rules(), RequestBuilder::messages());
        $user = $request->user();

        $serviceRequest = RequestBuilder::create($user->client, $user, $data, $request->file('files', []), true, 'portal', 'new');

        AuditLog::record('request.submitted', $serviceRequest, ['number' => $serviceRequest->number]);

        try {
            Notification::send(User::where('role', User::ROLE_ADMIN)->get(), new NewRequestSubmitted($serviceRequest->load('client', 'user')));
        } catch (\Throwable $e) {
            // A mail problem must never lose the client's request.
            report($e);
        }

        return redirect()->route('requests.show', $serviceRequest->id)
            ->with('status', "Thank you. Request {$serviceRequest->number} has been sent to our team. We will reply with a quote or any questions.");
    }

    /** Add files to a request after sending it, for example a logo that was forgotten. */
    public function addFiles(Request $request, string $id)
    {
        $serviceRequest = $request->user()->client->serviceRequests()->withCount('files')->findOrFail($id);

        if ($serviceRequest->status === 'declined') {
            return back()->withErrors(['files' => 'This request is closed. Please start a new request.']);
        }

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:8'],
            'files.*' => RequestBuilder::rules()['files.*'],
        ], RequestBuilder::messages() + ['files.required' => 'Choose at least one file to add.']);

        if ($serviceRequest->files_count + count($data['files']) > 30) {
            return back()->withErrors(['files' => 'A request can hold up to 30 files. Please share the rest as a link in a note.']);
        }

        $user = $request->user();
        foreach ($request->file('files') as $upload) {
            $serviceRequest->files()->create([
                'client_id' => $serviceRequest->client_id,
                'uploaded_by' => $user->id,
                'original_name' => mb_substr($upload->getClientOriginalName(), 0, 200),
                'path' => $upload->store("clients/{$serviceRequest->client_id}/requests/{$serviceRequest->id}", 'local'),
                'mime' => $upload->getMimeType(),
                'size' => $upload->getSize(),
            ]);
        }

        AuditLog::record('request.files_added', $serviceRequest, ['number' => $serviceRequest->number, 'count' => count($data['files'])]);

        try {
            Notification::send(User::where('role', User::ROLE_ADMIN)->get(), new RequestFilesAdded($serviceRequest->load('client'), count($data['files'])));
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('status', count($data['files']) === 1 ? 'File added. Our team has been told.' : count($data['files']).' files added. Our team has been told.');
    }

    public function show(Request $request, string $id)
    {
        // Always looked up through the signed-in client, so another client's ID returns 404.
        $serviceRequest = $request->user()->client->serviceRequests()
            ->with(['services', 'answers', 'files'])
            ->findOrFail($id);

        return view('client.requests.show', ['serviceRequest' => $serviceRequest]);
    }
}
