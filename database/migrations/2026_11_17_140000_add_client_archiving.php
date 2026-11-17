<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Archived clients are hidden from lists and cannot sign in. Their records stay for the books.
            $table->timestamp('archived_at')->nullable()->index();
            $table->string('archived_reason', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn(['archived_at', 'archived_reason']));
    }
};
