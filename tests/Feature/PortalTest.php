<?php

namespace Tests\Feature;

use App\Models\CategoryQuestion;
use App\Models\Client;
use App\Models\File;
use App\Models\LoginToken;
use App\Models\Package;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\MagicLoginLink;
use App\Notifications\NewRequestSubmitted;
use App\Notifications\RequestStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    private function client(string $business = 'Kasi Threads'): User
    {
        $client = Client::create(['business_name' => $business]);
        $user = User::factory()->make();
        $user->role = User::ROLE_CLIENT;
        $user->client_id = $client->id;
        $user->save();

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->make();
        $user->role = User::ROLE_ADMIN;
        $user->save();

        return $user;
    }

    /** @return array{ServiceCategory, Service, CategoryQuestion} */
    private function catalogue(): array
    {
        $category = ServiceCategory::create(['name' => 'Website Support', 'slug' => 'website-support']);
        $service = Service::create(['service_category_id' => $category->id, 'name' => 'Website Health Check', 'pricing_type' => 'fixed', 'price_cents' => 35000]);
        $question = CategoryQuestion::create(['service_category_id' => $category->id, 'label' => 'What is your website address?', 'type' => 'text', 'is_required' => true]);

        return [$category, $service, $question];
    }

    private function submit(User $user, array $extra = []): ServiceRequest
    {
        [, $service, $question] = $this->catalogue();
        $this->actingAs($user)->post('/requests', $extra + [
            'services' => [$service->id],
            'answers' => [$question->id => 'example.co.za'],
        ])->assertSessionHasNoErrors();

        return ServiceRequest::latest('created_at')->firstOrFail();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/admin')->assertRedirect('/login');
        $this->get('/login')->assertOk();
        $this->get('/register')->assertOk();
    }

    public function test_registration_creates_a_client_and_never_an_admin(): void
    {
        $this->post('/register', [
            'name' => 'Lerato M', 'business_name' => 'Kasi Threads', 'email' => 'Lerato@Example.com',
            'password' => 'a-long-password', 'password_confirmation' => 'a-long-password', 'accept_terms' => '1',
            'role' => 'admin', // must be ignored
        ])->assertRedirect('/requests/new');

        $user = User::where('email', 'lerato@example.com')->firstOrFail();
        $this->assertSame(User::ROLE_CLIENT, $user->role);
        $this->assertSame('Kasi Threads', $user->client->business_name);
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_login_and_role_redirects(): void
    {
        $client = $this->client();
        $this->post('/login', ['email' => $client->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $client->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->post('/logout');

        $admin = $this->admin();
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
    }

    public function test_login_never_sends_a_user_to_the_other_roles_area(): void
    {
        $admin = $this->admin();
        // Admin had opened a client page before signing in.
        $this->get('/dashboard')->assertRedirect('/login');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->post('/logout');

        $this->get('/admin/quotes')->assertRedirect('/login');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin/quotes');
        $this->post('/logout');

        $client = $this->client();
        $this->get('/admin')->assertRedirect('/login');
        $this->post('/login', ['email' => $client->email, 'password' => 'password'])->assertRedirect('/dashboard');
    }

    public function test_magic_link_signs_in_once_only(): void
    {
        Notification::fake();
        $user = $this->client();

        $this->post('/login/link', ['email' => $user->email])->assertSessionHas('status');
        $url = null;
        Notification::assertSentTo($user, MagicLoginLink::class, function ($n) use (&$url, $user) {
            $url = $n->toMail($user)->actionUrl;

            return true;
        });

        $this->get($url)->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');

        $this->get($url)->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_magic_link_does_not_reveal_unknown_emails_and_expires(): void
    {
        Notification::fake();
        $this->post('/login/link', ['email' => 'nobody@example.com'])->assertSessionHas('status');
        Notification::assertNothingSent();

        $user = $this->client();
        LoginToken::create(['user_id' => $user->id, 'token_hash' => hash('sha256', 'old'), 'expires_at' => now()->subMinute()]);
        $this->get('/login/link/old')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_clients_cannot_reach_admin_and_admins_have_no_client_area(): void
    {
        $this->actingAs($this->client())->get('/admin')->assertNotFound();
        $this->actingAs($this->client())->get('/admin/requests')->assertNotFound();
        $this->actingAs($this->admin())->get('/dashboard')->assertNotFound();
        $this->actingAs($this->admin())->get('/admin')->assertOk();
    }

    public function test_client_can_submit_a_request_with_services_answers_and_files(): void
    {
        Notification::fake();
        Storage::fake('local');
        $admin = $this->admin();
        $user = $this->client();

        $this->actingAs($user)->get('/requests/new')->assertOk();

        $request = $this->submit($user, [
            'description' => 'Our contact form stopped working last week.',
            'files' => [UploadedFile::fake()->create('screenshot.png', 120, 'image/png')],
        ]);

        $this->assertSame('REQ-0001', $request->number);
        $this->assertSame('new', $request->status);
        $this->assertSame($user->client_id, $request->client_id);
        $this->assertSame('Website Health Check', $request->services->first()->name);
        $this->assertSame(35000, $request->services->first()->price_cents);
        $this->assertSame('example.co.za', $request->answers->first()->answer);
        $this->assertCount(1, $request->files);
        Storage::disk('local')->assertExists($request->files->first()->path);
        Notification::assertSentTo($admin, NewRequestSubmitted::class);

        $this->actingAs($user)->get("/requests/{$request->id}")->assertOk()->assertSee('REQ-0001')->assertSee('Website Health Check');
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('REQ-0001');
    }

    public function test_request_numbers_are_sequential(): void
    {
        Notification::fake();
        $user = $this->client();
        [, $service, $question] = $this->catalogue();
        foreach ([1, 2, 3] as $i) {
            $this->actingAs($user)->post('/requests', ['services' => [$service->id], 'answers' => [$question->id => 'x.co.za']]);
        }
        $this->assertSame(['REQ-0001', 'REQ-0002', 'REQ-0003'], ServiceRequest::orderBy('number')->pluck('number')->all());
    }

    public function test_empty_request_and_missing_required_answer_are_rejected(): void
    {
        $user = $this->client();
        [, $service] = $this->catalogue();

        $this->actingAs($user)->post('/requests', [])->assertSessionHasErrors('services');
        $this->actingAs($user)->post('/requests', ['services' => [$service->id]])->assertSessionHasErrors();
        $this->assertSame(0, ServiceRequest::count());

        // A plain description on its own is enough.
        Notification::fake();
        $this->actingAs($user)->post('/requests', ['description' => 'I need a website for my clothing business.'])->assertSessionHasNoErrors();
        $this->assertSame(1, ServiceRequest::count());
    }

    public function test_hidden_services_and_bad_file_types_are_not_accepted(): void
    {
        Notification::fake();
        Storage::fake('local');
        $user = $this->client();
        [, $service] = $this->catalogue();
        $service->update(['is_active' => false]);

        $this->actingAs($user)->post('/requests', ['services' => [$service->id]])->assertSessionHasErrors('services');
        $this->actingAs($user)->post('/requests', [
            'description' => 'Please look at this file for me.',
            'files' => [UploadedFile::fake()->create('script.php', 5, 'application/x-php')],
        ])->assertSessionHasErrors('files.0');
    }

    public function test_one_client_cannot_see_another_clients_request_or_files(): void
    {
        Notification::fake();
        Storage::fake('local');
        $owner = $this->client('Kasi Threads');
        $other = $this->client('Other Business');

        $request = $this->submit($owner, ['files' => [UploadedFile::fake()->create('logo.png', 50, 'image/png')]]);
        $file = File::firstOrFail();

        $this->actingAs($other)->get("/requests/{$request->id}")->assertNotFound();
        $this->actingAs($other)->get("/files/{$file->id}")->assertNotFound();
        $this->actingAs($other)->get('/requests')->assertOk()->assertDontSee($request->number);

        $this->actingAs($owner)->get("/files/{$file->id}")->assertOk();
        $this->actingAs($this->admin())->get("/files/{$file->id}")->assertOk();
    }

    public function test_admin_reviews_updates_status_and_adds_internal_notes(): void
    {
        Notification::fake();
        $user = $this->client();
        $admin = $this->admin();
        $request = $this->submit($user);

        $this->actingAs($admin)->get('/admin/requests')->assertOk()->assertSee($request->number);
        $this->actingAs($admin)->get("/admin/requests/{$request->id}")->assertOk()->assertSee('Internal notes');

        $this->actingAs($admin)->patch("/admin/requests/{$request->id}/status", ['status' => 'reviewing'])->assertSessionHas('status');
        $this->assertSame('reviewing', $request->fresh()->status);
        Notification::assertSentTo($user, RequestStatusChanged::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'request.status_changed', 'subject_id' => $request->id]);

        $this->actingAs($admin)->patch("/admin/requests/{$request->id}/status", ['status' => 'nonsense'])->assertSessionHasErrors('status');

        $this->actingAs($admin)->post("/admin/requests/{$request->id}/notes", ['body' => 'Secret pricing thoughts']);
        $this->actingAs($admin)->get("/admin/requests/{$request->id}")->assertSee('Secret pricing thoughts');
        // Internal notes must never reach the client.
        $this->actingAs($user)->get("/requests/{$request->id}")->assertOk()->assertDontSee('Secret pricing thoughts')->assertDontSee('Internal notes');
    }

    public function test_admin_can_manage_the_catalogue(): void
    {
        $admin = $this->admin();
        [$category, $service] = $this->catalogue();

        $this->actingAs($admin)->get('/admin/services')->assertOk()->assertSee('Website Support');
        $this->actingAs($admin)->get("/admin/services/categories/{$category->id}")->assertOk();

        $this->actingAs($admin)->put("/admin/services/{$service->id}", ['name' => 'Health Check', 'pricing_type' => 'from', 'price' => '1 250', 'is_active' => '1']);
        $service->refresh();
        $this->assertSame([125000, true, 'Health Check'], [$service->price_cents, $service->is_from, $service->name]);
        $this->assertSame('From R1 250', $service->priceLabel());

        $this->actingAs($admin)->post("/admin/services/categories/{$category->id}/services", ['name' => 'Emergency Support', 'pricing_type' => 'hourly', 'price' => '650']);
        $this->assertSame('R650 / hour', Service::where('name', 'Emergency Support')->firstOrFail()->priceLabel());

        $this->actingAs($admin)->post("/admin/services/categories/{$category->id}/questions", ['label' => 'Platform?', 'type' => 'select', 'options' => 'WordPress, Shopify']);
        $this->assertSame(['WordPress', 'Shopify'], CategoryQuestion::where('label', 'Platform?')->firstOrFail()->options);

        $this->actingAs($admin)->post('/admin/packages', ['name' => 'Care Plan', 'price' => '750', 'unit' => 'month', 'includes' => "Updates\nBackups"]);
        $this->assertDatabaseHas('packages', ['name' => 'Care Plan', 'price_cents' => 75000, 'unit' => 'month']);

        $this->actingAs($admin)->get('/admin/clients')->assertOk();
        $this->actingAs($admin)->get('/admin/packages')->assertOk()->assertSee('Care Plan');
    }

    public function test_catalogue_seeder_loads_the_real_service_list(): void
    {
        $this->seed();
        $this->assertSame(15, ServiceCategory::count());
        $this->assertSame(442, Service::count());
        $this->assertSame(10, Package::count());
        $this->assertSame('R450 / hour', Service::where('name', 'Email Troubleshooting')->firstOrFail()->priceLabel());

        // Running it again must not duplicate anything, or bring back what the admin renamed.
        Service::where('name', 'Email Troubleshooting')->update(['name' => 'Email Fixes']);
        $this->seed();
        $this->assertSame(442, Service::count());
        $this->assertSame(0, Service::where('name', 'Email Troubleshooting')->count());

        $this->actingAs($this->client())->get('/requests/new')->assertOk()->assertSee('Business Starter Package')->assertSee('Graphic Design');
    }

    public function test_account_details_can_be_updated(): void
    {
        $user = $this->client();
        $this->actingAs($user)->get('/account')->assertOk();
        $this->actingAs($user)->put('/account', ['name' => 'New Name', 'email' => $user->email, 'business_name' => 'Renamed Co'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed Co', $user->client->fresh()->business_name);

        $this->actingAs($user)->put('/account/password', ['current_password' => 'wrong', 'password' => 'another-long-one', 'password_confirmation' => 'another-long-one'])->assertSessionHasErrors('current_password');
    }
}
