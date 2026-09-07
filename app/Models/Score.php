<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    protected $fillable = [
        'event_id',
        'team_id',
        'score_category_id',
        'points',
        'recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:2',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ScoreCategory::class, 'score_category_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
