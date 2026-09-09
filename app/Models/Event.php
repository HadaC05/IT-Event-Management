<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'location',
        'audience_type',
        'poster_path',
        'start_at',
        'end_at',
        'morning_in_at',
        'morning_out_at',
        'afternoon_in_at',
        'afternoon_out_at',
        'event_type_id',
        'event_status_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'morning_in_at' => 'datetime',
            'morning_out_at' => 'datetime',
            'afternoon_in_at' => 'datetime',
            'afternoon_out_at' => 'datetime',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(EventTypes::class, 'event_type_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(EventStatus::class, 'event_status_id');
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function audienceTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'event_team')->withTimestamps();
    }

    public function audienceYearLevels(): BelongsToMany
    {
        return $this->belongsToMany(YearLevel::class, 'event_year_level')->withTimestamps();
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_participants')->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceSchedules(): HasMany
    {
        return $this->hasMany(EventAttendanceSchedule::class)->orderBy('schedule_date');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }

    public function scoreCategories(): HasMany
    {
        return $this->hasMany(ScoreCategory::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withDefault();
    }

    public function expectedParticipantsQuery(): Builder
    {
        $studentRoleId = Role::where('name', 'Student')->value('id');
        $students = User::query()
            ->where('role_id', $studentRoleId)
            ->whereHas('userStatus', fn ($query) => $query->where('label', 'active'));

        return match ($this->audience_type) {
            'selected_tribes' => $students->whereHas('teams', fn ($query) => $query->whereIn('teams.id', $this->audienceTeams()->pluck('teams.id'))),
            'selected_year_levels' => $students->whereIn('year_level', $this->audienceYearLevels()->pluck('year_levels.id')),
            'specific_students' => $students->whereIn('id', $this->participants()->pluck('users.id')),
            default => $students,
        };
    }

    public function expectedParticipants(): Collection
    {
        return $this->expectedParticipantsQuery()->get();
    }

    public function getScheduleStateAttribute(): string
    {
        if ($this->trashed()) {
            return 'archived';
        }

        if ($this->start_at->isFuture()) {
            return 'upcoming';
        }

        if ($this->end_at->isPast()) {
            return 'completed';
        }

        return 'ongoing';
    }

    public function attendanceSlots($date = null): array
    {
        $date = $date ? Carbon::parse($date) : now();
        $schedule = $this->attendanceSchedules->first(
            fn (EventAttendanceSchedule $schedule) => $schedule->schedule_date->isSameDay($date)
        );

        if ($schedule) {
            return $schedule->slots();
        }

        return collect([
            ['key' => 'morning_in', 'label' => 'Morning time in', 'date' => $this->morning_in_at?->copy()->startOfDay(), 'at' => $this->morning_in_at],
            ['key' => 'morning_out', 'label' => 'Morning time out', 'date' => $this->morning_out_at?->copy()->startOfDay(), 'at' => $this->morning_out_at],
            ['key' => 'afternoon_in', 'label' => 'Afternoon time in', 'date' => $this->afternoon_in_at?->copy()->startOfDay(), 'at' => $this->afternoon_in_at],
            ['key' => 'afternoon_out', 'label' => 'Afternoon time out', 'date' => $this->afternoon_out_at?->copy()->startOfDay(), 'at' => $this->afternoon_out_at],
        ])->filter(fn (array $slot) => $slot['at'] !== null)->values()->all();
    }
}
