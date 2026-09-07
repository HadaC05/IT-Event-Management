<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'student_profile_id',
        'password',
        'role_id',
        'id_number',
        'first_name',
        'middle_name',
        'last_name',
        'username',
        'year_level',
        'officer_team_id',
        'status',
        'must_change_password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function userStatus(): BelongsTo
    {
        return $this->belongsTo(UserStatus::class, 'status');
    }

    public function yearLevel(): BelongsTo
    {
        return $this->belongsTo(YearLevel::class, 'year_level');
    }

    public function assignedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class)->withTimestamps();
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withTimestamps();
    }

    public function officerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'officer_team_id');
    }

    public function studentProfile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class);
    }

    public function officerAssignments(): HasMany
    {
        return $this->hasMany(SboOfficerAssignment::class, 'officer_user_id');
    }

    public function activeOfficerAssignment(): HasOne
    {
        return $this->hasOne(SboOfficerAssignment::class, 'officer_user_id')->where('status', 'Active')->latestOfMany();
    }

    public function participantEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_participants')->withTimestamps();
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'actor_id');
    }

    public function isSboAdviser(): bool
    {
        return $this->role?->name === 'SBO Adviser';
    }

    public function isSboOfficer(): bool
    {
        return $this->role?->name === 'SBO Officer';
    }

    public function getFullNameAttribute(): string
    {
        if ($this->studentProfile) {
            return $this->studentProfile->full_name;
        }

        return trim(implode(' ', array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ])));
    }

    public function getFirstNameAttribute(?string $value): ?string { return $value ?? $this->studentProfile?->first_name; }
    public function getMiddleNameAttribute(?string $value): ?string { return $value ?? $this->studentProfile?->middle_name; }
    public function getLastNameAttribute(?string $value): ?string { return $value ?? $this->studentProfile?->last_name; }
    public function getEmailAttribute(?string $value): ?string { return $value ?? $this->studentProfile?->email; }
    public function getIdNumberAttribute(?string $value): ?string { return $value ?? $this->studentProfile?->student_id; }

    public function getDisplayEmailAttribute(): ?string
    {
        return $this->studentProfile?->email ?? $this->email;
    }

    public function getDisplayIdNumberAttribute(): ?string
    {
        return $this->studentProfile?->student_id ?? $this->id_number;
    }

    public function getDisplayYearLevelAttribute(): ?YearLevel
    {
        return $this->studentProfile?->yearLevel ?? $this->yearLevel;
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->display_email;
    }
}
