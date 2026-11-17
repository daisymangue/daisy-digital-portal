<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Quote;
use App\Models\QuoteAcceptance;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\QuoteAnswered;
use App\Notifications\QuoteSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->admin = User::factory()->make();
        $this->admin->role = User::ROLE_ADMIN;
        $this->admin->save();

        $this->client = $this->makeClient('Kasi Threads');
    }

    private function makeClient(string $business): User
    {
        $client = Client::create(['business_name' => $business]);
        $user = User::factory()->make();
        $user->role = User::ROLE_CLIENT;
        $user->client_id = $client->id;
        $user->save();

        return $user;
    }

    private function payload(array $extra = []): array
    {
        return $extra + [
            'title' => 'Online store',
            'scope' => 'Shopify store with 40 products.',
            'timeline' => '3 to 4 weeks',
            'terms' => 'Deposit before work starts.',
            'discount' => '500',
            'vat_rate' => '15',
            'deposit_percent' => '50',
            'valid_until' => now()->addDays(30)->toDateString(),
            'items' => [
                ['description' => 'Online Store Setup', 'quantity' => '1', 'unit' => '', 'price' => '6 500'],
                ['description' => 'Content upload', 'quantity' => '2.5', 'unit' => 'hour', 'price' => '450'],
            ],
        ];
    }

    /** Create a draft for the client, fill it in and optionally send it. */
    private function quote(bool $send = true, array $extra = []): Quote
    {
        $this->actingAs($this->admin)->post('/admin/quotes', ['client_id' => $this->client->client_id])->assertRedirect();
        $quote = Quote::orderByDesc('number')->firstOrFail();
        $this->actingAs($this->admin)->put("/admin/quotes/{$quote->id}", $this->payload($extra + ['action' => $send ? 'send' : 'save']))->assertSessionHasNoErrors();

        return $quote->fresh();
    }

    public function test_totals_are_calculated_on_the_server_with_discount_vat_and_deposit(): void
    {
        $quote = $this->quote(false);

        // 6500.00 + (2.5 x 450.00 = 1125.00) = 7625.00; less 500 = 7125.00; VAT 15% = 1068.75; total 8193.75
        $this->assertSame(762500, $quote->subtotal_cents);
        $this->assertSame(50000, $quote->discount_cents);
        $this->assertSame(106875, $quote->vat_cents);
        $this->assertSame(819375, $quote->total_cents);
        $this->assertSame(409688, $quote->depositCents());
        $this->assertSame('draft', $quote->status);
        $this->assertSame('Q-0001', $quote->number);
    }

    public function test_new_quotes_use_the_settings_defaults(): void
    {
        Setting::put(['quote_valid_days' => '30', 'vat_rate' => '15', 'deposit_percent' => '40']);
        $this->actingAs($this->admin)->post('/admin/quotes', ['client_id' => $this->client->client_id]);
        $quote = Quote::firstOrFail();

        $this->assertSame(now()->addDays(30)->toDateString(), $quote->valid_until->toDateString());
        $this->assertSame(15.0, $quote->vat_rate);
        $this->assertSame(40.0, $quote->deposit_percent);
    }

    public function test_quote_from_request_copies_services_as_line_items(): void
    {
        $category = ServiceCategory::create(['name' => 'Graphic Design', 'slug' => 'graphic-design']);
        $service = Service::create(['service_category_id' => $category->id, 'name' => 'Logo Design', 'pricing_type' => 'from', 'price_cents' => 150000, 'is_from' => true]);
        $this->actingAs($this->client)->post('/requests', ['services' => [$service->id]]);
        $request = ServiceRequest::firstOrFail();

        $this->actingAs($this->admin)->post("/admin/requests/{$request->id}/quotes")->assertRedirect();
        $quote = Quote::with('items')->firstOrFail();

        $this->assertSame($request->id, $quote->service_request_id);
        $this->assertSame('Logo Design', $quote->items->first()->description);
        $this->assertSame(150000, $quote->subtotal_cents);
        $this->assertSame(172500, $quote->total_cents);
        $this->actingAs($this->admin)->get("/admin/quotes/{$quote->id}/edit")->assertOk()->assertSee('Logo Design');
    }

    public function test_drafts_are_invisible_to_the_client_and_sending_locks_the_quote(): void
    {
        $draft = $this->quote(false);
        $this->actingAs($this->client)->get("/quotes/{$draft->id}")->assertNotFound();
        $this->actingAs($this->client)->get('/quotes')->assertOk()->assertDontSee('Q-0001');

        $this->actingAs($this->admin)->put("/admin/quotes/{$draft->id}", $this->payload(['action' => 'send']));
        $sent = $draft->fresh();
        $this->assertSame('sent', $sent->status);
        Notification::assertSentTo($this->client, QuoteSent::class);

        $this->actingAs($this->client)->get("/quotes/{$sent->id}")->assertOk()->assertSee('Online store')->assertSee('R8 193.75');

        // Once sent, it cannot be edited or deleted.
        $this->actingAs($this->admin)->put("/admin/quotes/{$sent->id}", $this->payload(['title' => 'Changed']))->assertStatus(409);
        $this->actingAs($this->admin)->delete("/admin/quotes/{$sent->id}")->assertStatus(409);
        $this->assertSame('Online store', $sent->fresh()->title);
    }

    public function test_client_accepts_and_the_acceptance_is_recorded_and_frozen(): void
    {
        $quote = $this->quote();

        $this->actingAs($this->client)->post("/quotes/{$quote->id}/accept", ['accepted_name' => 'Lerato Mokoena'])->assertSessionHasErrors('confirm');
        $this->actingAs($this->client)->post("/quotes/{$quote->id}/accept", ['accepted_name' => 'Lerato Mokoena', 'confirm' => '1'])->assertSessionHasNoErrors();

        $quote->refresh();
        $this->assertSame('accepted', $quote->status);
        $this->assertSame('Lerato Mokoena', $quote->acceptance->accepted_name);
        $this->assertSame(819375, $quote->acceptance->snapshot['total_cents']);
        $this->assertCount(2, $quote->acceptance->snapshot['items']);
        Notification::assertSentTo($this->admin, QuoteAnswered::class);

        // Accepted terms cannot be revised, edited or accepted twice.
        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/revise")->assertStatus(409);
        $this->actingAs($this->admin)->put("/admin/quotes/{$quote->id}", $this->payload())->assertStatus(409);
        $this->actingAs($this->client)->post("/quotes/{$quote->id}/accept", ['accepted_name' => 'Again', 'confirm' => '1'])->assertSessionHasErrors('quote');
        $this->assertSame(1, QuoteAcceptance::count());
    }

    public function test_accepting_marks_the_request_approved(): void
    {
        $category = ServiceCategory::create(['name' => 'Design', 'slug' => 'design']);
        $service = Service::create(['service_category_id' => $category->id, 'name' => 'Flyer', 'pricing_type' => 'fixed', 'price_cents' => 65000]);
        $this->actingAs($this->client)->post('/requests', ['services' => [$service->id]]);
        $request = ServiceRequest::firstOrFail();

        $this->actingAs($this->admin)->post("/admin/requests/{$request->id}/quotes");
        $quote = Quote::firstOrFail();
        $this->actingAs($this->admin)->put("/admin/quotes/{$quote->id}", $this->payload(['action' => 'send']));
        $this->assertSame('quoted', $request->fresh()->status);

        $this->actingAs($this->client)->post("/quotes/{$quote->id}/accept", ['accepted_name' => 'Lerato', 'confirm' => '1']);
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_changes_requested_then_revised_version_replaces_the_old_one(): void
    {
        $v1 = $this->quote();

        $this->actingAs($this->client)->post("/quotes/{$v1->id}/changes", ['message' => ''])->assertSessionHasErrors('message');
        $this->actingAs($this->client)->post("/quotes/{$v1->id}/changes", ['message' => 'Please remove content upload.'])->assertSessionHasNoErrors();
        $this->assertSame('changes_requested', $v1->fresh()->status);

        $this->actingAs($this->admin)->post("/admin/quotes/{$v1->id}/revise")->assertRedirect();
        $v2 = Quote::where('version', 2)->firstOrFail();
        $this->assertSame(['Q-0001', 'draft', 2], [$v2->number, $v2->status, $v2->items()->count()]);
        // The original is untouched until the new version is sent.
        $this->assertSame('changes_requested', $v1->fresh()->status);

        $this->actingAs($this->admin)->put("/admin/quotes/{$v2->id}", $this->payload([
            'action' => 'send', 'discount' => '',
            'items' => [['description' => 'Online Store Setup', 'quantity' => '1', 'price' => '6500']],
        ]))->assertSessionHasNoErrors();

        $this->assertSame('superseded', $v1->fresh()->status);
        $this->assertSame(['sent', 747500], [$v2->fresh()->status, $v2->fresh()->total_cents]);
        $this->assertSame('Q-0001 v2', $v2->fresh()->reference());

        // The old version can no longer be accepted.
        $this->actingAs($this->client)->post("/quotes/{$v1->id}/accept", ['accepted_name' => 'Lerato', 'confirm' => '1'])->assertSessionHasErrors('quote');
    }

    public function test_expired_quotes_cannot_be_accepted_and_can_be_declined_only_while_open(): void
    {
        $quote = $this->quote();
        $this->actingAs($this->client)->post("/quotes/{$quote->id}/decline", ['message' => 'Too expensive for now'])->assertSessionHasNoErrors();
        $this->assertSame('declined', $quote->fresh()->status);

        $other = $this->quote();
        $other->valid_until = now()->subDay()->toDateString();
        $other->save();
        $this->assertSame('expired', $other->effectiveStatus());
        $this->actingAs($this->client)->post("/quotes/{$other->id}/accept", ['accepted_name' => 'Lerato', 'confirm' => '1'])->assertSessionHasErrors('quote');
        $this->assertSame(0, QuoteAcceptance::count());
    }

    public function test_another_client_cannot_see_answer_or_download_a_quote(): void
    {
        $quote = $this->quote();
        $stranger = $this->makeClient('Other Business');

        $this->actingAs($stranger)->get("/quotes/{$quote->id}")->assertNotFound();
        $this->actingAs($stranger)->get("/quotes/{$quote->id}/pdf")->assertNotFound();
        $this->actingAs($stranger)->post("/quotes/{$quote->id}/accept", ['accepted_name' => 'X Y', 'confirm' => '1'])->assertNotFound();
        $this->actingAs($stranger)->get('/admin/quotes')->assertNotFound();
        $this->assertSame('sent', $quote->fresh()->status);
    }

    public function test_pdf_downloads_for_client_and_admin(): void
    {
        $quote = $this->quote();

        $response = $this->actingAs($this->client)->get("/quotes/{$quote->id}/pdf");
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->actingAs($this->admin)->get("/admin/quotes/{$quote->id}/pdf")->assertOk();
    }

    public function test_validation_and_admin_pages(): void
    {
        $draft = $this->quote(false);

        $this->actingAs($this->admin)->put("/admin/quotes/{$draft->id}", $this->payload(['items' => []]))->assertSessionHasErrors('items');
        $this->actingAs($this->admin)->put("/admin/quotes/{$draft->id}", $this->payload(['valid_until' => now()->subDay()->toDateString()]))->assertSessionHasErrors('valid_until');
        // A quote with a zero total cannot be sent.
        $this->actingAs($this->admin)->put("/admin/quotes/{$draft->id}", $this->payload(['action' => 'send', 'discount' => '', 'items' => [['description' => 'TBC', 'quantity' => '1', 'price' => '']]]))->assertSessionHasErrors('items');
        $this->assertSame('draft', $draft->fresh()->status);

        $this->actingAs($this->admin)->get('/admin/quotes')->assertOk()->assertSee('Q-0001');
        $this->actingAs($this->admin)->get("/admin/quotes/{$draft->id}")->assertOk();
        $this->actingAs($this->admin)->get('/admin/settings')->assertOk();
        $this->actingAs($this->admin)->put('/admin/settings', ['business_name' => 'Daisy Digital Agency', 'vat_rate' => '15', 'quote_valid_days' => '30', 'deposit_percent' => '50', 'invoice_due_days' => '7', 'vat_number' => '4123456789'])->assertSessionHasNoErrors();
        $this->assertSame('4123456789', Setting::get('vat_number'));

        $this->actingAs($this->admin)->delete("/admin/quotes/{$draft->id}")->assertRedirect();
        $this->assertSoftDeleted('quotes', ['id' => $draft->id]);
    }

    public function test_client_can_still_accept_after_asking_for_changes_or_declining(): void
    {
        $quote = $this->quote();
        $this->actingAs($this->client)->post("/quotes/{$quote->id}/changes", ['message' => 'Can I get a discount?'])->assertSessionHasNoErrors();
        $this->actingAs($this->client)->get("/quotes/{$quote->id}")->assertSee('You asked for changes')->assertSee('Accept quote');

        $this->actingAs($this->client)->post("/quotes/{$quote->id}/accept", ['accepted_name' => 'Lerato', 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $quote->fresh()->status);

        $declined = $this->quote();
        $this->actingAs($this->client)->post("/quotes/{$declined->id}/decline", ['message' => 'Not now']);
        $this->actingAs($this->client)->get("/quotes/{$declined->id}")->assertSee('Changed your mind?');
        $this->actingAs($this->client)->post("/quotes/{$declined->id}/accept", ['accepted_name' => 'Lerato', 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $declined->fresh()->status);

        // The team can record a yes after the client asked for changes, too.
        $third = $this->quote();
        $this->actingAs($this->client)->post("/quotes/{$third->id}/changes", ['message' => 'Change the colour']);
        $this->actingAs($this->admin)->post("/admin/quotes/{$third->id}/answer", ['answer' => 'accepted', 'accepted_name' => 'Lerato', 'method' => 'phone', 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $third->fresh()->status);
    }

    public function test_expired_quote_can_be_renewal_requested_and_extended(): void
    {
        $quote = $this->quote();
        $quote->valid_until = now()->subDays(3)->toDateString();
        $quote->status = 'expired';
        $quote->save();

        $this->actingAs($this->client)->get("/quotes/{$quote->id}")->assertSee('Ask for this quote to be renewed')->assertDontSee('Accept quote');
        $this->actingAs($this->client)->post("/quotes/{$quote->id}/renew")->assertSessionHas('renew_asked');
        Notification::assertSentTo($this->admin, QuoteAnswered::class);

        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/extend")->assertSessionHasNoErrors();
        $quote->refresh();
        $this->assertSame('sent', $quote->status);
        $this->assertTrue($quote->valid_until->isFuture());
        Notification::assertSentTo($this->client, QuoteSent::class);

        $this->actingAs($this->client)->post("/quotes/{$quote->id}/accept", ['accepted_name' => 'Lerato', 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('accepted', $quote->fresh()->status);

        // Nothing to extend on a valid quote.
        $this->actingAs($this->admin)->post("/admin/quotes/{$this->quote()->id}/extend")->assertStatus(409);
    }
}
