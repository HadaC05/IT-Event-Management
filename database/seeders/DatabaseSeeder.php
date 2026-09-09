<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserStatusSeeder::class,
            AttendanceSessionModeSeeder::class,
            YearLevelSeeder::class,
            SchoolYearSeeder::class,
            EventTypeSeeder::class,
            EventStatusSeeder::class,
            LocationSeeder::class,
            StudentSeeder::class,
        ]);

        User::updateOrCreate(['email' => 'test@example.com'], [
            'id_number' => '00000001',
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'testuser',
            'password' => Hash::make('password'),
        ]);

        User::updateOrCreate(['email' => 'adviser@itevents.local'], [
            'id_number' => 'SBO-ADV-001',
            'first_name' => 'SBO',
            'middle_name' => null,
            'last_name' => 'Adviser',
            'username' => 'sbo.adviser',
            'password' => Hash::make('SBOAdviser@2026'),
            'role_id' => Role::where('name', 'SBO Adviser')->value('id'),
            'status' => UserStatus::where('label', 'active')->value('id'),
        ]);
    }
}
