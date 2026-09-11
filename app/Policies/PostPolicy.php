<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function create(User $user): bool
    {
        return $user->role?->name === 'Student';
    }

    public function update(User $user, Post $post): bool
    {
        return $user->role?->name === 'Student' && $post->user_id === $user->id && in_array($post->status, ['pending', 'approved'], true);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->role?->name === 'Student' && $post->user_id === $user->id && $post->status === 'pending';
    }

    public function review(User $user, Post $post): bool
    {
        return $user->isSboAdviser() && $post->user_id !== $user->id && $post->status === 'pending';
    }

    public function interact(User $user, Post $post): bool
    {
        return $user->role?->name === 'Student' && $post->status === 'approved';
    }
}
