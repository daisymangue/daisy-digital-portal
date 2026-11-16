<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // A revised quote keeps its number and gets the next version: Q-0001 v2.
            $table->string('number', 20);
            $table->unsignedInteger('version')->default(1);
            $table->foreignUuid('client_id')->constrained('clients');
            $table->foreignUuid('service_request_id')->nullable()->constrained('service_requests')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            // draft | sent | changes_requested | accepted | declined | expired | superseded
            $table->string('status', 30)->default('draft')->index();
            $table->string('title');
            $table->text('scope')->nullable();
            $table->string('timeline')->nullable();
            $table->text('terms')->nullable();
            $table->unsignedBigInteger('subtotal_cents')->default(0);
            $table->unsignedBigInteger('discount_cents')->default(0);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->unsignedBigInteger('vat_cents')->default(0);
            $table->unsignedBigInteger('total_cents')->default(0);
            $table->decimal('deposit_percent', 5, 2)->default(0);
            $table->date('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('client_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['number', 'version']);
            $table->index(['client_id', 'status']);
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 30)->nullable();
            $table->unsignedBigInteger('unit_price_cents')->default(0);
            $table->unsignedBigInteger('line_total_cents')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Proof of what the client agreed to, frozen at the moment they accepted.
        Schema::create('quote_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('quote_id')->unique()->constrained('quotes');
            $table->foreignId('user_id')->constrained();
            $table->string('accepted_name');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('snapshot');
            $table->timestamp('accepted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quote_acceptances');
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('settings');
    }
};
