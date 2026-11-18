<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The set menu of care plans. Picking one fills in a new client's plan, which can still be adjusted.
        Schema::create('care_plan_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->string('interval', 20)->default('monthly');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // A fixed payment reference per plan, so a client can set up a monthly EFT or stop order.
        Schema::table('care_plans', function (Blueprint $table) {
            $table->string('reference', 20)->nullable()->unique()->after('client_id');
        });

        $n = 0;
        foreach (DB::table('care_plans')->orderBy('id')->pluck('id') as $id) {
            DB::table('care_plans')->where('id', $id)->update(['reference' => 'CARE-'.str_pad((string) ++$n, 4, '0', STR_PAD_LEFT)]);
        }
        if ($n > 0) {
            DB::table('counters')->updateOrInsert(['key' => 'care_plan'], ['value' => $n]);
        }

        $now = now();
        DB::table('care_plan_templates')->insert([
            ['name' => 'Basic hosting', 'description' => "Hosting and SSL\nDaily backups\nSecurity updates", 'amount_cents' => 35000, 'interval' => 'monthly', 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Website care', 'description' => "Hosting and SSL\nDaily backups\nSecurity updates\nUp to 3 small changes a month", 'amount_cents' => 95000, 'interval' => 'monthly', 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Social media', 'description' => "12 posts a month\nCaptions and scheduling\nMonthly report", 'amount_cents' => 250000, 'interval' => 'monthly', 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('care_plans', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn('reference');
        });
        Schema::dropIfExists('care_plan_templates');
    }
};
