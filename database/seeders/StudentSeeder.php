<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\UserStatus;
use App\Models\YearLevel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $studentRoleId = Role::where('name', 'Student')->value('id');
        $activeStatusId = UserStatus::where('label', 'active')->value('id');

        foreach (YearLevel::orderBy('id')->get()->values() as $yearIndex => $yearLevel) {
            for ($studentIndex = 1; $studentIndex <= 3; $studentIndex++) {
                $sequence = ($yearIndex * 3) + $studentIndex;
                $studentId = sprintf('02-2026-%06d', $sequence);
                User::updateOrCreate(['id_number' => $studentId], [
                    'first_name' => 'Student',
                    'middle_name' => null,
                    'last_name' => $yearLevel->label.' '.$studentIndex,
                    'email' => "student{$sequence}@cite.local",
                    'username' => "student.{$sequence}",
                    'password' => Hash::make('Student@2026'),
                    'role_id' => $studentRoleId,
                    'year_level' => $yearLevel->id,
                    'status' => $activeStatusId,
                    'must_change_password' => false,
                ]);
            }
        }
    }
}
