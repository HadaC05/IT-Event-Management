<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Post;
use App\Models\Role;
use App\Models\User;
use App\Models\UserStatus;
use App\Notifications\PostReviewed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::insert([['name' => 'Student'], ['name' => 'SBO Adviser'], ['name' => 'Faculty']]);
        UserStatus::insert([['label' => 'active'], ['label' => 'inactive']]);
    }

    public function test_student_pages_are_role_protected_and_navigation_is_present(): void
    {
        $student = $this->user('Student');
        $faculty = $this->user('Faculty');
        $this->actingAs($student)->get(route('student.home'))->assertOk()->assertSee('What’s happening')->assertSee('Attendance')->assertSee('Rankings');
        $this->get(route('student.events.index'))->assertOk()->assertSee('Your events');
        $this->get(route('student.attendance.show'))->assertOk()->assertSee('Attendance overview');
        $this->get(route('student.team.show'))->assertOk()->assertSee('No team assigned');
        $this->get(route('student.leaderboard.index'))->assertOk()->assertSee('Leaderboard');
        $this->get(route('student.profile.show'))->assertOk()->assertSee($student->full_name);
        $this->get(route('student.profile.edit'))->assertOk()->assertSee('Edit profile');
        $this->get(route('student.settings'))->assertOk()->assertSee('Appearance')->assertSee('Dark');
        $this->actingAs($faculty)->get(route('student.home'))->assertForbidden();
    }

    public function test_student_post_waits_for_adviser_and_only_approved_posts_enter_feed(): void
    {
        Notification::fake();
        $student = $this->user('Student');
        $adviser = $this->user('SBO Adviser');
        $this->actingAs($student)->post(route('student.posts.store'), ['content' => 'Pending community update', 'category' => 'general'])->assertSessionHas('success');
        $post = Post::first();
        $this->assertSame('pending', $post->status);
        $this->actingAs($student)->get(route('student.home'))->assertOk()->assertSee('View pending posts')->assertSee('data-submissions-dialog', false);
        $this->actingAs($student)->get(route('student.profile.show'))->assertOk()->assertSee('Pending posts')->assertSee('View submissions');
        $this->actingAs($this->user('Student'))->get(route('student.home'))->assertDontSee('Pending community update');
        $this->actingAs($adviser)->patch(route('adviser.posts.review', $post), ['status' => 'approved'])->assertSessionHas('success');
        $this->actingAs($this->user('Student'))->get(route('student.home'))->assertSee('Pending community update');
        $this->actingAs($student)->get(route('student.profile.show'))->assertOk()->assertSee('Your posts')->assertSee('Pending community update');
        $this->assertDatabaseHas('post_audits', ['post_id' => $post->id, 'action' => 'approved']);
    }

    public function test_rejection_reason_is_author_only_and_post_permissions_are_enforced(): void
    {
        $author = $this->user('Student');
        $other = $this->user('Student');
        $adviser = $this->user('SBO Adviser');
        $post = Post::create(['user_id' => $author->id, 'content' => 'Review me', 'category' => 'general', 'status' => 'pending']);
        $this->actingAs($other)->put(route('student.posts.update', $post), ['content' => 'Hijacked', 'category' => 'general'])->assertForbidden();
        $this->actingAs($other)->delete(route('student.posts.destroy', $post))->assertForbidden();
        $this->actingAs($author)->patch(route('adviser.posts.review', $post), ['status' => 'approved'])->assertForbidden();
        $this->actingAs($adviser)->patch(route('adviser.posts.review', $post), ['status' => 'rejected', 'rejection_reason' => 'Please add the event name.'])->assertSessionHas('success');
        $this->actingAs($author)->get(route('student.home'))->assertSee('Please add the event name.');
        $this->actingAs($other)->get(route('student.home'))->assertDontSee('Please add the event name.');
    }

    public function test_editing_an_approved_post_returns_it_to_pending(): void
    {
        $student = $this->user('Student');
        $post = Post::create(['user_id' => $student->id, 'content' => 'Original', 'category' => 'general', 'status' => 'approved', 'reviewed_at' => now()]);
        $this->actingAs($student)->put(route('student.posts.update', $post), ['content' => 'Revised', 'category' => 'academic'])->assertSessionHas('success');
        $post->refresh();
        $this->assertSame('pending', $post->status);
        $this->assertNull($post->reviewed_at);
    }

    public function test_students_can_react_and_comment_only_on_approved_posts(): void
    {
        $author = $this->user('Student');
        $student = $this->user('Student');
        $other = $this->user('Student');
        $faculty = $this->user('Faculty');
        $approvedPost = Post::create([
            'user_id' => $author->id,
            'content' => 'Caption appears before the image.',
            'category' => 'general',
            'image_path' => 'posts/example.jpg',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
        $pendingPost = Post::create([
            'user_id' => $author->id,
            'content' => 'Still pending',
            'category' => 'general',
            'status' => 'pending',
        ]);

        $this->actingAs($student)
            ->put(route('student.posts.reactions.update', $approvedPost), ['reaction' => 'love'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('post_reactions', [
            'post_id' => $approvedPost->id,
            'user_id' => $student->id,
            'type' => 'love',
        ]);

        $this->put(route('student.posts.reactions.update', $approvedPost), ['reaction' => 'love'])
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('post_reactions', ['post_id' => $approvedPost->id, 'user_id' => $student->id]);

        $this->put(route('student.posts.reactions.update', $pendingPost), ['reaction' => 'like'])->assertForbidden();
        $this->post(route('student.posts.comments.store', $pendingPost), ['body' => 'Not public'])->assertForbidden();

        $this->post(route('student.posts.comments.store', $approvedPost), ['body' => 'Great event!'])
            ->assertSessionHas('success');
        $comment = $approvedPost->comments()->firstOrFail();

        $this->actingAs($other)
            ->delete(route('student.posts.comments.destroy', $comment))
            ->assertForbidden();

        $this->actingAs($faculty)
            ->post(route('student.posts.comments.store', $approvedPost), ['body' => 'Not a student'])
            ->assertForbidden();

        $this->actingAs($author)
            ->get(route('student.home'))
            ->assertOk()
            ->assertSee('Great event!')
            ->assertSee('aria-label="Edit post"', false)
            ->assertSeeInOrder(['Caption appears before the image.', 'data-post-media'], false);

        $this->actingAs($student)
            ->delete(route('student.posts.comments.destroy', $comment))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('post_comments', ['id' => $comment->id]);
    }

    public function test_post_images_use_a_consistent_preview_and_reject_excessive_dimensions(): void
    {
        Storage::fake('public');
        $student = $this->user('Student');

        $this->actingAs($student)
            ->get(route('student.home'))
            ->assertOk()
            ->assertSee('Feed image preview')
            ->assertSee('Instagram-style 4:5 portrait frame')
            ->assertSee('data-student-feature-carousel', false);

        $oversizedPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
        $oversizedPng = substr_replace($oversizedPng, pack('N', 4097), 16, 4);

        $this->post(route('student.posts.store'), [
            'content' => 'Image dimensions should be limited.',
            'category' => 'general',
            'image' => UploadedFile::fake()->createWithContent('oversized.png', $oversizedPng),
        ])->assertSessionHasErrors('image');

        $this->assertDatabaseMissing('posts', ['content' => 'Image dimensions should be limited.']);
    }

    public function test_post_review_notifications_appear_with_a_date_and_can_be_marked_as_read(): void
    {
        $student = $this->user('Student');
        $adviser = $this->user('SBO Adviser');
        $faculty = $this->user('Faculty');
        $post = Post::create([
            'user_id' => $student->id,
            'content' => 'Notify me after review',
            'category' => 'general',
            'status' => 'pending',
        ]);

        $this->actingAs($adviser)
            ->patch(route('adviser.posts.review', $post), ['status' => 'approved'])
            ->assertSessionHas('success');

        $notification = $student->notifications()->firstOrFail();

        $this->actingAs($student)
            ->get(route('student.home'))
            ->assertOk()
            ->assertSee('aria-label="1 unread notification"', false)
            ->assertSee('Your post was approved.')
            ->assertSee($notification->created_at->format('M j, Y · g:i A'));

        foreach (range(1, 9) as $index) {
            $student->notify(new PostReviewed($post));
        }

        $this->get(route('student.home'))
            ->assertOk()
            ->assertSee('9+');

        $this->patch(route('student.notifications.read'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertNotNull($notification->fresh()->read_at);

        $this->actingAs($faculty)
            ->patch(route('student.notifications.read'))
            ->assertForbidden();
    }

    public function test_attendance_page_never_accepts_another_student_identifier(): void
    {
        $student = $this->user('Student');
        $other = $this->user('Student');
        EventStatus::create(['label' => 'upcoming']);
        $event = Event::create(['title' => 'IT Days', 'description' => '', 'start_at' => now()->addDay(), 'end_at' => now()->addDays(2), 'event_status_id' => EventStatus::first()->id, 'audience_type' => 'all_students']);
        $otherEvent = Event::create(['title' => 'Other Student Event', 'description' => '', 'start_at' => now()->subDays(2), 'end_at' => now()->subDay(), 'event_status_id' => EventStatus::first()->id, 'audience_type' => 'all_students']);
        Attendance::create(['event_id' => $event->id, 'user_id' => $student->id, 'attendance_date' => now()->toDateString(), 'status' => 'present', 'morning_in_at' => now()->setTime(9, 0)]);
        Attendance::create(['event_id' => $otherEvent->id, 'user_id' => $other->id, 'attendance_date' => now()->subDay()->toDateString(), 'status' => 'absent']);
        $response = $this->actingAs($student)->get(route('student.attendance.show', ['user' => $other->id]));
        $response->assertOk()->assertSee($student->full_name)->assertSee('9:00 AM')->assertDontSee($other->full_name)->assertDontSee('Other Student Event');
        $this->assertDatabaseHas('attendance_qr_tokens', ['event_id' => $event->id, 'user_id' => $student->id]);
        $this->assertDatabaseMissing('attendance_qr_tokens', ['event_id' => $event->id, 'user_id' => $other->id]);
    }

    public function test_student_can_update_only_their_own_profile(): void
    {
        $student = $this->user('Student');
        $this->actingAs($student)->put(route('student.profile.update'), ['first_name' => 'Ana', 'middle_name' => '', 'last_name' => 'Reyes', 'username' => 'ana-reyes', 'email' => 'ana@example.test', 'bio' => 'CITE student leader.'])->assertRedirect(route('student.profile.show'));
        $this->assertDatabaseHas('users', ['id' => $student->id, 'bio' => 'CITE student leader.']);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->value('id'), 'status' => UserStatus::where('label', 'active')->value('id'), 'must_change_password' => false]);
    }
}
