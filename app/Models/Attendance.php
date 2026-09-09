<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    public const ATTENDED_STATUSES = ['present', 'late'];

    public const STATUSES = ['present', 'late', 'absent', 'excused'];

    protected $fillable = [
        'event_id',
        'user_id',
        'attendance_date',
        'status',
        'checked_in_at',
        'morning_in_at',
        'morning_out_at',
        'afternoon_in_at',
        'afternoon_out_at',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'checked_in_at' => 'datetime',
            'morning_in_at' => 'datetime',
            'morning_out_at' => 'datetime',
            'afternoon_in_at' => 'datetime',
            'afternoon_out_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
