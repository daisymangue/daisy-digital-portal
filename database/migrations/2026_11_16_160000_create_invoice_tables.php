<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Assigned when the invoice is issued, so issued numbers run in sequence with no gaps.
            $table->string('number', 20)->nullable()->unique();
            $table->foreignUuid('client_id')->constrained('clients');
            $table->foreignUuid('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            // deposit | balance | full | custom
            $table->string('type', 20)->default('custom');
            // draft | issued | partially_paid | paid | cancelled   (overdue is worked out from the due date)
            $table->string('status', 20)->default('draft')->index();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('vat_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->unsignedBigInteger('paid_cents')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'status']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 30)->nullable();
            $table->unsignedBigInteger('unit_price_cents')->default(0);
            $table->unsignedBigInteger('line_total_cents')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices');
            $table->foreignUuid('client_id')->constrained('clients');
            $table->unsignedBigInteger('amount_cents');
            // eft | card | cash | other
            $table->string('method', 20)->default('eft');
            // pending (client says they paid) | confirmed (admin checked the bank) | rejected
            $table->string('status', 20)->default('pending')->index();
            $table->string('reference', 120)->nullable();
            $table->date('paid_on');
            $table->string('note', 500)->nullable();
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
