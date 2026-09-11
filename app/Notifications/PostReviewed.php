<?php

namespace App\Notifications;

use App\Models\Post;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PostReviewed extends Notification
{
    use Queueable;

    public function __construct(public Post $post) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['post_id' => $this->post->id, 'status' => $this->post->status, 'reason' => $this->post->rejection_reason, 'message' => 'Your post was '.$this->post->status.'.'];
    }
}
