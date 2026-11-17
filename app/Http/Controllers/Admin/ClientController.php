<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\MagicLinkController;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\File;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Testimonial;
use App\Models\User;
use App\Notifications\PortalInvite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientController extends Controller
{
    /** Invite links stay valid for a week. */
    public const INVITE_MINUTES = 60 * 24 * 7;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $archived = $request->boolean('archived');

        $clients = Client::withCount('serviceRequests')->with('users')
            ->when($archived, fn ($q) => $q->whereNotNull('archived_at'), fn ($q) => $q->whereNull('archived_at'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('business_name', 'like', "%{$search}%")
                ->orWhere('contact_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhereHas('users', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.clients.index', ['clients' => $clients, 'search' => $search, 'archived' => $archived, 'archivedCount' => Client::whereNotNull('archived_at')->count()]);
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
            'projects' => $client->projects()->latest()->get(),
            'plans' => $client->carePlans()->where('status', '!=', 'cancelled')->get(),
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
        return self::invite($client);
    }

    /** Creates the login for a client profile and emails a 7-day sign-in link. Also used when they try to register. */
    public static function invite(Client $client): User
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

    /** Hide a client who is no longer active. They cannot sign in, and their care plans stop billing. */
    public function archive(Request $request, Client $client)
    {
        $data = $request->validate(['archived_reason' => ['nullable', 'string', 'max:300']]);

        $client->archived_at = now();
        $client->archived_reason = $data['archived_reason'] ?? null;
        $client->save();
        $paused = $client->carePlans()->where('status', 'active')->update(['status' => 'paused']);

        AuditLog::record('client.archived', $client, ['reason' => $client->archived_reason, 'plans_paused' => $paused]);

        return redirect()->route('admin.clients.show', $client)->with('status', 'Client archived. They can no longer sign in'.($paused ? " and {$paused} care plan(s) were paused." : '.'));
    }

    public function restore(Client $client)
    {
        $client->archived_at = null;
        $client->archived_reason = null;
        $client->save();
        AuditLog::record('client.restored', $client);

        return back()->with('status', 'Client restored. Paused care plans stay paused until you switch them back on.');
    }

    /**
     * Everything held about a client in one zip: data.json plus their files. For POPIA access
     * requests, or to hand a client their records when they leave.
     */
    public function export(Client $client)
    {
        $client->load([
            'users:id,client_id,name,email,phone,created_at',
            'serviceRequests.services', 'serviceRequests.answers', 'serviceRequests.notes',
            'quotes.items', 'quotes.acceptance', 'invoices.items', 'invoices.payments',
            'projects.milestones', 'projects.updates', 'projects.messages', 'projects.checklist', 'projects.changeRequests',
            'carePlans',
        ]);
        $files = File::where('client_id', $client->id)->get();

        $data = [
            'exported_at' => now()->toIso8601String(),
            'exported_by' => auth()->user()->name,
            'client' => $client->toArray(),
            'testimonials' => Testimonial::where('client_id', $client->id)->get()->toArray(),
            'files' => $files->map(fn ($f) => ['name' => $f->original_name, 'size' => $f->size, 'uploaded' => $f->created_at->toIso8601String(), 'in_zip_as' => 'files/'.$f->id.'-'.$this->safeName($f->original_name)])->all(),
        ];

        $path = storage_path('app/private/exports/'.Str::uuid().'.zip');
        @mkdir(dirname($path), 0750, true);
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('README.txt', "Export of all records held for {$client->business_name}.\ndata.json holds requests, quotes, invoices, payments, projects and messages.\nThe files folder holds every uploaded file.\n");
        foreach ($files as $f) {
            if (Storage::disk('local')->exists($f->path)) {
                $zip->addFile(Storage::disk('local')->path($f->path), 'files/'.$f->id.'-'.$this->safeName($f->original_name));
            }
        }
        $zip->close();

        AuditLog::record('client.exported', $client, ['files' => $files->count()]);

        return response()->download($path, Str::slug($client->business_name).'-records-'.today()->format('Y-m-d').'.zip')->deleteFileAfterSend();
    }

    private function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'file';
    }
}
