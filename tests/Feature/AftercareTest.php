<?php

namespace Tests\Feature;

use App\Models\CarePlan;
use App\Models\ChangeRequest;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\User;
use App\Notifications\InvoiceIssued;
use App\Notifications\InvoiceReminder;
use App\Notifications\ProjectNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AftercareTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');

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

    private function project(): Project
    {
        $this->actingAs($this->admin);

        return Project::startStandalone($this->client->client, 'Website', $this->admin, 'test');
    }

    public function test_care_plan_bills_each_period_once_and_issues_automatically(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1)->setTime(6, 5));
        $this->actingAs($this->admin)->post('/admin/care-plans', [
            'client_id' => $this->client->client_id, 'name' => 'Website care', 'amount' => '1000',
            'interval' => 'monthly', 'next_invoice_on' => '2026-10-01', 'auto_issue' => '1',
        ])->assertSessionHasNoErrors();

        // Starting in the past catches up: October and November.
        $this->artisan('portal:bill-care-plans')->assertSuccessful();
        $invoices = Invoice::where('type', 'recurring')->orderBy('period_start')->get();
        $this->assertCount(2, $invoices);
        $this->assertSame('Website care, October 2026', $invoices[0]->title);
        $this->assertSame(115000, $invoices[0]->total_cents);
        $this->assertSame('issued', $invoices[1]->status);
        $this->assertNotNull($invoices[1]->number);
        Notification::assertSentTo($this->client, InvoiceIssued::class);

        // Running again the same day changes nothing.
        $this->artisan('portal:bill-care-plans');
        $this->assertSame(2, Invoice::count());
        $this->assertSame('2026-12-01', CarePlan::sole()->next_invoice_on->toDateString());

        // Paused plans skip billing.
        $plan = CarePlan::sole();
        $plan->status = 'paused';
        $plan->save();
        $this->travelTo(now()->setDate(2026, 12, 2));
        $this->artisan('portal:bill-care-plans');
        $this->assertSame(2, Invoice::count());
    }

    public function test_care_plan_can_leave_drafts_and_stops_at_its_end_date(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1));
        $plan = new CarePlan(['name' => 'Hosting', 'amount_cents' => 30000, 'interval' => 'quarterly', 'next_invoice_on' => '2026-11-01', 'auto_issue' => false, 'ends_on' => '2027-01-15']);
        $plan->client_id = $this->client->client_id;
        $plan->status = 'active';
        $plan->save();

        $plan->billDue();
        $this->assertSame('draft', Invoice::sole()->status);
        $this->assertSame('2027-02-01', $plan->fresh()->next_invoice_on->toDateString());

        $this->travelTo(now()->setDate(2027, 2, 2));
        $plan->fresh()->billDue();
        $this->assertSame(1, Invoice::count());
        $this->assertSame('cancelled', $plan->fresh()->status);
    }

    public function test_overdue_reminders_go_out_on_days_1_7_and_14_then_stop(): void
    {
        $invoice = new Invoice(['title' => 'Logo', 'vat_rate' => 15, 'due_date' => '2026-11-10']);
        $invoice->client_id = $this->client->client_id;
        $invoice->created_by = $this->admin->id;
        $invoice->type = 'custom';
        $invoice->status = 'issued';
        $invoice->number = 'INV-0009';
        $invoice->total_cents = 50000;
        $invoice->save();

        $sentOn = [];
        foreach (range(10, 30) as $day) {
            $this->travelTo(now()->setDate(2026, 11, $day)->setTime(8, 15));
            $before = $invoice->fresh()->reminders_sent;
            $this->artisan('portal:send-reminders');
            if ($invoice->fresh()->reminders_sent > $before) {
                $sentOn[] = $day;
            }
        }
        $this->assertSame([11, 17, 24], $sentOn);
        Notification::assertSentToTimes($this->client, InvoiceReminder::class, 3);

        // Switched off in settings: nothing.
        Setting::put(['overdue_reminders' => '0']);
        $invoice->forceFill(['reminders_sent' => 0])->save();
        $this->artisan('portal:send-reminders');
        $this->assertSame(0, $invoice->fresh()->reminders_sent);

        // By hand, once a day.
        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/remind")->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/remind")->assertSessionHasErrors('remind');
    }

    public function test_change_request_can_be_quoted_and_its_payment_does_not_open_a_new_project(): void
    {
        $project = $this->project();

        $this->actingAs($this->client)->post("/projects/{$project->id}/changes", [
            'title' => 'Booking page', 'details' => 'Add a page where people can book fittings.',
            'files' => [UploadedFile::fake()->image('example.png')],
        ])->assertSessionHasNoErrors();
        $change = ChangeRequest::sole();
        Notification::assertSentTo($this->admin, ProjectNotice::class);

        $this->actingAs($this->admin)->post("/admin/projects/{$project->id}/changes/{$change->id}", ['action' => 'quote'])->assertRedirect();
        $change->refresh();
        $this->assertSame('quoted', $change->status);
        $quote = $change->quote;
        $this->assertSame('Change: Booking page', $quote->title);

        // Send, accept, invoice in full and pay: still one project.
        $this->actingAs($this->admin)->put("/admin/quotes/{$quote->id}", [
            'action' => 'send', 'title' => $quote->title, 'discount' => '0', 'vat_rate' => '15', 'deposit_percent' => '0',
            'valid_until' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Booking page', 'quantity' => '1', 'price' => '2000']],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->client)->post("/quotes/{$quote->id}/accept", ['accepted_name' => 'Lerato', 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/invoices", ['type' => 'full'])->assertSessionHasNoErrors();
        $invoice = Invoice::where('quote_id', $quote->id)->sole();
        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/issue");
        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/payments", ['amount' => '2300', 'method' => 'eft', 'paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(1, Project::count());

        // Declining needs a reason.
        $this->actingAs($this->client)->post("/projects/{$project->id}/changes", ['title' => 'Free app', 'details' => 'A mobile app as well please'])->assertSessionHasNoErrors();
        $second = ChangeRequest::latest('id')->first();
        $this->actingAs($this->admin)->post("/admin/projects/{$project->id}/changes/{$second->id}", ['action' => 'declined'])->assertSessionHasErrors('team_reply');
        $this->actingAs($this->admin)->post("/admin/projects/{$project->id}/changes/{$second->id}", ['action' => 'declined', 'team_reply' => 'Out of scope, happy to quote an app separately.'])->assertSessionHasNoErrors();
        $this->assertSame('declined', $second->fresh()->status);

        // Other clients cannot add changes.
        $this->actingAs($this->makeClient('Other'))->post("/projects/{$project->id}/changes", ['title' => 'x', 'details' => 'xxxxxxxxxxxx'])->assertNotFound();
    }

    public function test_handover_confirmation_then_testimonial(): void
    {
        $project = $this->project();

        // Nothing to confirm before it is sent.
        $this->actingAs($this->client)->post("/projects/{$project->id}/handover", ['confirmed_name' => 'Lerato'])->assertStatus(409);
        $this->actingAs($this->admin)->post("/admin/projects/{$project->id}/handover", ['send' => '1'])->assertSessionHasErrors('handover_notes');

        $this->actingAs($this->admin)->post("/admin/projects/{$project->id}/handover", [
            'handover_notes' => 'Domain renews in March.', 'send' => '0',
            'files' => [UploadedFile::fake()->create('guide.pdf', 40, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $this->assertNull($project->fresh()->handover_sent_at);
        $this->actingAs($this->client)->get("/projects/{$project->id}")->assertDontSee('Domain renews in March.');

        $this->actingAs($this->admin)->post("/admin/projects/{$project->id}/handover", ['handover_notes' => 'Domain renews in March.', 'send' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($this->client)->get("/projects/{$project->id}")->assertSee('Domain renews in March.')->assertSee('guide.pdf');

        $this->actingAs($this->client)->post("/projects/{$project->id}/handover", ['confirmed_name' => 'Lerato'])->assertSessionHasNoErrors();
        $this->assertSame('Lerato', $project->fresh()->handover_confirmed_name);
        $t = Testimonial::sole();
        $this->assertSame('requested', $t->status);

        $this->actingAs($this->client)->post("/projects/{$project->id}/testimonial", ['rating' => '7'])->assertSessionHasErrors('rating');
        $this->actingAs($this->client)->post("/projects/{$project->id}/testimonial", ['rating' => '5', 'body' => 'Brilliant work', 'display_name' => 'Lerato, Kasi Threads', 'can_publish' => '1'])->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame(5, $t->rating);
        $this->assertTrue($t->can_publish);
        $this->actingAs($this->client)->post("/projects/{$project->id}/testimonial", ['rating' => '1'])->assertStatus(409);

        $this->actingAs($this->admin)->get('/admin/testimonials')->assertOk()->assertSee('Brilliant work');
        $this->actingAs($this->admin)->post("/admin/testimonials/{$t->id}/feature");
        $this->assertSame('featured', $t->fresh()->status);
        $this->actingAs($this->client)->get('/admin/testimonials')->assertNotFound();
    }

    public function test_new_admin_pages_render(): void
    {
        $project = $this->project();
        $this->actingAs($this->admin)->get('/admin/care-plans')->assertOk();
        $this->actingAs($this->admin)->get("/admin/projects/{$project->id}")->assertOk()->assertSee('Handover pack');
        $this->actingAs($this->admin)->get("/admin/clients/{$this->client->client_id}")->assertOk()->assertSee($project->number);
        $this->actingAs($this->client)->get('/dashboard')->assertOk();
        $this->actingAs($this->client)->get('/admin/care-plans')->assertNotFound();
    }
}
