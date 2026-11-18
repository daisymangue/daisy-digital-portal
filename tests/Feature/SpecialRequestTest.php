<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Package;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\RequestFilesAdded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SpecialRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $client;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $client = Client::create(['business_name' => 'Glow Salon']);
        $this->client = User::factory()->make();
        $this->client->role = User::ROLE_CLIENT;
        $this->client->client_id = $client->id;
        $this->client->save();

        $this->admin = User::factory()->make();
        $this->admin->role = User::ROLE_ADMIN;
        $this->admin->save();
    }

    public function test_a_special_request_on_its_own_is_accepted_with_budget_and_deadline(): void
    {
        $this->actingAs($this->client)->post('/requests', [
            'special_details' => 'A booking system for my salon where clients pay a deposit.',
            'budget_range' => 'R5 000 to R15 000',
            'needed_by' => today()->addMonth()->toDateString(),
        ])->assertSessionHasNoErrors();

        $request = ServiceRequest::firstOrFail();
        $this->assertTrue($request->isSpecial());
        $this->assertSame('R5 000 to R15 000', $request->budget_range);
        $this->assertSame(today()->addMonth()->toDateString(), $request->needed_by->toDateString());

        $this->actingAs($this->client)->get("/requests/{$request->id}")->assertOk()->assertSee('Special request')->assertSee('R5 000 to R15 000');
        $this->actingAs($this->admin)->get('/admin/requests')->assertOk()->assertSee('Special');
        $this->actingAs($this->admin)->get("/admin/requests/{$request->id}")->assertOk()->assertSee('A booking system for my salon');

        $this->actingAs($this->admin)->post("/admin/requests/{$request->id}/quotes");
        $this->assertSame('A booking system for my salon where clients pay a deposit.', Quote::firstOrFail()->title);
    }

    public function test_special_request_validation(): void
    {
        $this->actingAs($this->client)->post('/requests', ['special_details' => 'Too short'])->assertSessionHasErrors('special_details');
        $this->actingAs($this->client)->post('/requests', ['special_details' => 'A booking system for my salon.', 'budget_range' => 'A million'])->assertSessionHasErrors('budget_range');
        $this->actingAs($this->client)->post('/requests', ['special_details' => 'A booking system for my salon.', 'needed_by' => today()->subDay()->toDateString()])->assertSessionHasErrors('needed_by');
        $this->assertSame(0, ServiceRequest::count());
    }

    public function test_budget_and_deadline_are_kept_with_a_package_too(): void
    {
        $package = Package::create(['name' => 'Starter', 'price_cents' => 199900, 'is_from' => true]);
        $this->actingAs($this->client)->post('/requests', [
            'package_id' => $package->id, 'budget_range' => 'Under R2 000', 'needed_by' => today()->addWeek()->toDateString(),
        ])->assertSessionHasNoErrors();

        $request = ServiceRequest::firstOrFail();
        $this->assertFalse($request->isSpecial());
        $this->assertSame('Under R2 000', $request->budget_range);
        $this->assertSame(today()->addWeek()->toDateString(), $request->needed_by->toDateString());
    }

    public function test_call_back_request_lands_as_the_first_line(): void
    {
        $this->actingAs($this->client)->post('/requests', ['callback_phone' => '079 083 5197', 'callback_time' => 'Morning'])->assertSessionHasNoErrors();
        $request = ServiceRequest::latest('id')->firstOrFail();
        $this->assertStringStartsWith('Please call me back on 079 083 5197 (Morning).', $request->special_details);

        $this->actingAs($this->client)->post('/requests', ['callback_phone' => 'call me'])->assertSessionHasErrors('callback_phone');
    }

    public function test_request_page_offers_everyday_gigs(): void
    {
        $this->actingAs($this->client)->get('/requests/new')->assertOk()
            ->assertSee('ANYTHING GOES')->assertSee('PDF to Word')->assertSee('Online application')->assertSee('Unusual job?')
            ->assertDontSee('If it is IT related');
    }

    public function test_featured_package_is_shown_first_with_a_badge_and_admin_can_set_it(): void
    {
        Package::create(['name' => 'Plain Package', 'price_cents' => 100000, 'sort_order' => 1]);
        $featured = Package::create(['name' => 'Popular Package', 'price_cents' => 200000, 'sort_order' => 2, 'is_featured' => true]);

        $this->actingAs($this->client)->get('/requests/new')->assertOk()
            ->assertSeeInOrder(['Popular Package', 'Plain Package'])
            ->assertSee('Most popular')
            ->assertSee('Tell us what you need');

        $this->actingAs($this->admin)->put("/admin/packages/{$featured->id}", ['name' => 'Popular Package', 'price' => '2000', 'is_active' => '1']);
        $this->assertFalse($featured->fresh()->is_featured);
    }

    public function test_only_three_packages_show_until_see_all_and_popular_services_appear(): void
    {
        foreach (range(1, 5) as $i) {
            Package::create(['name' => "Package {$i}", 'price_cents' => 100000, 'sort_order' => $i]);
        }
        Package::create(['name' => 'Hidden Package', 'price_cents' => 100000, 'is_active' => false]);
        $category = ServiceCategory::create(['name' => 'Graphic Design', 'slug' => 'graphic-design']);
        Service::create(['service_category_id' => $category->id, 'name' => 'Logo Design', 'pricing_type' => 'fixed', 'price_cents' => 150000, 'is_popular' => true]);

        $page = $this->actingAs($this->client)->get('/requests/new')->assertOk();
        $page->assertSee('See all 5 packages')->assertDontSee('Hidden Package')->assertSee('Popular services')->assertSee('Search all services');
    }

    public function test_files_can_be_sent_with_a_special_request_and_added_later(): void
    {
        Storage::fake('local');
        $file = fn ($name) => UploadedFile::fake()->create($name, 100, 'image/png');

        $this->actingAs($this->client)->post('/requests', [
            'special_details' => 'A booking page like the one in this screenshot.',
            'files' => [$file('example.png')],
        ])->assertSessionHasNoErrors();
        $request = ServiceRequest::firstOrFail();
        $this->assertCount(1, $request->files);

        $this->actingAs($this->client)->get("/requests/{$request->id}")->assertOk()->assertSee('Forgot something?');
        $this->actingAs($this->client)->post("/requests/{$request->id}/files", ['files' => [$file('logo.png'), $file('menu.png')]])->assertSessionHasNoErrors();
        $this->assertCount(3, $request->fresh()->files);
        Notification::assertSentTo($this->admin, RequestFilesAdded::class);

        $this->actingAs($this->client)->post("/requests/{$request->id}/files", [])->assertSessionHasErrors('files');
        $this->actingAs($this->client)->post('/requests', [
            'special_details' => 'Too many files attached to this one.',
            'files' => array_map(fn ($i) => $file("f{$i}.png"), range(1, 9)),
        ])->assertSessionHasErrors('files');

        // Another client cannot add files to this request.
        $other = Client::create(['business_name' => 'Other']);
        $stranger = User::factory()->make();
        $stranger->role = User::ROLE_CLIENT;
        $stranger->client_id = $other->id;
        $stranger->save();
        $this->actingAs($stranger)->post("/requests/{$request->id}/files", ['files' => [$file('x.png')]])->assertNotFound();
        $this->assertCount(3, $request->fresh()->files);
    }

    public function test_seeder_marks_one_featured_package(): void
    {
        $this->seed();
        $this->assertSame(['Business Essentials Package'], Package::where('is_featured', true)->pluck('name')->all());
        $this->assertSame(8, Service::where('is_popular', true)->count());
    }

    public function test_package_sale_price_shows_crossed_out_and_is_honoured_on_the_quote(): void
    {
        $package = Package::create(['name' => 'Starter Pack', 'price_cents' => 499900, 'is_from' => true, 'is_active' => true, 'includes' => ['Logo'], 'sort_order' => 1]);
        $normal = $package->price_cents;

        // A sale price must be lower than the normal price.
        $this->actingAs($this->admin)->put("/admin/packages/{$package->id}", [
            'name' => $package->name, 'price' => (string) ($normal / 100), 'sale_price' => (string) ($normal / 100 + 1), 'is_active' => '1',
        ])->assertSessionHasErrors('sale_price');

        $this->actingAs($this->admin)->put("/admin/packages/{$package->id}", [
            'name' => $package->name, 'price' => (string) ($normal / 100), 'sale_price' => (string) ($normal / 100 - 1000),
            'sale_ends_on' => today()->addDays(5)->toDateString(), 'is_active' => '1', 'is_from' => '1',
        ])->assertSessionHasNoErrors();
        $package->refresh();
        $this->assertTrue($package->onSale());

        $this->actingAs($this->client)->get('/requests/new')->assertOk()
            ->assertSee('Save R1 000')->assertSee('<del', false)->assertSee($package->regularPriceLabel());

        $this->actingAs($this->client)->post('/requests', ['package_id' => $package->id])->assertSessionHasNoErrors();
        $request = ServiceRequest::latest('id')->firstOrFail();

        // The sale ends before the quote is made: the client still gets the price they saw.
        $this->travel(10)->days();
        $this->assertFalse($package->fresh()->onSale());
        $this->actingAs($this->admin)->post("/admin/requests/{$request->id}/quotes");
        $line = Quote::latest('created_at')->firstOrFail()->items()->first();
        $this->assertSame($normal - 100000, $line->unit_price_cents);
        $this->assertStringContainsString('special price, normally', $line->description);
    }
}
