<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Post;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Team;
use App\Models\User;
use App\Models\UserStatus;
use App\Services\EventStatusSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventCarouselTest extends TestCase
{
    use RefreshDatabase;

    private UserStatus $activeUserStatus;

    private EventStatus $upcomingStatus;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Student', 'SBO Officer', 'SBO Adviser', 'Faculty'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $this->activeUserStatus = UserStatus::firstOrCreate(['label' => 'active']);
        $this->upcomingStatus = EventStatus::firstOrCreate(['label' => 'upcoming']);
        EventStatus::firstOrCreate(['label' => 'ongoing']);
        EventStatus::firstOrCreate(['label' => 'completed']);
        EventStatus::firstOrCreate(['label' => 'inactive']);
    }

    public function test_adviser_features_existing_events_and_carousel_reuses_event_data_in_order(): void
    {
        $adviser = $this->user('SBO Adviser');
        $first = $this->event('First Featured Event', now()->addDays(3));
        $second = $this->event('Second Featured Event', now()->addDays(2));
        $first->update(['poster_path' => 'event-posters/first-featured.jpg']);

        $this->actingAs($adviser)
            ->patch(route('adviser.events.feature', $first), [
                'is_featured' => true,
                'featured_order' => 1,
                'featured_until' => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHas('success');

        $this->patch(route('adviser.events.feature', $second), [
            'is_featured' => true,
            'featured_order' => 2,
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('events', [
            'id' => $first->id,
            'title' => 'First Featured Event',
            'is_featured' => true,
            'featured_order' => 1,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'event_id' => $first->id,
            'actor_id' => $adviser->id,
            'action' => 'event_featured',
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-feature-carousel', false)
            ->assertSeeInOrder(['First Featured Event', 'Second Featured Event'])
            ->assertSee($first->description)
            ->assertSee($first->location)
            ->assertSee('storage/event-posters/first-featured.jpg')
            ->assertSee($first->start_at->format('M j, Y · g:i A'))
            ->assertSee('upcoming');

        $student = $this->user('Student');
        $this->actingAs($student)
            ->get(route('student.home'))
            ->assertOk()
            ->assertSee('data-student-feature-carousel', false)
            ->assertSeeInOrder(['First Featured Event', 'Second Featured Event']);
    }

    public function test_officer_can_feature_an_eligible_event_but_students_cannot(): void
    {
        $schoolYear = SchoolYear::create(['label' => '2026-2027']);
        $team = Team::create([
            'school_year_id' => $schoolYear->id,
            'name' => 'Green Innovators',
            'color' => '#397565',
        ]);
        $student = $this->user('Student');
        $team->members()->attach($student);
        $officer = $this->user('SBO Officer', ['officer_team_id' => $team->id]);
        $event = $this->event('Officer Featured Event', now()->addDays(2));

        $this->actingAs($officer)
            ->patch(route('officer.events.feature', $event), ['is_featured' => true])
            ->assertSessionHas('success');

        $this->assertTrue($event->fresh()->is_featured);

        $this->actingAs($student)
            ->patch(route('officer.events.feature', $event), ['is_featured' => false])
            ->assertForbidden();

        $this->assertTrue($event->fresh()->is_featured);
    }

    public function test_completed_and_expired_events_leave_carousel_without_deleting_events_or_posts(): void
    {
        $student = $this->user('Student');
        $event = $this->event('Completed Featured Event', now()->subDays(2), now()->subDay());
        $event->update(['is_featured' => true, 'featured_order' => 1]);
        $expiredEvent = $this->event('Expired Featured Event', now()->addDays(2));
        $expiredEvent->update([
            'is_featured' => true,
            'featured_order' => 2,
            'featured_until' => now()->subMinute(),
        ]);
        $post = Post::create([
            'user_id' => $student->id,
            'event_id' => $event->id,
            'content' => 'A preserved event memory',
            'category' => 'general',
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);

        app(EventStatusSynchronizer::class)->sync();

        $event->refresh();
        $this->assertFalse($event->is_featured);
        $this->assertSame('completed', $event->status->label);
        $this->assertDatabaseHas('events', ['id' => $event->id]);
        $this->assertDatabaseHas('posts', ['id' => $post->id, 'event_id' => $event->id]);
        $this->assertFalse(Event::featuredForCarousel()->whereKey($expiredEvent)->exists());

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Completed Featured Event')
            ->assertSee('No featured events yet');

        $this->actingAs($student)
            ->get(route('student.events.index'))
            ->assertOk()
            ->assertSee('Past Events')
            ->assertSee('Completed Featured Event');

        $this->get(route('student.home'))
            ->assertOk()
            ->assertSee('A preserved event memory');
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->value('id'),
            'status' => $this->activeUserStatus->id,
            'must_change_password' => false,
            ...$attributes,
        ]);
    }

    private function event(string $title, $startAt, $endAt = null): Event
    {
        return Event::create([
            'title' => $title,
            'description' => "Description for {$title}",
            'location' => 'CITE Campus',
            'start_at' => $startAt,
            'end_at' => $endAt ?? $startAt->copy()->addHours(4),
            'event_status_id' => $this->upcomingStatus->id,
            'audience_type' => 'all_students',
        ]);
    }
}
