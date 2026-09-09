<?php

namespace Database\Seeders;

use App\Models\AttendanceSessionMode;
use Illuminate\Database\Seeder;

class AttendanceSessionModeSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => AttendanceSessionMode::NONE, 'name' => 'No attendance scanning'],
            ['code' => AttendanceSessionMode::WHOLE_DAY, 'name' => 'Whole day — one sign in and sign out'],
            ['code' => AttendanceSessionMode::TWO_SESSIONS, 'name' => 'Morning and afternoon — two sign-ins and sign-outs'],
        ] as $mode) {
            AttendanceSessionMode::updateOrCreate(['code' => $mode['code']], $mode);
        }
    }
}
