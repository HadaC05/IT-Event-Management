<?php

namespace Database\Seeders;

use App\Models\YearLevel;
use Illuminate\Database\Seeder;

class YearLevelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $year_levels = ['First Year', 'Second Year', 'Third Year', 'Fourth Year'];

        foreach ($year_levels as $year_level) {
            YearLevel::firstOrCreate(['label' => $year_level]);
        }
    }
}
