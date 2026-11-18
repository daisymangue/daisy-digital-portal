<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->unsignedBigInteger('sale_price_cents')->nullable()->after('price_cents');
            $table->date('sale_ends_on')->nullable()->after('sale_price_cents');
        });

        // The price the client saw when they asked, so a sale that ends later is still honoured on the quote.
        Schema::table('service_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('package_price_cents')->nullable()->after('package_name');
            $table->unsignedBigInteger('package_was_cents')->nullable()->after('package_price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('packages', fn (Blueprint $t) => $t->dropColumn(['sale_price_cents', 'sale_ends_on']));
        Schema::table('service_requests', fn (Blueprint $t) => $t->dropColumn(['package_price_cents', 'package_was_cents']));
    }
};
