<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\ProjectNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectTest extends TestCase
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

    private function acceptedQuote(?User $client = null): Quote
    {
        $client ??= $this->client;
        $this->actingAs($this->admin)->post('/admin/quotes', ['client_id' => $client->client_id]);
        $quote = Quote::where('client_id', $client->client_id)->latest('created_at')->firstOrFail();
        $this->actingAs($this->admin)->put("/admin/quotes/{$quote->id}", [
            'action' => 'send', 'title' => 'Online store', 'discount' => '0', 'vat_rate' => '15', 'deposit_percent' => '50',
            'valid_until' => now()->addDays(30)->toDateString(),
            'items' => [['description' => 'Online Store Setup', 'quantity' => '1', 'price' => '1000']],
        ])->assertSessionHasNoErrors();
        $this->actingAs($client)->post("/quotes/{$quote->id}/accept", ['accepted_name' => 'Lerato', 'confirm' => '1'])->assertSessionHasNoErrors();

        return $quote->fresh();
    }

    private function issuedInvoice(Quote $quote, string $type): Invoice
    {
        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/invoices", ['type' => $type])->assertSessionHasNoErrors();
        $invoice = Invoice::where('quote_id', $quote->id)->where('type', $type)->firstOrFail();
        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/issue")->assertSessionHasNoErrors();

        return $invoice->fresh();
    }

    private function projectFor(Quote $quote): Project
    {
        return Project::startForQuote($quote, $this->admin, 'test');
    }

    public function test_paying_the_deposit_opens_the_project_once(): void
    {
        $quote = $this->acceptedQuote();
        $invoice = $this->issuedInvoice($quote, 'deposit');
        $this->assertSame(0, Project::count());

        // A part payment is not enough.
        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/payments", ['amount' => '100', 'method' => 'eft', 'paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame(0, Project::count());

        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/payments", ['amount' => '475', 'method' => 'eft', 'paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh()->status);

        $project = Project::sole();
        $this->assertSame('P-0001', $project->number);
        $this->assertSame($quote->id, $project->quote_id);
        $this->assertSame('Online store', $project->title);
        $this->assertNull($project->start_reason);
        Notification::assertSentTo($this->client, ProjectNotice::class);

        // Paying the balance later does not open a second project.
        $balance = $this->issuedInvoice($quote, 'balance');
        $this->actingAs($this->admin)->post("/admin/invoices/{$balance->id}/payments", ['amount' => '575', 'method' => 'eft', 'paid_on' => today()->toDateString()]);
        $this->assertSame(1, Project::count());
    }

    public function test_confirming_proof_of_payment_also_opens_the_project(): void
    {
        $quote = $this->acceptedQuote();
        $invoice = $this->issuedInvoice($quote, 'full');
        $this->actingAs($this->client)->post("/invoices/{$invoice->id}/pay", [
            'amount' => '1150', 'paid_on' => today()->toDateString(),
            'proof' => UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, Project::count());

        $payment = Payment::sole();
        $this->actingAs($this->admin)->post("/admin/payments/{$payment->id}/confirm", ['amount' => '1150'])->assertSessionHasNoErrors();
        $this->assertSame(1, Project::count());
    }

    public function test_starting_by_hand_needs_a_reason(): void
    {
        $quote = $this->acceptedQuote();

        $this->actingAs($this->admin)->post('/admin/projects', ['quote_id' => $quote->id])->assertSessionHasErrors('start_reason');
        $this->assertSame(0, Project::count());

        $this->actingAs($this->admin)->post('/admin/projects', ['quote_id' => $quote->id, 'start_reason' => 'Deposit waived'])->assertRedirect();
        $project = Project::sole();
        $this->assertSame('Deposit waived', $project->start_reason);
        $this->assertSame($this->admin->id, $project->started_by);

        // Starting it again just goes to the existing one.
        $this->actingAs($this->admin)->post('/admin/projects', ['quote_id' => $quote->id, 'start_reason' => 'Again']);
        $this->assertSame(1, Project::count());

        // And a project with no quote at all.
        $this->actingAs($this->admin)->post('/admin/projects', ['client_id' => $this->client->client_id, 'title' => 'Monthly care', 'start_reason' => 'Agreed by phone'])->assertSessionHasNoErrors();
        $this->assertSame(2, Project::count());
        $this->assertSame('P-0002', Project::where('title', 'Monthly care')->value('number'));
    }

    public function test_clients_only_see_their_own_projects(): void
    {
        $project = $this->projectFor($this->acceptedQuote());
        $other = $this->makeClient('Other Co');

        $this->actingAs($this->client)->get("/projects/{$project->id}")->assertOk()->assertSee('Online store');
        $this->actingAs($this->client)->get('/projects')->assertOk()->assertSee($project->number);

        $this->actingAs($other)->get("/projects/{$project->id}")->assertNotFound();
        $this->actingAs($other)->get("/projects/{$project->id}/messages")->assertNotFound();
        $this->actingAs($other)->post("/projects/{$project->id}/messages", ['body' => 'Hi'])->assertNotFound();
        $this->actingAs($other)->get('/projects')->assertDontSee($project->number);
        $this->actingAs($this->client)->get("/admin/projects/{$project->id}")->assertNotFound();
    }

    public function test_chat_marks_read_and_emails_only_the_first_unread_message(): void
    {
        $project = $this->projectFor($this->acceptedQuote());
        Notification::fake();

        $this->actingAs($this->client)->postJson("/projects/{$project->id}/messages", ['body' => 'Hello team'])->assertOk()->assertJsonPath('message.mine', true);
        $this->actingAs($this->client)->postJson("/projects/{$project->id}/messages", ['body' => 'One more thing'])->assertOk();
        Notification::assertSentToTimes($this->admin, ProjectNotice::class, 1);

        // The admin's nav shows 2 unread until they open the project.
        $this->assertSame(2, ProjectMessage::where('from_team', false)->whereNull('read_at')->count());
        $this->actingAs($this->admin)->get("/admin/projects/{$project->id}")->assertOk()->assertSee('Hello team');
        $this->assertSame(0, ProjectMessage::where('from_team', false)->whereNull('read_at')->count());

        // The reply shows up for the client on the next poll, from the other side.
        $this->actingAs($this->admin)->postJson("/admin/projects/{$project->id}/messages", [
            'body' => 'Got it', 'files' => [UploadedFile::fake()->image('draft.png')],
        ])->assertOk();
        $lastClientId = ProjectMessage::where('from_team', false)->max('id');
        $this->actingAs($this->client)->getJson("/projects/{$project->id}/messages?after={$lastClientId}")
            ->assertOk()->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Got it')
            ->assertJsonPath('messages.0.mine', false)
            ->assertJsonPath('messages.0.files.0.name', 'draft.png');
        Notification::assertSentTo($this->client, ProjectNotice::class);

        $this->actingAs($this->client)->postJson("/projects/{$project->id}/messages", ['body' => ''])->assertStatus(422);
    }

    public function test_approval_steps_wait_for_the_client(): void
    {
        $project = $this->projectFor($this->acceptedQuote());
        $this->actingAs($this->admin)->post("/admin/projects/{$project->id}/milestones", ['standard' => '1']);
        $this->assertSame(5, $project->milestones()->count());

        $design = $project->milestones()->where('needs_approval', true)->first();

        // Setting an approval step to done asks the client instead of finishing it.
        $this->actingAs($this->admin)->put("/admin/projects/{$project->id}/milestones/{$design->id}", ['title' => $design->title, 'status' => 'done', 'needs_approval' => '1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('awaiting_approval', $design->fresh()->status);
        Notification::assertSentTo($this->client, ProjectNotice::class);

        $this->actingAs($this->client)->post("/projects/{$project->id}/milestones/{$design->id}/changes", ['client_feedback' => 'Make the logo bigger please'])->assertSessionHasNoErrors();
        $this->assertSame('changes_requested', $design->fresh()->status);

        // Cannot approve something that is not waiting.
        $this->actingAs($this->client)->post("/projects/{$project->id}/milestones/{$design->id}/approve", ['approved_name' => 'Lerato'])->assertStatus(409);

        $this->actingAs($this->admin)->put("/admin/projects/{$project->id}/milestones/{$design->id}", ['title' => $design->title, 'status' => 'awaiting_approval', 'needs_approval' => '1']);
        $this->actingAs($this->client)->post("/projects/{$project->id}/milestones/{$design->id}/approve", ['approved_name' => 'Lerato'])->assertSessionHasNoErrors();

        $design->refresh();
        $this->assertSame('done', $design->status);
        $this->assertSame('Lerato', $design->approved_name);
        $this->assertSame(20, $project->fresh()->progress);

        // Another client cannot touch this step.
        $other = $this->makeClient('Other Co');
        $this->actingAs($other)->post("/projects/{$project->id}/milestones/{$design->id}/approve", ['approved_name' => 'X'])->assertNotFound();
    }

    public function test_checklist_items_go_from_needed_to_received(): void
    {
        $project = $this->projectFor($this->acceptedQuote());

        $this->actingAs($this->admin)->post("/admin/projects/{$project->id}/checklist", ['items' => "- Logo\n\nPhotos of the shop\n"])->assertSessionHasNoErrors();
        $this->assertSame(['Logo', 'Photos of the shop'], $project->checklist()->pluck('title')->all());

        $logo = $project->checklist()->first();
        $this->actingAs($this->client)->post("/projects/{$project->id}/checklist/{$logo->id}", [])->assertSessionHasErrors('client_reply');
        $this->actingAs($this->client)->post("/projects/{$project->id}/checklist/{$logo->id}", [
            'files' => [UploadedFile::fake()->image('logo.png')],
        ])->assertSessionHasNoErrors();

        $this->assertSame('submitted', $logo->fresh()->status);
        $this->assertSame(1, $logo->files()->count());
        Notification::assertSentTo($this->admin, ProjectNotice::class);

        $this->actingAs($this->admin)->patch("/admin/projects/{$project->id}/checklist/{$logo->id}", ['status' => 'accepted']);
        $this->assertSame('accepted', $logo->fresh()->status);

        // Files from the checklist show in the project's file list for both sides.
        $this->actingAs($this->client)->get("/projects/{$project->id}")->assertSee('logo.png');
        $this->actingAs($this->admin)->get("/admin/projects/{$project->id}")->assertSee('logo.png');
    }

    public function test_completing_a_project_sets_full_progress(): void
    {
        $project = $this->projectFor($this->acceptedQuote());
        $this->actingAs($this->admin)->put("/admin/projects/{$project->id}", ['title' => 'Online store', 'status' => 'completed'])->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(100, $project->progress);
        $this->assertNotNull($project->completed_at);
    }
}
