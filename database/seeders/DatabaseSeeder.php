<?php

namespace Database\Seeders;

use App\Models\StepSet;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogueSeeder::class);
        StepSet::linkDefaults();
    }
}
