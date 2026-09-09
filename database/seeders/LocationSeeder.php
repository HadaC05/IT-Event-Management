<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'PHINMA COC Carmen Campus', 'type' => Location::GENERAL],
            ['name' => 'MS Computer Lab 1', 'type' => Location::SPECIFIC],
            ['name' => 'PH 310', 'type' => Location::SPECIFIC],
        ] as $location) {
            Location::updateOrCreate(['name' => $location['name']], $location);
        }
    }
}
