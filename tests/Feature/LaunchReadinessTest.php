<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CarePlan;
use App\Models\Client;
use App\Models\File;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PortalInvite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');

        $this->admin = User::factory()->make(['password' => 'password-admin-1']);
        $this->admin->role = User::ROLE_ADMIN;
        $this->admin->save();
        $this->client = $this->makeClient('Kasi Threads');
    }

    private function makeClient(string $business): User
    {
        $client = Client::create(['business_name' => $business]);
        $user = User::factory()->make(['password' => 'password-client-1']);
        $user->role = User::ROLE_CLIENT;
        $user->client_id = $client->id;
        $user->save();

        return $user;
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/login')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $res = $this->actingAs($this->client)->get('/dashboard');
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }

    public function test_archived_clients_cannot_sign_in_and_are_signed_out(): void
    {
        $plan = new CarePlan(['name' => 'Care', 'amount_cents' => 1000, 'interval' => 'monthly', 'next_invoice_on' => today()->addDays(3)]);
        $plan->client_id = $this->client->client_id;
        $plan->status = 'active';
        $plan->save();

        // Signed in before the archive...
        $this->actingAs($this->client)->get('/dashboard')->assertOk();

        $this->actingAs($this->admin)->post("/admin/clients/{$this->client->client_id}/archive", ['archived_reason' => 'Finished'])->assertRedirect();
        $this->assertSame('paused', $plan->fresh()->status);

        // ...and thrown out on the next page (fresh() as a real request loads the user again).
        $this->actingAs($this->client->fresh())->get('/dashboard')->assertRedirect('/login');

        auth()->logout();
        $this->post('/login', ['email' => $this->client->email, 'password' => 'password-client-1'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // No magic link is sent either.
        $this->post('/login/link', ['email' => $this->client->email]);
        Notification::assertNothingSentTo($this->client);

        // Hidden from the list, shown under Archived, and restorable.
        $this->actingAs($this->admin)->get('/admin/clients')->assertDontSee('Kasi Threads');
        $this->actingAs($this->admin)->get('/admin/clients?archived=1')->assertSee('Kasi Threads');
        $this->actingAs($this->admin)->post("/admin/clients/{$this->client->client_id}/restore");
        auth()->logout();
        $this->post('/login', ['email' => $this->client->email, 'password' => 'password-client-1'])->assertRedirect();
        $this->assertAuthenticatedAs($this->client);
    }

    public function test_client_export_holds_their_data_and_files_only(): void
    {
        $this->actingAs($this->admin);
        $project = Project::startStandalone($this->client->client, 'Website', $this->admin, 'test');
        $this->actingAs($this->client)->post("/projects/{$project->id}/files", ['files' => [UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf')]])->assertSessionHasNoErrors();

        $other = $this->makeClient('Other Co');
        $this->actingAs($this->admin);
        $otherProject = Project::startStandalone($other->client, 'Secret project', $this->admin, 'test');

        $res = $this->actingAs($this->admin)->get("/admin/clients/{$this->client->client_id}/export")->assertOk();
        $zipPath = $res->baseResponse->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $json = $zip->getFromName('data.json');
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $zip->close();

        $this->assertStringContainsString('Kasi Threads', $json);
        $this->assertStringContainsString('Website', $json);
        $this->assertStringNotContainsString('Secret project', $json);
        $this->assertStringNotContainsString('password', $json);
        $this->assertTrue($names->contains(fn ($n) => str_ends_with($n, 'brief.pdf')));

        $this->actingAs($this->client)->get("/admin/clients/{$this->client->client_id}/export")->assertNotFound();
    }

    public function test_files_are_only_downloadable_by_their_owner_or_admins(): void
    {
        $this->actingAs($this->admin);
        $project = Project::startStandalone($this->client->client, 'Website', $this->admin, 'test');
        $this->actingAs($this->client)->post("/projects/{$project->id}/files", ['files' => [UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf')]]);
        $file = File::sole();

        $this->actingAs($this->client)->get("/files/{$file->id}")->assertOk();
        $this->actingAs($this->admin)->get("/files/{$file->id}")->assertOk();
        $this->actingAs($this->makeClient('Other'))->get("/files/{$file->id}")->assertNotFound();
        auth()->logout();
        $this->get("/files/{$file->id}")->assertRedirect('/login');
    }

    public function test_logo_upload_accepts_images_but_not_svg(): void
    {
        $base = Setting::values();
        $form = fn (array $extra) => array_merge($base, ['google_review_url' => ''], $extra);

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->actingAs($this->admin)->put('/admin/settings', $form(['logo' => $svg]))->assertSessionHasErrors('logo');

        $this->actingAs($this->admin)->put('/admin/settings', $form(['logo' => UploadedFile::fake()->image('logo.png', 400, 100)]))->assertSessionHasNoErrors();
        $this->assertNotSame('', Setting::get('logo_path'));
        $this->get('/brand/logo')->assertOk();
        auth()->logout();
        $this->get('/login')->assertSee('/brand/logo', false);

        $this->actingAs($this->admin)->put('/admin/settings', $form(['remove_logo' => '1']))->assertSessionHasNoErrors();
        Setting::flush();
        $this->assertSame('', Setting::get('logo_path'));
        $this->get('/brand/logo')->assertNotFound();
    }

    public function test_prune_removes_old_noise_but_keeps_business_records(): void
    {
        DB::table('notifications')->insert([
            ['id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => User::class, 'notifiable_id' => $this->client->id, 'data' => '{}', 'read_at' => now()->subMonths(7), 'created_at' => now()->subMonths(7), 'updated_at' => now()],
            ['id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => User::class, 'notifiable_id' => $this->client->id, 'data' => '{}', 'read_at' => null, 'created_at' => now()->subDays(3), 'updated_at' => now()],
        ]);
        $old = AuditLog::record('old.thing');
        $old->forceFill(['created_at' => now()->subYears(4)])->save();
        AuditLog::record('recent.thing');
        Storage::disk('local')->put('imports/stale.xlsx', 'x');
        touch(Storage::disk('local')->path('imports/stale.xlsx'), now()->subDays(2)->getTimestamp());
        Storage::disk('local')->put('imports/fresh.xlsx', 'x');

        $this->artisan('portal:prune')->assertSuccessful();

        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertFalse(AuditLog::where('action', 'old.thing')->exists());
        $this->assertTrue(AuditLog::where('action', 'recent.thing')->exists());
        $this->assertFalse(Storage::disk('local')->exists('imports/stale.xlsx'));
        $this->assertTrue(Storage::disk('local')->exists('imports/fresh.xlsx'));
    }

    public function test_dashboard_shows_what_needs_attention(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('all caught up');

        $this->actingAs($this->admin);
        $project = Project::startStandalone($this->client->client, 'Website', $this->admin, 'test');
        $this->actingAs($this->client)->postJson("/projects/{$project->id}/messages", ['body' => 'Hello']);

        $this->actingAs($this->admin)->get('/admin')->assertSee('Unread client messages');
    }

    public function test_launch_check_runs(): void
    {
        $this->artisan('portal:check')->assertFailed()->expectsOutputToContain('APP_ENV is production');
    }

    public function test_registering_with_the_email_of_a_profile_you_made_sends_the_invite_instead(): void
    {
        $profile = Client::create(['business_name' => 'Mayinga Electrical', 'contact_name' => 'Sipho', 'email' => 'Sipho@Example.com']);

        $this->post('/register', [
            'name' => 'Sipho M', 'business_name' => 'Mayinga Electric', 'email' => 'sipho@example.com',
            'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1', 'accept_terms' => '1',
        ])->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame(1, Client::where('business_name', 'like', 'Mayinga%')->count());
        $user = User::where('email', 'sipho@example.com')->sole();
        $this->assertSame($profile->id, $user->client_id);
        $this->assertFalse((bool) $user->has_password);
        Notification::assertSentTo($user, PortalInvite::class);
    }

    public function test_activity_log_has_its_own_page_and_the_dashboard_stays_clean(): void
    {
        AuditLog::record('quote.sent', null, ['number' => 'Q-0042'], $this->admin->id);

        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertDontSee('Recent activity')->assertSee('Activity log');
        $this->actingAs($this->admin)->get('/admin/activity')->assertOk()->assertSee('Quote sent')->assertSee('Q-0042');
        $this->actingAs($this->admin)->get('/admin/activity?group=quotes&q=Q-0042')->assertOk()->assertSee('Q-0042');
        $this->actingAs($this->admin)->get('/admin/activity?group=money')->assertOk()->assertDontSee('Q-0042');
        $this->actingAs($this->client)->get('/admin/activity')->assertNotFound();
    }
}
