<?php

namespace Database\Seeders;

use App\Models\UserStatus;
use Illuminate\Database\Seeder;

class UserStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user_statuses = ['active', 'inactive'];

        foreach ($user_statuses as $user_status) {
            UserStatus::firstOrCreate(['label' => $user_status]);
        }
    }
}
