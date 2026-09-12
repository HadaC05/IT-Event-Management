<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\AnnouncementRequest;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'draft', 'published', 'archived'])],
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')->whereNull('deleted_at')],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $validated['status'] ?? 'all';

        $announcements = Post::query()
            ->when($status === 'archived', fn (Builder $query) => $query->onlyTrashed())
            ->official()
            ->with(['author', 'event'])
            ->when($status === 'draft', fn (Builder $query) => $query->where('status', 'draft'))
            ->when($status === 'published', fn (Builder $query) => $query->where('status', 'approved'))
            ->when($validated['event_id'] ?? null, fn (Builder $query, int $eventId) => $query->where('event_id', $eventId))
            ->when($validated['search'] ?? null, function (Builder $query, string $search) {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where('content', 'like', $term);
            })
            ->latest('updated_at')
            ->paginate(10)
            ->withQueryString();

        return view('adviser.announcements.index', [
            'announcements' => $announcements,
            'events' => Event::query()->orderByDesc('start_at')->get(['id', 'title', 'start_at']),
            'summary' => [
                'published' => Post::official()->where('status', 'approved')->count(),
                'drafts' => Post::official()->where('status', 'draft')->count(),
                'archived' => Post::onlyTrashed()->official()->count(),
                'events' => Post::official()->where('status', 'approved')->whereNotNull('event_id')->distinct()->count('event_id'),
            ],
        ]);
    }

    public function store(AnnouncementRequest $request): RedirectResponse
    {
        $this->authorize('createAnnouncement', Post::class);
        $data = $request->validated();
        $status = $data['intent'] === 'publish' ? 'approved' : 'draft';
        $imagePath = $request->hasFile('image')
            ? $request->file('image')->store('posts', 'public')
            : null;

        $announcement = DB::transaction(function () use ($request, $data, $status, $imagePath) {
            $announcement = Post::create([
                'user_id' => $request->user()->id,
                'event_id' => $data['event_id'] ?? null,
                'category' => 'announcement',
                'is_official' => true,
                'content' => $data['content'],
                'image_path' => $imagePath,
                'status' => $status,
                'reviewed_by' => $status === 'approved' ? $request->user()->id : null,
                'reviewed_at' => $status === 'approved' ? now() : null,
            ]);

            $announcement->audits()->create([
                'actor_id' => $request->user()->id,
                'action' => $status === 'approved' ? 'announcement_published' : 'announcement_drafted',
                'to_status' => $status,
            ]);
            $this->logActivity($request, $announcement, $status === 'approved' ? 'announcement_published' : 'announcement_drafted');

            return $announcement;
        });

        return to_route('adviser.announcements.index')
            ->with('success', $announcement->status === 'approved' ? 'Announcement published to the student feed.' : 'Announcement saved as a draft.');
    }

    public function update(AnnouncementRequest $request, Post $announcement): RedirectResponse
    {
        $this->authorize('updateAnnouncement', $announcement);
        $data = $request->validated();
        $oldStatus = $announcement->status;
        $status = $data['intent'] === 'publish' ? 'approved' : 'draft';
        $imagePath = $announcement->image_path;

        if ($request->boolean('remove_image') && $imagePath) {
            Storage::disk('public')->delete($imagePath);
            $imagePath = null;
        }
        if ($request->hasFile('image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('posts', 'public');
        }

        DB::transaction(function () use ($request, $announcement, $data, $oldStatus, $status, $imagePath) {
            $announcement->update([
                'event_id' => $data['event_id'] ?? null,
                'content' => $data['content'],
                'image_path' => $imagePath,
                'status' => $status,
                'rejection_reason' => null,
                'reviewed_by' => $status === 'approved' ? $request->user()->id : null,
                'reviewed_at' => $status === 'approved' ? now() : null,
            ]);
            $announcement->audits()->create([
                'actor_id' => $request->user()->id,
                'action' => $status === 'approved' ? 'announcement_published' : 'announcement_drafted',
                'from_status' => $oldStatus,
                'to_status' => $status,
            ]);
            $this->logActivity($request, $announcement, $status === 'approved' ? 'announcement_published' : 'announcement_drafted');
        });

        return back()->with('success', $status === 'approved' ? 'Announcement updated and published.' : 'Announcement updated and moved to drafts.');
    }

    public function updateStatus(Request $request, Post $announcement): RedirectResponse
    {
        $this->authorize('updateAnnouncement', $announcement);
        $data = $request->validate(['status' => ['required', Rule::in(['draft', 'approved'])]]);
        $oldStatus = $announcement->status;

        DB::transaction(function () use ($request, $announcement, $data, $oldStatus) {
            $announcement->update([
                'status' => $data['status'],
                'reviewed_by' => $data['status'] === 'approved' ? $request->user()->id : null,
                'reviewed_at' => $data['status'] === 'approved' ? now() : null,
            ]);
            $action = $data['status'] === 'approved' ? 'announcement_published' : 'announcement_unpublished';
            $announcement->audits()->create([
                'actor_id' => $request->user()->id,
                'action' => $action,
                'from_status' => $oldStatus,
                'to_status' => $data['status'],
            ]);
            $this->logActivity($request, $announcement, $action);
        });

        return back()->with('success', $data['status'] === 'approved' ? 'Announcement published.' : 'Announcement returned to drafts.');
    }

    public function destroy(Request $request, Post $announcement): RedirectResponse
    {
        $this->authorize('deleteAnnouncement', $announcement);

        DB::transaction(function () use ($request, $announcement) {
            $announcement->audits()->create([
                'actor_id' => $request->user()->id,
                'action' => 'announcement_archived',
                'from_status' => $announcement->status,
            ]);
            $this->logActivity($request, $announcement, 'announcement_archived');
            $announcement->delete();
        });

        return back()->with('success', 'Announcement archived.');
    }

    public function restore(Request $request, int $announcement): RedirectResponse
    {
        $announcement = Post::withTrashed()->findOrFail($announcement);
        $this->authorize('restoreAnnouncement', $announcement);

        DB::transaction(function () use ($request, $announcement) {
            $announcement->restore();
            $announcement->update(['status' => 'draft', 'reviewed_by' => null, 'reviewed_at' => null]);
            $announcement->audits()->create([
                'actor_id' => $request->user()->id,
                'action' => 'announcement_restored',
                'to_status' => 'draft',
            ]);
            $this->logActivity($request, $announcement, 'announcement_restored');
        });

        return to_route('adviser.announcements.index', ['status' => 'draft'])
            ->with('success', 'Announcement restored as a draft.');
    }

    private function logActivity(Request $request, Post $announcement, string $action): void
    {
        $verb = str($action)->after('announcement_')->replace('_', ' ');
        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'event_id' => $announcement->event_id,
            'action' => $action,
            'acting_role' => 'SBO Adviser',
            'description' => "Official announcement #{$announcement->id} was {$verb}.",
        ]);
    }
}
