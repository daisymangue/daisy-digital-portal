<?php

namespace Tests\Feature;

use App\Models\CategoryQuestion;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\NewRequestSubmitted;
use App\Notifications\PortalInvite;
use App\Notifications\QuoteSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** The admin running the whole flow for clients who never log in. */
class OnBehalfTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->make();
        $this->admin->role = User::ROLE_ADMIN;
        $this->admin->save();
    }

    private function addClient(array $extra = []): Client
    {
        $this->actingAs($this->admin)->post('/admin/clients', $extra + [
            'business_name' => 'Mayinga Electrical', 'contact_name' => 'Sipho Mayinga', 'phone' => '082 000 0000',
            'email' => 'sipho@example.com', 'address' => '12 Main Road, Cape Town',
        ])->assertSessionHasNoErrors()->assertRedirect();

        return Client::latest()->firstOrFail();
    }

    private function sendQuote(Client $client): Quote
    {
        $this->actingAs($this->admin)->post('/admin/quotes', ['client_id' => $client->id]);
        $quote = Quote::orderByDesc('number')->firstOrFail();
        $this->actingAs($this->admin)->put("/admin/quotes/{$quote->id}", [
            'action' => 'send', 'title' => 'Website refresh', 'vat_rate' => '15', 'deposit_percent' => '50',
            'valid_until' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Website redesign', 'quantity' => '1', 'price' => '4000']],
        ])->assertSessionHasNoErrors();

        return $quote->fresh();
    }

    public function test_admin_adds_a_client_without_portal_access(): void
    {
        $client = $this->addClient();

        $this->assertSame(['Sipho Mayinga', 'sipho@example.com'], [$client->contact_name, $client->email]);
        $this->assertSame(0, $client->users()->count());
        Notification::assertNothingSent();

        $this->actingAs($this->admin)->get("/admin/clients/{$client->id}")->assertOk()->assertSee('No portal login');
        $this->actingAs($this->admin)->get('/admin/clients')->assertOk()->assertSee('Sipho Mayinga');
        $this->actingAs($this->admin)->get('/admin/clients/new')->assertOk();
    }

    public function test_admin_captures_a_request_without_required_answers_and_no_email_is_sent(): void
    {
        $client = $this->addClient();
        $category = ServiceCategory::create(['name' => 'Website Support', 'slug' => 'website-support']);
        $service = Service::create(['service_category_id' => $category->id, 'name' => 'Website Health Check', 'pricing_type' => 'fixed', 'price_cents' => 35000]);
        CategoryQuestion::create(['service_category_id' => $category->id, 'label' => 'Website address?', 'type' => 'text', 'is_required' => true]);

        $this->actingAs($this->admin)->get("/admin/requests/new?client={$client->id}")->assertOk()->assertSee('Mayinga Electrical');
        $this->actingAs($this->admin)->post('/admin/requests', [
            'client_id' => $client->id, 'source' => 'phone', 'services' => [$service->id], 'description' => 'Called about a slow site.',
        ])->assertSessionHasNoErrors();

        $request = ServiceRequest::firstOrFail();
        $this->assertSame([$client->id, 'phone', 'reviewing', $this->admin->id], [$request->client_id, $request->source, $request->status, $request->user_id]);
        Notification::assertNotSentTo($this->admin, NewRequestSubmitted::class);

        $this->actingAs($this->admin)->get("/admin/requests/{$request->id}")->assertOk()->assertSee('Phone call')->assertSee('Sipho Mayinga');
        $this->actingAs($this->admin)->post('/admin/requests', ['client_id' => $client->id, 'source' => 'portal', 'description' => 'Not allowed source'])->assertSessionHasErrors('source');
    }

    public function test_full_flow_for_a_client_who_never_logs_in(): void
    {
        $client = $this->addClient();
        $quote = $this->sendQuote($client);
        $this->assertSame('sent', $quote->status);
        Notification::assertNothingSent();

        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/answer", ['answer' => 'accepted', 'accepted_name' => 'Sipho Mayinga', 'method' => 'email'])
            ->assertSessionHasErrors('confirm');
        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/answer", [
            'answer' => 'accepted', 'accepted_name' => 'Sipho Mayinga', 'method' => 'email', 'note' => 'Email of 16 Nov', 'confirm' => '1',
        ])->assertSessionHasNoErrors();

        $quote->refresh();
        $this->assertSame('accepted', $quote->status);
        $this->assertSame(['Sipho Mayinga', 'email', $this->admin->id], [$quote->acceptance->accepted_name, $quote->acceptance->method, $quote->acceptance->recorded_by]);
        $this->assertSame(460000, $quote->acceptance->snapshot['total_cents']);

        $pdf = $this->actingAs($this->admin)->get("/admin/quotes/{$quote->id}/pdf");
        $pdf->assertOk();

        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/invoices", ['type' => 'deposit'])->assertSessionHasNoErrors();
        $invoice = Invoice::firstOrFail();
        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/issue")->assertSessionHas('status', fn ($m) => str_contains($m, 'no portal login'));
        Notification::assertNothingSent();

        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/payments", ['amount' => '2300', 'method' => 'eft', 'paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_admin_can_record_a_decline_and_answers_need_an_open_quote(): void
    {
        $client = $this->addClient();
        $quote = $this->sendQuote($client);

        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/answer", ['answer' => 'declined', 'method' => 'whatsapp', 'note' => 'Budget is tight'])->assertSessionHasNoErrors();
        $this->assertSame('declined', $quote->fresh()->status);
        $this->assertStringContainsString('Budget is tight', $quote->fresh()->client_message);

        // Past its valid date, nothing can be recorded until the team extends it.
        $quote->valid_until = now()->subDay()->toDateString();
        $quote->save();
        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/answer", ['answer' => 'accepted', 'accepted_name' => 'Sipho', 'method' => 'email', 'confirm' => '1'])->assertSessionHasErrors('answer');
        $this->assertNull($quote->fresh()->acceptance);

        // Client changed their mind: extend, then record the yes.
        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/extend")->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/answer", ['answer' => 'accepted', 'accepted_name' => 'Sipho', 'method' => 'email', 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $quote->fresh()->status);
    }

    public function test_giving_portal_access_invites_the_client_who_then_sees_everything(): void
    {
        $client = $this->addClient();
        $quote = $this->sendQuote($client);

        $this->actingAs($this->admin)->post("/admin/clients/{$client->id}/access")->assertSessionHasNoErrors();
        $user = User::where('email', 'sipho@example.com')->firstOrFail();
        $this->assertSame([User::ROLE_CLIENT, $client->id, false], [$user->role, $user->client_id, $user->has_password]);

        $url = null;
        Notification::assertSentTo($user, PortalInvite::class, function ($n) use (&$url, $user) {
            $url = $n->toMail($user)->actionUrl;

            return true;
        });
        $this->post('/logout');
        $this->get($url)->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        // Work the admin created earlier is visible to them now.
        $this->get("/quotes/{$quote->id}")->assertOk()->assertSee('Website refresh');

        // They can set a password without knowing the random one.
        $this->put('/account/password', ['password' => 'my-new-password', 'password_confirmation' => 'my-new-password'])->assertSessionHasNoErrors();
        $this->assertTrue($user->fresh()->has_password);

        // Access can only be given once.
        $this->actingAs($this->admin)->post("/admin/clients/{$client->id}/access")->assertSessionHasErrors('email');
    }

    public function test_access_on_creation_needs_an_unused_email_and_sends_invite(): void
    {
        $this->actingAs($this->admin)->post('/admin/clients', ['business_name' => 'No Email Co', 'portal_access' => '1'])->assertSessionHasErrors('email');

        $client = $this->addClient(['portal_access' => '1']);
        $this->assertSame(1, $client->users()->count());
        Notification::assertSentTo($client->users()->first(), PortalInvite::class);

        $this->actingAs($this->admin)->post('/admin/clients', ['business_name' => 'Duplicate', 'email' => 'sipho@example.com', 'portal_access' => '1'])->assertSessionHasErrors('email');
    }

    public function test_quote_sent_to_a_client_with_access_is_emailed(): void
    {
        $client = $this->addClient(['portal_access' => '1']);
        $this->sendQuote($client);
        Notification::assertSentTo($client->users()->first(), QuoteSent::class);
    }

    public function test_clients_cannot_use_admin_on_behalf_actions(): void
    {
        $client = $this->addClient(['portal_access' => '1']);
        $quote = $this->sendQuote($client);
        $user = $client->users()->first();

        $this->actingAs($user)->post("/admin/quotes/{$quote->id}/answer", ['answer' => 'accepted', 'accepted_name' => 'X', 'method' => 'email', 'confirm' => '1'])->assertNotFound();
        $this->actingAs($user)->post('/admin/clients', ['business_name' => 'Hack'])->assertNotFound();
        $this->actingAs($user)->post('/admin/requests', ['client_id' => $client->id, 'source' => 'phone', 'description' => 'Trying to sneak in'])->assertNotFound();
        $this->assertSame('sent', $quote->fresh()->status);
    }

    public function test_registering_with_an_email_already_in_use_explains_what_to_do(): void
    {
        $this->addClient(['portal_access' => '1']);
        $this->post('/logout');
        $this->post('/register', [
            'name' => 'Sipho', 'business_name' => 'Mayinga', 'email' => 'sipho@example.com',
            'password' => 'a-long-password', 'password_confirmation' => 'a-long-password', 'accept_terms' => '1',
        ])->assertSessionHasErrors(['email' => 'This email already has an account. Sign in instead, and choose "Email me a link" if you do not have a password.']);
    }
}
