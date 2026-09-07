<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'actor_id',
        'subject_user_id',
        'student_profile_id',
        'officer_assignment_id',
        'event_id',
        'action',
        'acting_role',
        'description',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withDefault();
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id')->withDefault();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withDefault();
    }
}
