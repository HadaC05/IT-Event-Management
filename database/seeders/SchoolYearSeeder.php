<?php

namespace Database\Seeders;

use App\Models\SchoolYear;
use Illuminate\Database\Seeder;

class SchoolYearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school_years = ['2026'];

        foreach ($school_years as $school_year) {
            SchoolYear::firstOrCreate(['label' => $school_year]);
        }
    }
}
