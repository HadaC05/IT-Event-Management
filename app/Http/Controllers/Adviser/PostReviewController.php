<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Post;
use App\Notifications\PostReviewed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostReviewController extends Controller
{
    public function index(): View
    {
        return view('adviser.posts.index', [
            'posts' => Post::community()->with(['author', 'event', 'reviewer'])->latest()->paginate(15),
            'pendingCount' => Post::community()->where('status', 'pending')->count(),
        ]);
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('review', $post);
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])], 'rejection_reason' => ['nullable', 'required_if:status,rejected', 'string', 'max:1000']]);
        DB::transaction(function () use ($request, $post, $data) {
            $post->update(['status' => $data['status'], 'rejection_reason' => $data['status'] === 'rejected' ? $data['rejection_reason'] : null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            $post->audits()->create(['actor_id' => $request->user()->id, 'action' => $data['status'], 'from_status' => 'pending', 'to_status' => $data['status'], 'notes' => $data['rejection_reason'] ?? null]);
            ActivityLog::create(['actor_id' => $request->user()->id, 'event_id' => $post->event_id, 'action' => 'post_'.$data['status'], 'acting_role' => 'SBO Adviser', 'description' => "Post #{$post->id} was {$data['status']}."]);
        });
        $post->author->notify(new PostReviewed($post));

        return back()->with('success', 'Post '.$data['status'].'.');
    }
}
