<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_session_modes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->timestamps();
        });

        DB::table('attendance_session_modes')->insert([
            ['code' => 'none', 'name' => 'No attendance scanning', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'whole_day', 'name' => 'Whole day — one sign in and sign out', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'two_sessions', 'name' => 'Morning and afternoon — two sign-ins and sign-outs', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Schema::table('event_attendance_schedules', function (Blueprint $table) {
            $table->foreignId('attendance_session_mode_id')->nullable()->after('schedule_date')->constrained()->restrictOnDelete();
            $table->time('whole_day_in_time')->nullable()->after('attendance_session_mode_id');
            $table->time('whole_day_out_time')->nullable()->after('whole_day_in_time');
        });

        $modes = DB::table('attendance_session_modes')->pluck('id', 'code');
        DB::table('event_attendance_schedules')->orderBy('id')->each(function (object $schedule) use ($modes): void {
            $mode = $schedule->session_mode === 'split' ? 'two_sessions' : ($schedule->session_mode === 'single' ? 'whole_day' : 'none');
            $update = ['attendance_session_mode_id' => $modes[$mode]];

            if ($mode === 'whole_day') {
                $update['whole_day_in_time'] = $schedule->morning_in_time;
                $update['whole_day_out_time'] = $schedule->morning_out_time;
                $update['morning_in_time'] = null;
                $update['morning_out_time'] = null;
            }

            DB::table('event_attendance_schedules')->where('id', $schedule->id)->update($update);
        });

        Schema::table('event_attendance_schedules', function (Blueprint $table) {
            $table->foreignId('attendance_session_mode_id')->nullable(false)->change();
            $table->dropColumn('session_mode');
        });
    }

    public function down(): void
    {
        Schema::table('event_attendance_schedules', function (Blueprint $table) {
            $table->string('session_mode', 12)->default('none')->after('schedule_date');
        });

        $modes = DB::table('attendance_session_modes')->pluck('code', 'id');
        DB::table('event_attendance_schedules')->orderBy('id')->each(function (object $schedule) use ($modes): void {
            $mode = $modes[$schedule->attendance_session_mode_id] ?? 'none';
            DB::table('event_attendance_schedules')->where('id', $schedule->id)->update([
                'session_mode' => $mode === 'two_sessions' ? 'split' : ($mode === 'whole_day' ? 'single' : 'none'),
                'morning_in_time' => $mode === 'whole_day' ? $schedule->whole_day_in_time : $schedule->morning_in_time,
                'morning_out_time' => $mode === 'whole_day' ? $schedule->whole_day_out_time : $schedule->morning_out_time,
            ]);
        });

        Schema::table('event_attendance_schedules', function (Blueprint $table) {
            $table->dropForeign(['attendance_session_mode_id']);
            $table->dropColumn(['attendance_session_mode_id', 'whole_day_in_time', 'whole_day_out_time']);
        });
        Schema::dropIfExists('attendance_session_modes');
    }
};
