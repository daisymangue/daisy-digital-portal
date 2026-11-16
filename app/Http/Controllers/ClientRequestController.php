<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\NewRequestSubmitted;
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

    public function show(Request $request, string $id)
    {
        // Always looked up through the signed-in client, so another client's ID returns 404.
        $serviceRequest = $request->user()->client->serviceRequests()
            ->with(['services', 'answers', 'files'])
            ->findOrFail($id);

        return view('client.requests.show', ['serviceRequest' => $serviceRequest]);
    }
}
