<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostReviewSubmitted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Post::class);
        $data = $this->validated($request);

        $post = DB::transaction(function () use ($request, $data) {
            if ($request->hasFile('image')) {
                $data['image_path'] = $request->file('image')->store('posts', 'public');
            }
            $post = $request->user()->posts()->create([...$data, 'status' => 'pending']);
            $post->audits()->create(['actor_id' => $request->user()->id, 'action' => 'submitted', 'to_status' => 'pending']);
            ActivityLog::create(['actor_id' => $request->user()->id, 'event_id' => $post->event_id, 'action' => 'post_submitted', 'acting_role' => 'Student', 'description' => "Post #{$post->id} was submitted for adviser review."]);

            return $post;
        });

        $advisers = User::whereHas('role', fn ($query) => $query->where('name', 'SBO Adviser'))->get();
        Notification::send($advisers, new PostReviewSubmitted($post->load('author')));

        return back()->with('success', 'Post submitted. Waiting for adviser approval.');
    }

    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('update', $post);
        $data = $this->validated($request);
        $oldStatus = $post->status;

        DB::transaction(function () use ($request, $post, $data, $oldStatus) {
            if ($request->boolean('remove_image') && $post->image_path) {
                Storage::disk('public')->delete($post->image_path);
                $data['image_path'] = null;
            }
            if ($request->hasFile('image')) {
                if ($post->image_path) {
                    Storage::disk('public')->delete($post->image_path);
                }
                $data['image_path'] = $request->file('image')->store('posts', 'public');
            }
            $post->update([...$data, 'status' => 'pending', 'rejection_reason' => null, 'reviewed_by' => null, 'reviewed_at' => null]);
            $post->audits()->create(['actor_id' => $request->user()->id, 'action' => 'edited', 'from_status' => $oldStatus, 'to_status' => 'pending']);
        });

        return back()->with('success', $oldStatus === 'approved' ? 'Changes saved. The post is waiting for another review.' : 'Pending post updated.');
    }

    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);
        $post->audits()->create(['actor_id' => $request->user()->id, 'action' => 'deleted', 'from_status' => 'pending']);
        if ($post->image_path) {
            Storage::disk('public')->delete($post->image_path);
        }
        ActivityLog::create(['actor_id' => $request->user()->id, 'event_id' => $post->event_id, 'action' => 'post_deleted', 'acting_role' => 'Student', 'description' => "Pending post #{$post->id} was deleted."]);
        $post->delete();

        return back()->with('success', 'Pending post deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'max:3000'],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'category' => ['required', Rule::in(Post::CATEGORIES)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096'],
            'remove_image' => ['nullable', 'boolean'],
        ]);
        if (! empty($data['event_id'])) {
            $event = Event::findOrFail($data['event_id']);
            abort_unless($event->expectedParticipantsQuery()->whereKey($request->user()->id)->exists(), 403);
        }

        return $data;
    }
}
