<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extra work asked for during a project. Either quoted separately, included free, or declined.
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('title');
            $table->text('details');
            // new | quoted | included | declined | done
            $table->string('status', 20)->default('new');
            $table->foreignUuid('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->text('team_reply')->nullable();
            $table->timestamps();
        });

        // Monthly (or other) retainers: hosting, website care, social media management.
        Schema::create('care_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('client_id')->constrained('clients');
            $table->string('name');
            $table->text('description')->nullable();
            // Excluding VAT. VAT is added on each invoice at the current rate.
            $table->unsignedBigInteger('amount_cents');
            // monthly | quarterly | yearly
            $table->string('interval', 20)->default('monthly');
            $table->date('next_invoice_on');
            // active | paused | cancelled
            $table->string('status', 20)->default('active')->index();
            // Issue and email the invoice by itself, or leave a draft for the admin to check.
            $table->boolean('auto_issue')->default(true);
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('care_plan_id')->nullable()->after('quote_id')->constrained('care_plans')->nullOnDelete();
            $table->date('period_start')->nullable()->after('due_date');
            $table->unsignedTinyInteger('reminders_sent')->default(0)->after('cancel_reason');
            $table->timestamp('last_reminded_at')->nullable()->after('reminders_sent');
            $table->unique(['care_plan_id', 'period_start']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->text('handover_notes')->nullable()->after('completed_at');
            $table->timestamp('handover_sent_at')->nullable()->after('handover_notes');
            $table->timestamp('handover_confirmed_at')->nullable()->after('handover_sent_at');
            $table->string('handover_confirmed_name')->nullable()->after('handover_confirmed_at');
        });

        // Files can be grouped, for example the handover pack.
        Schema::table('files', function (Blueprint $table) {
            $table->string('collection', 30)->nullable()->after('fileable_id');
        });

        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('client_id')->constrained('clients');
            $table->foreignUuid('project_id')->nullable()->unique()->constrained('projects')->nullOnDelete();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('body')->nullable();
            $table->string('display_name')->nullable();
            $table->boolean('can_publish')->default(false);
            // requested | received | featured
            $table->string('status', 20)->default('requested');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
        Schema::table('files', fn (Blueprint $t) => $t->dropColumn('collection'));
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn(['handover_notes', 'handover_sent_at', 'handover_confirmed_at', 'handover_confirmed_name']));
        Schema::table('invoices', function (Blueprint $t) {
            $t->dropUnique(['care_plan_id', 'period_start']);
            $t->dropConstrainedForeignId('care_plan_id');
            $t->dropColumn(['period_start', 'reminders_sent', 'last_reminded_at']);
        });
        Schema::dropIfExists('care_plans');
        Schema::dropIfExists('change_requests');
    }
};
