<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A special request is for work that is not in the packages or service list.
        Schema::table('service_requests', function (Blueprint $table) {
            $table->text('special_details')->nullable();
            $table->string('budget_range', 40)->nullable();
            $table->date('needed_by')->nullable();
        });

        // Featured packages get a "Most popular" badge and stand out on the request page.
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('packages', fn (Blueprint $table) => $table->dropColumn('is_featured'));
        Schema::table('service_requests', fn (Blueprint $table) => $table->dropColumn(['special_details', 'budget_range', 'needed_by']));
    }
};
