<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Popular services appear as one-tap quick picks on the request page.
        Schema::table('services', function (Blueprint $table) {
            $table->boolean('is_popular')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('services', fn (Blueprint $table) => $table->dropColumn('is_popular'));
    }
};
