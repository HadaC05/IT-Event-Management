<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_attendance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('schedule_date');
            $table->time('morning_in_time')->nullable();
            $table->time('morning_out_time')->nullable();
            $table->time('afternoon_in_time')->nullable();
            $table->time('afternoon_out_time')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'schedule_date']);
        });

        DB::table('events')->orderBy('id')->each(function (object $event): void {
            $start = Carbon::parse($event->start_at)->startOfDay();
            $end = Carbon::parse($event->end_at)->startOfDay();
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $isFirstDay = $date->isSameDay($start);
                DB::table('event_attendance_schedules')->insert([
                    'event_id' => $event->id,
                    'schedule_date' => $date->toDateString(),
                    'morning_in_time' => $isFirstDay && $event->morning_in_at ? Carbon::parse($event->morning_in_at)->format('H:i:s') : null,
                    'morning_out_time' => $isFirstDay && $event->morning_out_at ? Carbon::parse($event->morning_out_at)->format('H:i:s') : null,
                    'afternoon_in_time' => $isFirstDay && $event->afternoon_in_at ? Carbon::parse($event->afternoon_in_at)->format('H:i:s') : null,
                    'afternoon_out_time' => $isFirstDay && $event->afternoon_out_at ? Carbon::parse($event->afternoon_out_at)->format('H:i:s') : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->date('attendance_date')->nullable()->after('user_id');
        });

        DB::table('attendances')->orderBy('id')->each(function (object $attendance): void {
            $eventDate = DB::table('events')->where('id', $attendance->event_id)->value('start_at');
            DB::table('attendances')->where('id', $attendance->id)->update([
                'attendance_date' => Carbon::parse($eventDate)->toDateString(),
            ]);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_event_id_user_id_unique');
            $table->unique(['event_id', 'user_id', 'attendance_date'], 'attendances_event_user_date_unique');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropUnique('attendances_event_user_date_unique');
            $table->dropColumn('attendance_date');
            $table->unique(['event_id', 'user_id']);
        });
        Schema::dropIfExists('event_attendance_schedules');
    }
};
