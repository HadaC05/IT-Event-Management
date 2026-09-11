<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostAudit extends Model
{
    protected $fillable = ['post_id', 'actor_id', 'action', 'from_status', 'to_status', 'notes'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
