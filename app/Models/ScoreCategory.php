<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoreCategory extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'max_points',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'max_points' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
