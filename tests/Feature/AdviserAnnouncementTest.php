<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdviserAnnouncementTest extends TestCase
{
    use RefreshDatabase;

    private UserStatus $activeStatus;

    private User $adviser;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SBO Adviser', 'SBO', 'Faculty', 'Student'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->activeStatus = UserStatus::create(['label' => 'active']);
        $this->adviser = $this->user('SBO Adviser');
    }

    public function test_only_advisers_can_manage_official_announcements(): void
    {
        $student = $this->user('Student');

        $this->actingAs($student)->get(route('adviser.announcements.index'))->assertForbidden();
        $this->post(route('adviser.announcements.store'), [
            'content' => 'Students cannot publish this.',
            'intent' => 'publish',
        ])->assertForbidden();

        $this->actingAs($this->adviser)
            ->get(route('adviser.announcements.index'))
            ->assertOk()
            ->assertSee('Official communication')
            ->assertSee('Publish announcement');
    }

    public function test_adviser_can_save_a_draft_without_showing_it_in_the_student_feed(): void
    {
        $student = $this->user('Student');

        $this->actingAs($this->adviser)->post(route('adviser.announcements.store'), [
            'content' => 'Registration closes on Friday.',
            'intent' => 'draft',
        ])->assertRedirect(route('adviser.announcements.index'))->assertSessionHas('success');

        $announcement = Post::firstOrFail();
        $this->assertTrue($announcement->is_official);
        $this->assertSame('announcement', $announcement->category);
        $this->assertSame('draft', $announcement->status);
        $this->assertNull($announcement->reviewed_at);
        $this->assertDatabaseHas('post_audits', [
            'post_id' => $announcement->id,
            'action' => 'announcement_drafted',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_id' => null,
            'action' => 'announcement_drafted',
        ]);

        $this->actingAs($student)
            ->get(route('student.home'))
            ->assertOk()
            ->assertDontSee('Registration closes on Friday.');
    }

    public function test_published_announcement_appears_in_the_student_feed_with_official_identity(): void
    {
        Storage::fake('public');
        $student = $this->user('Student');
        $event = $this->event('Foundation Day');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        $this->actingAs($this->adviser)->post(route('adviser.announcements.store'), [
            'content' => 'Assembly starts at 8:00 AM. Please arrive early.',
            'event_id' => $event->id,
            'image' => UploadedFile::fake()->createWithContent('assembly.png', $image),
            'intent' => 'publish',
        ])->assertRedirect(route('adviser.announcements.index'))->assertSessionHas('success');

        $announcement = Post::firstOrFail();
        $this->assertSame('approved', $announcement->status);
        $this->assertSame($this->adviser->id, $announcement->reviewed_by);
        $this->assertNotNull($announcement->reviewed_at);
        Storage::disk('public')->assertExists($announcement->image_path);

        $this->actingAs($student)
            ->get(route('student.home'))
            ->assertOk()
            ->assertSee('Assembly starts at 8:00 AM. Please arrive early.')
            ->assertSee('Official announcement')
            ->assertSee('Foundation Day');
    }

    public function test_adviser_can_edit_unpublish_archive_and_restore_an_announcement(): void
    {
        $announcement = Post::create([
            'user_id' => $this->adviser->id,
            'category' => 'announcement',
            'is_official' => true,
            'content' => 'Original announcement',
            'status' => 'approved',
            'reviewed_by' => $this->adviser->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($this->adviser)->put(route('adviser.announcements.update', $announcement), [
            'content' => 'Updated announcement',
            'intent' => 'publish',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('posts', ['id' => $announcement->id, 'content' => 'Updated announcement', 'status' => 'approved']);

        $this->patch(route('adviser.announcements.status', $announcement), ['status' => 'draft'])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('posts', ['id' => $announcement->id, 'status' => 'draft', 'reviewed_at' => null]);

        $this->delete(route('adviser.announcements.destroy', $announcement))->assertSessionHas('success');
        $this->assertSoftDeleted($announcement);

        $this->get(route('adviser.announcements.index', ['status' => 'archived']))
            ->assertOk()
            ->assertSee('Updated announcement')
            ->assertSee('Restore as draft');

        $this->post(route('adviser.announcements.restore', $announcement->id))
            ->assertRedirect(route('adviser.announcements.index', ['status' => 'draft']))
            ->assertSessionHas('success');

        $announcement->refresh();
        $this->assertFalse($announcement->trashed());
        $this->assertSame('draft', $announcement->status);
        $this->assertDatabaseHas('post_audits', ['post_id' => $announcement->id, 'action' => 'announcement_restored']);
    }

    public function test_event_announcement_is_visible_only_to_students_in_that_event_audience(): void
    {
        $participant = $this->user('Student');
        $otherStudent = $this->user('Student');
        $event = $this->event('Team Briefing');
        $event->update(['audience_type' => 'specific_students']);
        $event->participants()->attach($participant);

        Post::create([
            'user_id' => $this->adviser->id,
            'event_id' => $event->id,
            'category' => 'announcement',
            'is_official' => true,
            'content' => 'Participant-only call time.',
            'status' => 'approved',
            'reviewed_by' => $this->adviser->id,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($participant)
            ->get(route('student.home'))
            ->assertOk()
            ->assertSee('Participant-only call time.');

        $this->actingAs($otherStudent)
            ->get(route('student.home'))
            ->assertOk()
            ->assertDontSee('Participant-only call time.');
    }

    public function test_official_announcements_do_not_enter_the_student_post_review_queue(): void
    {
        $student = $this->user('Student');
        Post::create([
            'user_id' => $this->adviser->id,
            'category' => 'announcement',
            'is_official' => true,
            'content' => 'Official draft',
            'status' => 'draft',
        ]);
        Post::create([
            'user_id' => $student->id,
            'category' => 'general',
            'content' => 'Student submission',
            'status' => 'pending',
        ]);

        $this->actingAs($this->adviser)
            ->get(route('adviser.posts.index'))
            ->assertOk()
            ->assertSee('Student submission')
            ->assertDontSee('Official draft');
    }

    public function test_announcement_routes_reject_ordinary_student_posts(): void
    {
        $student = $this->user('Student');
        $studentPost = Post::create([
            'user_id' => $student->id,
            'category' => 'announcement',
            'content' => 'This remains a student submission.',
            'status' => 'pending',
        ]);

        $this->actingAs($this->adviser)
            ->put(route('adviser.announcements.update', $studentPost), [
                'content' => 'Attempted conversion',
                'intent' => 'publish',
            ])
            ->assertForbidden();
        $this->patch(route('adviser.announcements.status', $studentPost), ['status' => 'approved'])
            ->assertForbidden();
        $this->delete(route('adviser.announcements.destroy', $studentPost))
            ->assertForbidden();

        $this->assertDatabaseHas('posts', [
            'id' => $studentPost->id,
            'content' => 'This remains a student submission.',
            'status' => 'pending',
            'is_official' => false,
        ]);
    }

    public function test_announcement_filters_find_status_event_and_content(): void
    {
        $event = $this->event('IT Days');
        Post::create(['user_id' => $this->adviser->id, 'event_id' => $event->id, 'category' => 'announcement', 'is_official' => true, 'content' => 'Bring your attendance pass.', 'status' => 'approved', 'reviewed_at' => now()]);
        Post::create(['user_id' => $this->adviser->id, 'category' => 'announcement', 'is_official' => true, 'content' => 'Unrelated draft', 'status' => 'draft']);

        $this->actingAs($this->adviser)
            ->get(route('adviser.announcements.index', [
                'status' => 'published',
                'event_id' => $event->id,
                'search' => 'attendance',
            ]))
            ->assertOk()
            ->assertSee('Bring your attendance pass.')
            ->assertDontSee('Unrelated draft');
    }

    private function user(string $role): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->value('id'),
            'status' => $this->activeStatus->id,
            'must_change_password' => false,
        ]);
    }

    private function event(string $title): Event
    {
        return Event::create([
            'title' => $title,
            'location' => 'Main Hall',
            'audience_type' => 'all_students',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDays(2),
            'created_by' => $this->adviser->id,
        ]);
    }
}
