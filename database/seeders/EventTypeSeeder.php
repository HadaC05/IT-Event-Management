<?php

namespace Database\Seeders;

use App\Models\EventTypes;
use Illuminate\Database\Seeder;

class EventTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $event_types = ['IT Days', 'IT Expo'];

        foreach ($event_types as $event_type) {
            EventTypes::firstOrCreate(['label' => $event_type]);
        }
    }
}
