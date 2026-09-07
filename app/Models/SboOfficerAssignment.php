<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SboOfficerAssignment extends Model
{
    protected $fillable = ['student_profile_id', 'officer_user_id', 'team_id', 'position', 'term', 'assigned_by', 'assigned_at', 'ended_by', 'ended_at', 'status'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    public function studentProfile(): BelongsTo { return $this->belongsTo(StudentProfile::class); }
    public function officerAccount(): BelongsTo { return $this->belongsTo(User::class, 'officer_user_id'); }
    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
    public function endedBy(): BelongsTo { return $this->belongsTo(User::class, 'ended_by'); }
}
