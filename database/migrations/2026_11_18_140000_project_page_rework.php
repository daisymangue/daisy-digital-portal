<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ready-made lists of project steps. Each category and package points at the set that fits it.
        Schema::create('step_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // [{"title": "First design", "approval": true}, ...]
            $table->json('steps');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('service_categories', function (Blueprint $table) {
            $table->foreignId('step_set_id')->nullable()->constrained('step_sets')->nullOnDelete();
        });
        Schema::table('packages', function (Blueprint $table) {
            $table->foreignId('step_set_id')->nullable()->constrained('step_sets')->nullOnDelete();
        });

        // A "what we need" item belongs to the step it holds up.
        Schema::table('project_checklist_items', function (Blueprint $table) {
            $table->foreignId('milestone_id')->nullable()->constrained('project_milestones')->nullOnDelete();
        });

        // Progress updates now live in the chat, as highlighted messages.
        Schema::table('project_messages', function (Blueprint $table) {
            $table->string('kind', 20)->default('message');
        });

        foreach (DB::table('project_updates')->orderBy('id')->get() as $u) {
            DB::table('project_messages')->insert([
                'project_id' => $u->project_id, 'user_id' => $u->user_id, 'from_team' => true, 'body' => $u->body,
                'kind' => 'update', 'read_at' => $u->created_at, 'created_at' => $u->created_at, 'updated_at' => $u->updated_at,
            ]);
        }

        $sets = [
            'website' => ['Website', [['Kick-off and content', false], ['First design', true], ['Build', false], ['Final review', true], ['Launch and handover', false]]],
            'design' => ['Logo and design', [['Brief', false], ['First ideas', true], ['Refine', false], ['Final files', true]]],
            'marketing' => ['Social media and marketing', [['Plan the month', true], ['Create the posts', true], ['Schedule and post', false], ['Monthly report', false]]],
            'quick' => ['Quick job', [['Got everything we need', false], ['Working on it', false], ['Ready for you', true]]],
        ];
        $ids = [];
        $n = 0;
        foreach ($sets as $key => [$name, $steps]) {
            $ids[$key] = DB::table('step_sets')->insertGetId([
                'name' => $name, 'sort_order' => ++$n, 'created_at' => now(), 'updated_at' => now(),
                'steps' => json_encode(array_map(fn ($s) => ['title' => $s[0], 'approval' => $s[1]], $steps)),
            ]);
        }

        // Sensible starting links. The admin can change any of them.
        foreach (DB::table('service_categories')->get(['id', 'name']) as $c) {
            $key = match (true) {
                str_contains($c->name, 'Website Design') => 'website',
                str_contains($c->name, 'Graphic Design') => 'design',
                str_contains($c->name, 'Marketing') => 'marketing',
                default => 'quick',
            };
            DB::table('service_categories')->where('id', $c->id)->update(['step_set_id' => $ids[$key]]);
        }
        foreach (DB::table('packages')->get(['id', 'name']) as $p) {
            $key = match (true) {
                str_contains($p->name, 'Marketing') => 'marketing',
                str_contains($p->name, 'Graphic Design') => 'design',
                preg_match('/Starter|Essentials|Growth|Launch/', $p->name) === 1 => 'website',
                default => 'quick',
            };
            DB::table('packages')->where('id', $p->id)->update(['step_set_id' => $ids[$key]]);
        }
    }

    public function down(): void
    {
        DB::table('project_messages')->where('kind', 'update')->delete();
        Schema::table('project_messages', fn (Blueprint $t) => $t->dropColumn('kind'));
        Schema::table('project_checklist_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('milestone_id');
        });
        Schema::table('packages', function (Blueprint $t) {
            $t->dropConstrainedForeignId('step_set_id');
        });
        Schema::table('service_categories', function (Blueprint $t) {
            $t->dropConstrainedForeignId('step_set_id');
        });
        Schema::dropIfExists('step_sets');
    }
};
