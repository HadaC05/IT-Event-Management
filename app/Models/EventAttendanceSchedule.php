<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventAttendanceSchedule extends Model
{
    protected $fillable = [
        'event_id',
        'schedule_date',
        'attendance_session_mode_id',
        'whole_day_in_time',
        'whole_day_out_time',
        'morning_in_time',
        'morning_out_time',
        'afternoon_in_time',
        'afternoon_out_time',
    ];

    protected function casts(): array
    {
        return ['schedule_date' => 'date'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendanceSessionMode(): BelongsTo
    {
        return $this->belongsTo(AttendanceSessionMode::class);
    }

    public function slots(): array
    {
        $wholeDay = $this->attendanceSessionMode?->code === AttendanceSessionMode::WHOLE_DAY;

        return collect([
            ['key' => 'morning_in', 'label' => $wholeDay ? 'Time in' : 'Morning time in', 'time' => $wholeDay ? $this->whole_day_in_time : $this->morning_in_time],
            ['key' => 'morning_out', 'label' => $wholeDay ? 'Time out' : 'Morning time out', 'time' => $wholeDay ? $this->whole_day_out_time : $this->morning_out_time],
            ['key' => 'afternoon_in', 'label' => 'Afternoon time in', 'time' => $this->afternoon_in_time],
            ['key' => 'afternoon_out', 'label' => 'Afternoon time out', 'time' => $this->afternoon_out_time],
        ])->filter(fn (array $slot) => filled($slot['time']))
            ->map(fn (array $slot) => [
                'key' => $slot['key'],
                'label' => $slot['label'],
                'date' => $this->schedule_date,
                'at' => Carbon::parse($this->schedule_date->toDateString().' '.$slot['time']),
            ])->values()->all();
    }
}
