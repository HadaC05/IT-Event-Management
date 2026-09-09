<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_attendance_schedules', function (Blueprint $table) {
            $table->string('session_mode', 12)->default('none')->after('schedule_date');
        });

        DB::table('event_attendance_schedules')->whereNotNull('morning_in_time')->update(['session_mode' => 'single']);
        DB::table('event_attendance_schedules')->whereNotNull('afternoon_in_time')->update(['session_mode' => 'split']);
    }

    public function down(): void
    {
        Schema::table('event_attendance_schedules', function (Blueprint $table) {
            $table->dropColumn('session_mode');
        });
    }
};
