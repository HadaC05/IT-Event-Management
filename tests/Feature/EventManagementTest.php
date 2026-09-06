<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventStatus;
use App\Models\EventTypes;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Team;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $adviser;

    private User $faculty;

    private EventStatus $activeEventStatus;

    private EventTypes $eventType;

    private UserStatus $activeUserStatus;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SBO Adviser', 'SBO', 'Faculty', 'Student'] as $role) {
            Role::create(['name' => $role]);
        }
        $this->activeUserStatus = UserStatus::create(['label' => 'active']);
        $this->activeEventStatus = EventStatus::create(['label' => 'active']);
        EventStatus::create(['label' => 'inactive']);
        $this->eventType = EventTypes::create(['label' => 'IT Days']);

        $this->adviser = User::factory()->create([
            'role_id' => Role::where('name', 'SBO Adviser')->value('id'),
            'status' => $this->activeUserStatus->id,
        ]);
        $this->faculty = User::factory()->create([
            'role_id' => Role::where('name', 'Faculty')->value('id'),
            'status' => $this->activeUserStatus->id,
        ]);
    }

    public function test_non_adviser_cannot_access_event_management(): void
    {
        $this->actingAs($this->faculty)->get('/adviser/events')->assertForbidden();
    }

    public function test_adviser_can_create_an_event_with_poster_and_event_in_charge(): void
    {
        Storage::fake('public');

        $this->actingAs($this->adviser)->get(route('adviser.events.create'))
            ->assertRedirect(route('adviser.events.index', ['create' => 1]));

        $this->get(route('adviser.events.index'))
            ->assertOk()
            ->assertSee('create-event-dialog')
            ->assertDontSee('Event type')
            ->assertDontSee('Select status');

        $response = $this->post('/adviser/events', [
            'title' => 'Foundation Day',
            'description' => 'Annual campus celebration.',
            'location' => 'University Gymnasium',
            'start_date' => '2026-10-10',
            'start_time' => '08:00',
            'end_date' => '2026-10-10',
            'end_time' => '17:00',
            'event_type_id' => $this->eventType->id,
            'event_status_id' => EventStatus::where('label', 'inactive')->value('id'),
            'assigned_user_ids' => [$this->faculty->id],
            'poster' => UploadedFile::fake()->createWithContent(
                'foundation-day.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=')
            ),
        ]);

        $event = Event::firstOrFail();
        $response->assertRedirect(route('adviser.events.index'));
        $this->assertDatabaseHas('events', [
            'title' => 'Foundation Day',
            'location' => 'University Gymnasium',
            'created_by' => $this->adviser->id,
            'event_type_id' => null,
            'event_status_id' => $this->activeEventStatus->id,
        ]);
        $this->assertDatabaseHas('event_user', ['event_id' => $event->id, 'user_id' => $this->faculty->id]);
        $this->assertDatabaseHas('activity_logs', ['event_id' => $event->id, 'action' => 'event_created']);
        Storage::disk('public')->assertExists($event->poster_path);

        $this->get(route('adviser.events.index'))
            ->assertOk()
            ->assertSee('Foundation Day')
            ->assertDontSee($event->poster_path);

        $this->get(route('adviser.events.show', $event))
            ->assertOk()
            ->assertSee('Foundation Day')
            ->assertSee($this->faculty->full_name);
        $this->get(route('adviser.events.edit', $event))
            ->assertOk()
            ->assertSee('Edit Event');
    }

    public function test_quick_create_prioritizes_schedule_participants_and_progressive_options(): void
    {
        $schoolYear = SchoolYear::create(['label' => '2026-2027']);
        $student = User::factory()->create([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => $this->activeUserStatus->id,
        ]);
        $tribe = Team::create([
            'school_year_id' => $schoolYear->id,
            'name' => 'Blue Eagles',
            'color' => '#2563EB',
        ]);
        $tribe->members()->attach($student);

        $this->actingAs($this->adviser)->get(route('adviser.events.index'))
            ->assertOk()
            ->assertSee('Quick create')
            ->assertSee('Event continues to another day')
            ->assertSee('Who should attend?')
            ->assertSee('All active students')
            ->assertSee('Blue Eagles')
            ->assertSee('You — SBO Adviser')
            ->assertSee('+ Add description')
            ->assertSee('+ Add poster')
            ->assertSee('data-create-event-submit disabled', false);
    }

    public function test_selected_tribe_audience_is_saved_and_expected_students_are_counted(): void
    {
        $schoolYear = SchoolYear::create(['label' => '2026-2027']);
        $students = User::factory()->count(2)->create([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => $this->activeUserStatus->id,
        ]);
        $tribe = Team::create([
            'school_year_id' => $schoolYear->id,
            'name' => 'Green Falcons',
            'color' => '#41B06E',
        ]);
        $tribe->members()->attach($students);

        $this->actingAs($this->adviser)->post(route('adviser.events.store'), [
            'title' => 'Tribe Assembly',
            'location' => 'Main Auditorium',
            'start_date' => '2026-10-12',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'audience_type' => 'selected_tribes',
            'tribe_ids' => [$tribe->id],
            'assigned_user_ids' => [$this->adviser->id],
        ])->assertRedirect(route('adviser.events.index'))
            ->assertSessionHasNoErrors();

        $event = Event::where('title', 'Tribe Assembly')->firstOrFail();
        $this->assertSame('2026-10-12', $event->end_at->toDateString());
        $this->assertDatabaseHas('event_team', ['event_id' => $event->id, 'team_id' => $tribe->id]);
        $this->assertDatabaseHas('event_user', ['event_id' => $event->id, 'user_id' => $this->adviser->id]);
        $this->assertSame(2, $event->expectedParticipants()->count());

        $this->get(route('adviser.events.show', $event))
            ->assertOk()
            ->assertSee('2 expected')
            ->assertSee('Selected tribes');
    }

    public function test_schedule_conflicts_are_reported_and_can_be_acknowledged(): void
    {
        $existing = Event::create([
            'title' => 'General Assembly',
            'location' => 'University Gymnasium',
            'start_at' => '2026-10-15 09:00:00',
            'end_at' => '2026-10-15 11:00:00',
            'event_status_id' => $this->activeEventStatus->id,
        ]);
        $existing->assignedUsers()->attach($this->faculty);
        $payload = [
            'title' => 'IT Program',
            'location' => 'University Gymnasium',
            'start_date' => '2026-10-15',
            'start_time' => '10:00',
            'end_date' => '2026-10-15',
            'end_time' => '12:00',
            'audience_type' => 'all_students',
            'assigned_user_ids' => [$this->faculty->id],
        ];

        $this->actingAs($this->adviser)->postJson(route('adviser.events.conflicts'), $payload)
            ->assertOk()
            ->assertJsonPath('has_conflicts', true)
            ->assertJsonPath('conflicts.location.0.title', 'General Assembly')
            ->assertJsonPath('conflicts.people.0.people.0', $this->faculty->full_name);

        $this->post(route('adviser.events.store'), $payload)
            ->assertSessionHasErrors(['location', 'assigned_user_ids']);
        $this->assertDatabaseMissing('events', ['title' => 'IT Program']);

        $this->post(route('adviser.events.store'), [...$payload, 'acknowledge_conflicts' => true])
            ->assertRedirect(route('adviser.events.index'))
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('events', ['title' => 'IT Program']);
    }

    public function test_event_dates_and_assignable_roles_are_validated(): void
    {
        $student = User::factory()->create([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => UserStatus::where('label', 'active')->value('id'),
        ]);

        $this->actingAs($this->adviser)->post('/adviser/events', [
            'title' => 'Invalid Event',
            'location' => 'Room 101',
            'start_date' => '2026-10-10',
            'start_time' => '17:00',
            'end_date' => '2026-10-10',
            'end_time' => '08:00',
            'event_status_id' => $this->activeEventStatus->id,
            'assigned_user_ids' => [$student->id],
        ])->assertSessionHasErrors(['end_time', 'assigned_user_ids']);

        $this->assertDatabaseCount('events', 0);
    }

    public function test_adviser_can_search_edit_archive_restore_and_delete_an_event(): void
    {
        $event = Event::create([
            'title' => 'Sports Festival',
            'location' => 'Main Field',
            'start_at' => now()->addWeek(),
            'end_at' => now()->addWeek()->addHours(8),
            'event_status_id' => $this->activeEventStatus->id,
            'created_by' => $this->adviser->id,
        ]);

        $this->actingAs($this->adviser)->get('/adviser/events?search=Sports&timing=upcoming')
            ->assertOk()
            ->assertSee('Sports Festival');

        $this->put(route('adviser.events.update', $event), [
            'title' => 'University Sports Festival',
            'description' => 'Updated details',
            'location' => 'Athletics Field',
            'start_date' => $event->start_at->format('Y-m-d'),
            'start_time' => $event->start_at->format('H:i'),
            'end_date' => $event->end_at->format('Y-m-d'),
            'end_time' => $event->end_at->format('H:i'),
            'event_status_id' => $this->activeEventStatus->id,
        ])->assertRedirect(route('adviser.events.show', $event));

        $this->delete(route('adviser.events.destroy', $event))->assertRedirect(route('adviser.events.index'));
        $this->assertSoftDeleted($event);

        $this->get('/adviser/events?timing=archived')->assertOk()->assertSee('University Sports Festival');
        $this->post(route('adviser.events.restore', $event->id))->assertRedirect(route('adviser.events.show', $event));
        $this->assertNotSoftDeleted($event);

        $this->delete(route('adviser.events.destroy', $event));
        $this->delete(route('adviser.events.force-delete', $event->id))
            ->assertRedirect(route('adviser.events.index', ['timing' => 'archived']));
        $this->assertDatabaseMissing('events', ['id' => $event->id]);
    }
}
