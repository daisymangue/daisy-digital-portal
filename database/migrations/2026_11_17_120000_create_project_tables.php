<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();
            $table->foreignUuid('client_id')->constrained('clients');
            $table->foreignUuid('quote_id')->nullable()->constrained('quotes')->nullOnDelete();
            $table->foreignUuid('service_request_id')->nullable()->constrained('service_requests')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            // active | awaiting_client | on_hold | completed | cancelled
            $table->string('status', 20)->default('active')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            // Why it was started without a paid deposit, when the admin waived it.
            $table->string('start_reason', 500)->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'status']);
        });

        Schema::create('project_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title');
            $table->date('due_date')->nullable();
            // pending | in_progress | awaiting_approval | changes_requested | done
            $table->string('status', 30)->default('pending');
            $table->boolean('needs_approval')->default(false);
            $table->string('approved_name')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('client_feedback')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('project_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->boolean('from_team')->default(false);
            $table->text('body')->nullable();
            // Read by the other side: the client reading the team's messages, or the team reading the client's.
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'id']);
        });

        // "What we need from you": things the client must supply before work can continue.
        Schema::create('project_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title');
            $table->string('note', 500)->nullable();
            // open | submitted | accepted
            $table->string('status', 20)->default('open');
            $table->text('client_reply')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['project_checklist_items', 'project_messages', 'project_updates', 'project_milestones', 'projects'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
