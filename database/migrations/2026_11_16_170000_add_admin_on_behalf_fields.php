<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Contact details for clients the admin adds, who may never log in.
        Schema::table('clients', function (Blueprint $table) {
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable()->index();
            $table->text('address')->nullable();
            $table->text('notes')->nullable();
        });

        // Users invited by the admin sign in with email links until they choose a password.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('has_password')->default(true);
        });

        // How the request reached us: the portal, or captured by the admin from a call, email, etc.
        Schema::table('service_requests', function (Blueprint $table) {
            $table->string('source', 20)->default('portal');
        });

        // Acceptance the admin records for a client (by email, WhatsApp, signed copy...).
        Schema::table('quote_acceptances', function (Blueprint $table) {
            $table->foreignId('recorded_by')->nullable()->constrained('users');
            $table->string('method', 30)->nullable();
            $table->string('note', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('quote_acceptances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recorded_by');
            $table->dropColumn(['method', 'note']);
        });
        Schema::table('service_requests', fn (Blueprint $table) => $table->dropColumn('source'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('has_password'));
        Schema::table('clients', fn (Blueprint $table) => $table->dropColumn(['contact_name', 'email', 'address', 'notes']));
    }
};
