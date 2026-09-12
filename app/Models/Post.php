<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    public const CATEGORIES = ['general', 'announcement', 'sports', 'esports', 'academic', 'cultural', 'special-events'];

    public const STATUSES = ['draft', 'pending', 'approved', 'rejected'];

    protected $fillable = ['user_id', 'event_id', 'category', 'is_official', 'content', 'image_path', 'status', 'rejection_reason', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return ['is_official' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(PostAudit::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(PostReaction::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('is_official', true);
    }

    public function scopeCommunity(Builder $query): Builder
    {
        return $query->where('is_official', false);
    }
}
