<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentProfile extends Model
{
    protected $fillable = ['student_id', 'first_name', 'middle_name', 'last_name', 'email', 'year_level_id'];

    public function accounts(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function yearLevel(): BelongsTo
    {
        return $this->belongsTo(YearLevel::class);
    }

    public function officerAssignments(): HasMany
    {
        return $this->hasMany(SboOfficerAssignment::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }
}
