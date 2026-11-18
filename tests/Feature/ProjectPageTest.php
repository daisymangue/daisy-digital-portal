<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Package;
use App\Models\Project;
use App\Models\ProjectMessage;
use App\Models\Quote;
use App\Models\ServiceRequest;
use App\Models\StepSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectPageTest extends TestCase
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

        $client = Client::create(['business_name' => 'Kasi Threads']);
        $this->client = User::factory()->make();
        $this->client->role = User::ROLE_CLIENT;
        $this->client->client_id = $client->id;
        $this->client->save();
    }

    private function project(): Project
    {
        $this->actingAs($this->admin);

        return Project::startStandalone($this->client->client, 'Online store', $this->admin, 'test');
    }

    public function test_step_sets_ship_with_the_app_and_can_be_managed(): void
    {
        $this->assertSame(['Website', 'Logo and design', 'Social media and marketing', 'Quick job'], StepSet::orderBy('sort_order')->pluck('name')->all());

        $this->actingAs($this->admin)->post('/admin/step-sets', ['name' => 'Translation', 'steps' => "Got the document\nTranslating\nReady for you *"])->assertSessionHasNoErrors();
        $set = StepSet::where('name', 'Translation')->sole();
        $this->assertSame([['title' => 'Got the document', 'approval' => false], ['title' => 'Translating', 'approval' => false], ['title' => 'Ready for you', 'approval' => true]], $set->steps);

        $this->post('/admin/step-sets', ['name' => 'Empty', 'steps' => "  \n "])->assertSessionHasErrors('steps');

        $package = Package::create(['name' => 'Translation pack', 'price_cents' => 50000]);
        $this->put('/admin/step-sets/links', ['packages' => [$package->id => $set->id]])->assertSessionHasNoErrors();
        $this->assertSame($set->id, $package->fresh()->step_set_id);

        $this->get('/admin/step-sets')->assertOk()->assertSee('Translation')->assertSee('Ready for you ✓');
        $this->actingAs($this->client)->get('/admin/step-sets')->assertNotFound();
    }

    public function test_a_project_from_a_quote_starts_with_its_package_steps(): void
    {
        $set = StepSet::where('name', 'Quick job')->sole();
        $package = Package::create(['name' => 'PDF to Word', 'price_cents' => 15000]);
        $package->step_set_id = $set->id;
        $package->save();

        $this->actingAs($this->client)->post('/requests', ['package_id' => $package->id])->assertSessionHasNoErrors();
        $request = ServiceRequest::sole();
        $this->actingAs($this->admin)->post("/admin/requests/{$request->id}/quotes")->assertRedirect();
        $quote = Quote::sole();
        $quote->status = 'accepted';
        $quote->save();

        $project = Project::startForQuote($quote, $this->admin, 'test');
        $this->assertSame(['Got everything we need', 'Working on it', 'Ready for you'], $project->milestones()->pluck('title')->all());
        $this->assertTrue((bool) $project->milestones()->where('title', 'Ready for you')->value('needs_approval'));
    }

    public function test_admin_can_add_a_step_set_to_a_project_without_steps(): void
    {
        $project = $this->project();
        $set = StepSet::where('name', 'Logo and design')->sole();

        $this->actingAs($this->admin)->get("/admin/projects/{$project->id}")->assertOk()->assertSee('Use these steps');
        $this->post("/admin/projects/{$project->id}/milestones", ['step_set_id' => $set->id])->assertSessionHasNoErrors();
        $this->assertSame(['Brief', 'First ideas', 'Refine', 'Final files'], $project->milestones()->pluck('title')->all());
    }

    public function test_what_we_need_sits_under_its_step_and_moves_on_when_the_step_is_done(): void
    {
        $project = $this->project();
        $project->applyStepSet(StepSet::where('name', 'Quick job')->sole());
        [$first, $second] = $project->milestones()->get()->all();

        $this->actingAs($this->admin)->post("/admin/projects/{$project->id}/checklist", ['items' => 'The PDF file', 'milestone_id' => $first->id])->assertSessionHasNoErrors();
        $item = $project->checklist()->sole();
        $this->assertSame($first->id, $item->milestone_id);

        $groups = $project->fresh(['milestones', 'checklist'])->checklistByStep();
        $this->assertSame([$item->id], array_map(fn ($i) => $i->id, $groups[$first->id]));

        // The step finishes but the item never came: it follows the work to the current step.
        $first->status = 'done';
        $first->save();
        $groups = $project->fresh(['milestones', 'checklist'])->checklistByStep();
        $this->assertArrayHasKey($second->id, $groups);

        $this->actingAs($this->client)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('We need from you (1)')->assertSee('The PDF file')->assertSee('Working on it');
    }

    public function test_review_buttons_sit_inside_the_step_waiting_for_approval(): void
    {
        $project = $this->project();
        $project->applyStepSet(StepSet::where('name', 'Quick job')->sole());
        $ready = $project->milestones()->where('title', 'Ready for you')->sole();
        $ready->status = 'awaiting_approval';
        $ready->save();

        $this->actingAs($this->client)->get("/projects/{$project->id}")->assertOk()
            ->assertSee('Ready for you to review')->assertSee('Ask for changes')
            ->assertSee(route('projects.milestones.approve', [$project->id, $ready->id]), false);
    }

    public function test_progress_updates_appear_in_the_chat(): void
    {
        $project = $this->project();
        $this->actingAs($this->admin)->post("/admin/projects/{$project->id}/updates", ['body' => 'Homepage is done, moving to the shop pages.'])
            ->assertRedirect(route('admin.projects.show', $project).'#chat');

        $update = ProjectMessage::sole();
        $this->assertSame('update', $update->kind);

        $feed = $this->actingAs($this->client)->getJson("/projects/{$project->id}/messages")->assertOk()->json('messages');
        $this->assertTrue($feed[0]['update']);
        $this->assertSame('Homepage is done, moving to the shop pages.', $feed[0]['body']);
    }

    public function test_page_has_no_tabs_and_files_open_from_the_chat_corner(): void
    {
        $project = $this->project();
        $this->actingAs($this->admin)->post("/admin/projects/{$project->id}/files", ['files' => [UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf')]]);

        $this->actingAs($this->client)->get("/projects/{$project->id}")->assertOk()
            ->assertDontSee('role="tabpanel"', false)->assertSee('chat-files-panel')->assertSee('brief.pdf')
            ->assertSee('Need something extra?')->assertSee('Show all steps')->assertDontSee('aria-label="Project"', false);
        $this->actingAs($this->admin)->get("/admin/projects/{$project->id}")->assertOk()
            ->assertDontSee('panel-details')->assertSee('Handover pack')->assertSee('brief.pdf');
    }
}
