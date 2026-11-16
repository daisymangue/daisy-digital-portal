<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\User;
use App\Notifications\InvoiceIssued;
use App\Notifications\PaymentReviewed;
use App\Notifications\PaymentSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceTest extends TestCase
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

    /** An accepted quote: R7 625.00 less R500 discount, 15% VAT = R8 193.75, 50% deposit. */
    private function acceptedQuote(): Quote
    {
        $this->actingAs($this->admin)->post('/admin/quotes', ['client_id' => $this->client->client_id]);
        $quote = Quote::orderByDesc('number')->firstOrFail();
        $this->actingAs($this->admin)->put("/admin/quotes/{$quote->id}", [
            'action' => 'send', 'title' => 'Online store', 'discount' => '500', 'vat_rate' => '15', 'deposit_percent' => '50',
            'valid_until' => now()->addDays(30)->toDateString(),
            'items' => [
                ['description' => 'Online Store Setup', 'quantity' => '1', 'price' => '6500'],
                ['description' => 'Content upload', 'quantity' => '2.5', 'unit' => 'hour', 'price' => '450'],
            ],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->client)->post("/quotes/{$quote->id}/accept", ['accepted_name' => 'Lerato Mokoena', 'confirm' => '1'])->assertSessionHasNoErrors();

        return $quote->fresh();
    }

    private function invoiceFromQuote(Quote $quote, string $type, bool $issue = true): Invoice
    {
        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/invoices", ['type' => $type])->assertSessionHasNoErrors();
        $invoice = Invoice::where('quote_id', $quote->id)->where('type', $type)->where('status', 'draft')->firstOrFail();
        if ($issue) {
            $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/issue")->assertSessionHasNoErrors();
        }

        return $invoice->fresh();
    }

    private function proof(Invoice $invoice, string $amount)
    {
        return $this->actingAs($this->client)->post("/invoices/{$invoice->id}/pay", [
            'amount' => $amount, 'paid_on' => today()->toDateString(),
            'proof' => UploadedFile::fake()->create('proof.pdf', 80, 'application/pdf'),
        ]);
    }

    public function test_deposit_and_balance_invoices_add_up_to_the_quote_exactly(): void
    {
        $quote = $this->acceptedQuote();
        $this->assertSame(819375, $quote->total_cents);

        $deposit = $this->invoiceFromQuote($quote, 'deposit');
        $this->assertSame(409688, $deposit->total_cents);
        $this->assertSame($deposit->total_cents, $deposit->subtotal_cents + $deposit->vat_cents);
        $this->assertSame('INV-0001', $deposit->number);
        $this->assertSame('issued', $deposit->status);
        Notification::assertSentTo($this->client, InvoiceIssued::class);

        $balance = $this->invoiceFromQuote($quote, 'balance');
        $this->assertSame(409687, $balance->total_cents);
        $this->assertSame('INV-0002', $balance->number);
        $this->assertSame($quote->total_cents, $deposit->total_cents + $balance->total_cents);

        // Nothing left to invoice.
        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/invoices", ['type' => 'balance'])->assertSessionHasErrors('invoice');
        $this->assertSame(2, Invoice::count());
    }

    public function test_full_invoice_copies_the_quote_and_blocks_a_second_one(): void
    {
        $quote = $this->acceptedQuote();
        $full = $this->invoiceFromQuote($quote, 'full');

        $this->assertSame([762500, 50000, 106875, 819375], [$full->subtotal_cents, $full->discount_cents, $full->vat_cents, $full->total_cents]);
        $this->assertSame(2, $full->items()->count());
        $this->actingAs($this->admin)->post("/admin/quotes/{$quote->id}/invoices", ['type' => 'deposit'])->assertSessionHasErrors('invoice');
    }

    public function test_invoices_need_an_accepted_quote(): void
    {
        $this->actingAs($this->admin)->post('/admin/quotes', ['client_id' => $this->client->client_id]);
        $draft = Quote::firstOrFail();
        $this->actingAs($this->admin)->post("/admin/quotes/{$draft->id}/invoices", ['type' => 'full'])->assertSessionHasErrors('invoice');
        $this->assertSame(0, Invoice::count());
    }

    public function test_draft_invoices_are_hidden_and_numbers_are_assigned_on_issue(): void
    {
        $quote = $this->acceptedQuote();
        $draft = $this->invoiceFromQuote($quote, 'deposit', false);

        $this->assertNull($draft->number);
        $this->actingAs($this->client)->get("/invoices/{$draft->id}")->assertNotFound();
        $this->actingAs($this->client)->get('/invoices')->assertOk()->assertDontSee('Online store');

        $this->actingAs($this->admin)->post("/admin/invoices/{$draft->id}/issue");
        $this->actingAs($this->client)->get("/invoices/{$draft->id}")->assertOk()->assertSee('INV-0001')->assertSee('R4 096.88');
        $this->actingAs($this->client)->get('/dashboard')->assertOk()->assertSee('INV-0001');

        // Issued invoices are locked.
        $this->actingAs($this->admin)->put("/admin/invoices/{$draft->id}", ['title' => 'Changed', 'due_date' => today()->toDateString()])->assertStatus(409);
        $this->actingAs($this->admin)->delete("/admin/invoices/{$draft->id}")->assertStatus(409);
    }

    public function test_proof_of_payment_does_not_mark_the_invoice_paid_until_admin_confirms(): void
    {
        $invoice = $this->invoiceFromQuote($this->acceptedQuote(), 'deposit');

        $this->proof($invoice, '4096.88')->assertSessionHasNoErrors();
        $payment = Payment::firstOrFail();

        $this->assertSame('pending', $payment->status);
        $this->assertSame(['issued', 0], [$invoice->fresh()->status, $invoice->fresh()->paid_cents]);
        $this->assertCount(1, $payment->files);
        Notification::assertSentTo($this->admin, PaymentSubmitted::class);

        // A second claim for the same money is refused while the first is being checked.
        $this->proof($invoice, '100')->assertSessionHasErrors('amount');

        // A client cannot confirm their own payment.
        $this->actingAs($this->client)->post("/admin/payments/{$payment->id}/confirm")->assertNotFound();
        $this->assertSame('pending', $payment->fresh()->status);

        $this->actingAs($this->admin)->post("/admin/payments/{$payment->id}/confirm")->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame(['paid', 409688, 0], [$invoice->status, $invoice->paid_cents, $invoice->balanceCents()]);
        $this->assertNotNull($invoice->paid_at);
        Notification::assertSentTo($this->client, PaymentReviewed::class);

        // Already reviewed, and the invoice is settled.
        $this->actingAs($this->admin)->post("/admin/payments/{$payment->id}/confirm")->assertStatus(409);
        $this->proof($invoice, '10')->assertSessionHasErrors('amount');
    }

    public function test_partial_payments_rejections_and_overpayment_limits(): void
    {
        $invoice = $this->invoiceFromQuote($this->acceptedQuote(), 'full');

        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/payments", ['amount' => '3 000', 'method' => 'eft', 'paid_on' => today()->toDateString()])->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame(['partially_paid', 300000, 519375], [$invoice->status, $invoice->paid_cents, $invoice->balanceCents()]);

        // More than the balance is refused, for admin and client alike.
        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/payments", ['amount' => '6000', 'method' => 'eft', 'paid_on' => today()->toDateString()])->assertSessionHasErrors('amount');
        $this->proof($invoice, '6000')->assertSessionHasErrors('amount');

        $this->proof($invoice, '5193.75')->assertSessionHasNoErrors();
        $pending = Payment::where('status', 'pending')->firstOrFail();
        $this->actingAs($this->admin)->post("/admin/payments/{$pending->id}/reject", ['rejection_reason' => 'Nothing received yet'])->assertSessionHasNoErrors();
        $this->assertSame(['rejected', 'partially_paid'], [$pending->fresh()->status, $invoice->fresh()->status]);
        $this->actingAs($this->client)->get("/invoices/{$invoice->id}")->assertOk()->assertSee('Nothing received yet');

        // Admin can correct the amount when confirming.
        $this->proof($invoice, '5193.75');
        $second = Payment::where('status', 'pending')->firstOrFail();
        $this->actingAs($this->admin)->post("/admin/payments/{$second->id}/confirm", ['amount' => '5000']);
        $this->assertSame(['partially_paid', 800000, 19375], [$invoice->fresh()->status, $invoice->fresh()->paid_cents, $invoice->fresh()->balanceCents()]);
    }

    public function test_overdue_status_and_cancellation_rules(): void
    {
        $quote = $this->acceptedQuote();
        $invoice = $this->invoiceFromQuote($quote, 'deposit');

        $invoice->due_date = today()->subDays(3);
        $invoice->save();
        $this->assertSame('overdue', $invoice->effectiveStatus());
        $this->assertSame(1, Invoice::overdue()->count());
        $this->actingAs($this->admin)->get('/admin/invoices?status=overdue')->assertOk()->assertSee('INV-0001');

        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/cancel", ['cancel_reason' => ''])->assertSessionHasErrors('cancel_reason');
        $this->actingAs($this->admin)->post("/admin/invoices/{$invoice->id}/cancel", ['cancel_reason' => 'Client changed scope'])->assertSessionHasNoErrors();
        $this->assertSame(['cancelled', 'INV-0001'], [$invoice->fresh()->status, $invoice->fresh()->number]);

        // A cancelled invoice frees the quote to be invoiced again, and the next number continues.
        $again = $this->invoiceFromQuote($quote, 'deposit');
        $this->assertSame('INV-0002', $again->number);

        // An invoice with money against it cannot be cancelled.
        $this->actingAs($this->admin)->post("/admin/invoices/{$again->id}/payments", ['amount' => '100', 'method' => 'cash', 'paid_on' => today()->toDateString()]);
        $this->actingAs($this->admin)->post("/admin/invoices/{$again->id}/cancel", ['cancel_reason' => 'Mistake'])->assertSessionHasErrors('invoice');
    }

    public function test_manual_invoice_is_calculated_on_the_server(): void
    {
        $this->actingAs($this->admin)->post('/admin/invoices', ['client_id' => $this->client->client_id])->assertRedirect();
        $invoice = Invoice::firstOrFail();
        $this->actingAs($this->admin)->get("/admin/invoices/{$invoice->id}/edit")->assertOk();

        $this->actingAs($this->admin)->put("/admin/invoices/{$invoice->id}", [
            'action' => 'issue', 'title' => 'Monthly website care', 'due_date' => today()->addDays(7)->toDateString(), 'vat_rate' => '15',
            'items' => [['description' => 'Website Care Package', 'quantity' => '1', 'unit' => 'month', 'price' => '750']],
        ])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame([75000, 11250, 86250, 'issued'], [$invoice->subtotal_cents, $invoice->vat_cents, $invoice->total_cents, $invoice->status]);

        $this->actingAs($this->admin)->get('/admin/invoices')->assertOk()->assertSee('Monthly website care');
        $this->actingAs($this->admin)->get("/admin/invoices/{$invoice->id}")->assertOk();
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSee('Outstanding invoices');
    }

    public function test_another_client_cannot_see_pay_or_download_an_invoice_or_its_proof(): void
    {
        $invoice = $this->invoiceFromQuote($this->acceptedQuote(), 'deposit');
        $this->proof($invoice, '1000');
        $file = Payment::firstOrFail()->files->first();
        $stranger = $this->makeClient('Other Business');

        $this->actingAs($stranger)->get("/invoices/{$invoice->id}")->assertNotFound();
        $this->actingAs($stranger)->get("/invoices/{$invoice->id}/pdf")->assertNotFound();
        $this->actingAs($stranger)->get("/files/{$file->id}")->assertNotFound();
        $this->actingAs($stranger)->post("/invoices/{$invoice->id}/pay", ['amount' => '1', 'paid_on' => today()->toDateString(), 'proof' => UploadedFile::fake()->create('p.pdf', 10, 'application/pdf')])->assertNotFound();
        $this->assertSame(1, Payment::count());

        $this->actingAs($this->admin)->get("/files/{$file->id}")->assertOk();
    }

    public function test_invoice_pdf_downloads_and_proof_must_be_an_image_or_pdf(): void
    {
        $invoice = $this->invoiceFromQuote($this->acceptedQuote(), 'deposit');

        $response = $this->actingAs($this->client)->get("/invoices/{$invoice->id}/pdf");
        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
        $this->actingAs($this->admin)->get("/admin/invoices/{$invoice->id}/pdf")->assertOk();

        $this->actingAs($this->client)->post("/invoices/{$invoice->id}/pay", ['amount' => '100', 'paid_on' => today()->toDateString()])->assertSessionHasErrors('proof');
        $this->actingAs($this->client)->post("/invoices/{$invoice->id}/pay", ['amount' => '100', 'paid_on' => today()->toDateString(), 'proof' => UploadedFile::fake()->create('proof.exe', 10, 'application/octet-stream')])->assertSessionHasErrors('proof');
        $this->assertSame(0, Payment::count());
    }
}
