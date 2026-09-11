<?php

namespace App\Policies;

use App\Models\PostComment;
use App\Models\User;

class PostCommentPolicy
{
    public function delete(User $user, PostComment $comment): bool
    {
        return $user->role?->name === 'Student'
            && $comment->user_id === $user->id
            && $comment->post()->where('status', 'approved')->exists();
    }
}
