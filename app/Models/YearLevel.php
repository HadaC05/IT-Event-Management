<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class YearLevel extends Model
{
    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_year_level')->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'year_level');
    }
}
