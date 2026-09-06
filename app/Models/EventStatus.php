<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventStatus extends Model
{
    protected $fillable = ['label'];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'event_status_id');
    }
}
