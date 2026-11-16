<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('business_name');
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('client')->index();
            $table->foreignUuid('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->string('phone')->nullable();
        });

        Schema::create('login_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // fixed | from | hourly | monthly | quote
            $table->string('pricing_type', 20)->default('quote');
            $table->unsignedBigInteger('price_cents')->nullable();
            $table->boolean('is_from')->default(false);
            $table->string('unit', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['service_category_id', 'is_active']);
        });

        Schema::create('category_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            // text | textarea | select | yesno
            $table->string('type', 20)->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('price_cents')->nullable();
            $table->boolean('is_from')->default(false);
            $table->string('unit', 30)->nullable();
            $table->json('includes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('counters', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('number', 20)->unique();
            $table->foreignUuid('client_id')->constrained('clients');
            $table->foreignId('user_id')->constrained();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('package_name')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('new')->index();
            $table->timestamp('submitted_at');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['client_id', 'created_at']);
        });

        // Snapshot of each chosen service, so later price edits never change an old request.
        Schema::create('request_services', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('service_request_id')->constrained('service_requests')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name');
            $table->string('name');
            $table->string('pricing_type', 20);
            $table->unsignedBigInteger('price_cents')->nullable();
            $table->boolean('is_from')->default(false);
            $table->string('unit', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('request_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('service_request_id')->constrained('service_requests')->cascadeOnDelete();
            $table->foreignId('category_question_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name');
            $table->string('question');
            $table->text('answer');
            $table->timestamps();
        });

        // Internal notes. Never shown to clients.
        Schema::create('request_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('service_request_id')->constrained('service_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('client_id')->constrained('clients');
            $table->uuidMorphs('fileable');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('original_name');
            $table->string('path');
            $table->string('mime', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80)->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 36)->nullable();
            $table->json('meta')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'files', 'request_notes', 'request_answers', 'request_services', 'service_requests', 'counters', 'packages', 'category_questions', 'services', 'service_categories', 'login_tokens'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropColumn(['role', 'phone']);
        });
        Schema::dropIfExists('clients');
    }
};
