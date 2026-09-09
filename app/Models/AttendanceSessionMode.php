<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSessionMode extends Model
{
    public const NONE = 'none';

    public const WHOLE_DAY = 'whole_day';

    public const TWO_SESSIONS = 'two_sessions';

    protected $fillable = ['code', 'name'];

    public function attendanceSchedules(): HasMany
    {
        return $this->hasMany(EventAttendanceSchedule::class);
    }
}
