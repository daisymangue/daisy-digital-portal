<?php

namespace Tests\Feature;

use App\Models\CarePlan;
use App\Models\CarePlanTemplate;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BillingTest extends TestCase
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

        $client = Client::create(['business_name' => 'Kasi Threads']);
        $this->client = User::factory()->make();
        $this->client->role = User::ROLE_CLIENT;
        $this->client->client_id = $client->id;
        $this->client->save();
    }

    /** A plan billed in October with the bill left unpaid past its due date. */
    private function planWithOverdueBill(): CarePlan
    {
        $this->travelTo(now()->setDate(2026, 10, 1)->setTime(9, 0));
        $this->actingAs($this->admin)->post('/admin/care-plans', [
            'client_id' => $this->client->client_id, 'name' => 'Website care', 'amount' => '950',
            'interval' => 'monthly', 'next_invoice_on' => '2026-10-01', 'auto_issue' => '1',
            'description' => "Hosting and SSL\nDaily backups",
        ])->assertSessionHasNoErrors();
        $this->artisan('portal:bill-care-plans');
        $this->travelTo(now()->setDate(2026, 10, 25));

        return CarePlan::sole();
    }

    public function test_plan_menu_can_be_managed_and_ships_with_starters(): void
    {
        $this->assertSame(3, CarePlanTemplate::count());

        $this->actingAs($this->admin)->post('/admin/plan-menu', ['name' => 'Email hosting', 'amount' => '150', 'interval' => 'monthly'])
            ->assertSessionHasNoErrors();
        $t = CarePlanTemplate::where('name', 'Email hosting')->sole();
        $this->assertSame(15000, $t->amount_cents);

        $this->put("/admin/plan-menu/{$t->id}", ['name' => 'Email hosting', 'amount' => '0', 'interval' => 'monthly'])->assertSessionHasErrors('amount');
        $this->put("/admin/plan-menu/{$t->id}", ['name' => 'Email hosting', 'amount' => '175', 'interval' => 'yearly'])->assertSessionHasNoErrors();
        $this->assertSame('yearly', $t->fresh()->interval);

        $this->get('/admin/plan-menu')->assertOk()->assertSee('Email hosting');
        $this->get('/admin/care-plans')->assertOk()->assertSee('Start from')->assertSee('Email hosting');

        $this->delete("/admin/plan-menu/{$t->id}")->assertRedirect();
        $this->assertSame(3, CarePlanTemplate::count());

        $this->actingAs($this->client)->get('/admin/plan-menu')->assertNotFound();
    }

    public function test_each_subscription_gets_a_fixed_payment_reference(): void
    {
        $plan = $this->planWithOverdueBill();
        $this->assertSame('CARE-0001', $plan->reference);
        $this->assertSame(['Hosting and SSL', 'Daily backups'], $plan->includedItems());

        $second = new CarePlan(['name' => 'Hosting', 'amount_cents' => 35000, 'interval' => 'monthly', 'next_invoice_on' => '2026-12-01']);
        $second->client_id = $this->client->client_id;
        $second->status = 'active';
        $second->save();
        $this->assertSame('CARE-0002', $second->reference);
    }

    public function test_admin_sees_who_is_behind_on_a_subscription(): void
    {
        $plan = $this->planWithOverdueBill();
        $this->assertSame(['overdue', '1 overdue'], CarePlan::withBillCounts()->sole()->paymentState());

        $this->actingAs($this->admin)->get('/admin/care-plans')->assertOk()
            ->assertSee('Billing')->assertSee('Kasi Threads')->assertSee('1 overdue')->assertSee('R1 092.50');
        $this->get("/admin/care-plans/{$plan->id}")->assertOk()
            ->assertSee('CARE-0001')->assertSee('Daily backups')->assertSee('October 2026');
        $this->get("/admin/care-plans?edit={$plan->id}")->assertRedirect("/admin/care-plans/{$plan->id}");

        // Once paid, the plan shows as paid up.
        $bill = Invoice::sole();
        $this->post("/admin/invoices/{$bill->id}/payments", ['amount' => '1092.50', 'method' => 'eft', 'paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertSame(['paid', 'Paid up'], CarePlan::withBillCounts()->sole()->paymentState());
    }

    public function test_all_bills_opens_on_unpaid_and_filters_by_kind(): void
    {
        $this->planWithOverdueBill();
        $this->actingAs($this->admin)->post('/admin/invoices', ['client_id' => $this->client->client_id]);
        $draft = Invoice::where('status', 'draft')->sole();
        $draft->update(['title' => 'Logo design']);

        $this->get('/admin/invoices')->assertOk()->assertSee('Website care, October 2026')->assertDontSee('Logo design');
        $this->get('/admin/invoices?status=draft')->assertSee('Logo design')->assertDontSee('Website care, October 2026');
        $this->get('/admin/invoices?status=all&kind=project')->assertSee('Logo design')->assertDontSee('Website care, October 2026');
        $this->get('/admin/invoices?status=all&kind=plan')->assertSee('Website care, October 2026')->assertDontSee('Logo design');
        $this->get('/admin/invoices?q=Logo')->assertSee('Logo design');
    }

    public function test_client_billing_page_shows_subscriptions_and_bills_together(): void
    {
        $this->planWithOverdueBill();

        $this->actingAs($this->client)->get('/invoices')->assertOk()
            ->assertSee('Your subscriptions')->assertSee('Website care')->assertSee('CARE-0001')
            ->assertSee('Daily backups')->assertSee('1 overdue')->assertSee('Website care, October 2026')
            ->assertSee('1 bill is overdue');
    }
}
