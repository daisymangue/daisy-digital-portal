<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MagicLinkController;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\PortalInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    /** Invite links stay valid for a week. */
    public const INVITE_MINUTES = 60 * 24 * 7;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));

        $clients = Client::withCount('serviceRequests')->with('users')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('business_name', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('users', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.clients.index', ['clients' => $clients, 'search' => $search]);
    }

    public function create()
    {
        return view('admin.clients.create');
    }

    /** Add a client who has not registered. Portal access is optional. */
    public function store(Request $request)
    {
        $data = $this->validated($request, true);

        $client = DB::transaction(function () use ($data, $request) {
            $client = Client::create($this->clientFields($data));
            AuditLog::record('client.created_by_admin', $client, ['name' => $client->business_name]);

            if ($request->boolean('portal_access')) {
                $this->createPortalUser($client);
            }

            return $client;
        });

        $message = $request->boolean('portal_access')
            ? "{$client->business_name} added and invited to the portal."
            : "{$client->business_name} added. They have no portal login, which you can give them later.";

        return redirect()->route('admin.clients.show', $client)->with('status', $message);
    }

    public function show(Client $client)
    {
        return view('admin.clients.show', [
            'client' => $client->load('users'),
            'requests' => $client->serviceRequests()->withCount('services')->latest('submitted_at')->get(),
            'quotes' => Quote::where('client_id', $client->id)->latest()->get(),
            'invoices' => Invoice::where('client_id', $client->id)->latest()->get(),
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $data = $this->validated($request, false);
        $client->update($this->clientFields($data));
        AuditLog::record('client.updated', $client);

        return back()->with('status', 'Client details saved.');
    }

    /** Create a login for the client's email and send them an invite link. */
    public function grantAccess(Client $client)
    {
        if ($client->users()->exists()) {
            return back()->withErrors(['email' => 'This client already has portal access.']);
        }
        if (! $client->email) {
            return back()->withErrors(['email' => 'Add the client\'s email address first, then give them access.']);
        }
        if (User::where('email', strtolower($client->email))->exists()) {
            return back()->withErrors(['email' => 'That email already belongs to another portal account.']);
        }

        $this->createPortalUser($client);

        return back()->with('status', "Invite sent to {$client->email}. The link works for 7 days.");
    }

    private function createPortalUser(Client $client): User
    {
        $user = new User([
            'name' => $client->contact_name ?: $client->business_name,
            'email' => strtolower($client->email),
            // Nobody knows this password. They sign in with email links until they set their own.
            'password' => Str::random(40),
            'phone' => $client->phone,
        ]);
        $user->role = User::ROLE_CLIENT;
        $user->client_id = $client->id;
        $user->has_password = false;
        $user->save();

        AuditLog::record('client.portal_access_given', $client, ['email' => $user->email]);

        try {
            $user->notify(new PortalInvite(MagicLinkController::linkFor($user, self::INVITE_MINUTES)));
        } catch (\Throwable $e) {
            report($e);
        }

        return $user;
    }

    private function validated(Request $request, bool $creating): array
    {
        $emailRules = ['nullable', 'email', 'max:190'];
        if ($creating && $request->boolean('portal_access')) {
            $emailRules = ['required', 'email', 'max:190', 'unique:users,email'];
        }

        return $request->validate([
            'business_name' => ['required', 'string', 'max:160'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'email' => $emailRules,
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ], [
            'email.required' => 'An email address is needed to give portal access.',
            'email.unique' => 'That email already has a portal account.',
        ]);
    }

    private function clientFields(array $data): array
    {
        return [
            'business_name' => $data['business_name'],
            'contact_name' => $data['contact_name'] ?? null,
            'email' => isset($data['email']) ? strtolower($data['email']) : null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
