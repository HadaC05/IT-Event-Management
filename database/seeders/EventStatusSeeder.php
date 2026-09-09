<?php

namespace Database\Seeders;

use App\Models\EventStatus;
use Illuminate\Database\Seeder;

class EventStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $event_statuses = ['upcoming', 'ongoing', 'completed', 'archived'];

        foreach ($event_statuses as $event_status) {
            EventStatus::firstOrCreate(['label' => $event_status]);
        }
    }
}
