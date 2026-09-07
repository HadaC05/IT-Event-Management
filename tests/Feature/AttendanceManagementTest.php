<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Team;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $adviser;

    private UserStatus $active;

    private EventStatus $eventStatus;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SBO Adviser', 'SBO', 'Faculty', 'Student'] as $role) {
            Role::create(['name' => $role]);
        }
        $this->active = UserStatus::create(['label' => 'active']);
        UserStatus::create(['label' => 'inactive']);
        $this->eventStatus = EventStatus::create(['label' => 'active']);
        $this->adviser = User::factory()->create([
            'role_id' => Role::where('name', 'SBO Adviser')->value('id'),
            'status' => $this->active->id,
        ]);
    }

    public function test_only_adviser_can_access_attendance_management(): void
    {
        $student = $this->student();

        $this->actingAs($student)->get(route('adviser.attendance.index'))->assertForbidden();
        $this->actingAs($this->adviser)->get(route('adviser.attendance.index'))
            ->assertOk()
            ->assertSee('Attendance command center');
    }

    public function test_event_directory_shows_roster_coverage_and_attendance_rate(): void
    {
        $students = collect([$this->student(), $this->student()]);
        $event = $this->event(['audience_type' => 'all_students']);
        Attendance::create([
            'event_id' => $event->id,
            'user_id' => $students->first()->id,
            'status' => 'present',
            'checked_in_at' => now(),
            'recorded_by' => $this->adviser->id,
        ]);

        $this->actingAs($this->adviser)->get(route('adviser.attendance.index'))
            ->assertOk()
            ->assertSee($event->title)
            ->assertSee('1 of 2 recorded')
            ->assertSee('50%')
            ->assertSee('Present or late')
            ->assertSee('Among recorded statuses')
            ->assertSee('Open Roster');
    }

    public function test_selected_tribe_generates_the_event_participant_roster(): void
    {
        $schoolYear = SchoolYear::create(['label' => '2026-2027']);
        $included = collect([$this->student(), $this->student()]);
        $excluded = $this->student();
        $tribe = Team::create([
            'school_year_id' => $schoolYear->id,
            'name' => 'Green Falcons',
            'color' => '#41B06E',
        ]);
        $tribe->members()->attach($included);
        $event = $this->event(['audience_type' => 'selected_tribes']);
        $event->audienceTeams()->attach($tribe);

        $response = $this->actingAs($this->adviser)->get(route('adviser.attendance.show', $event));

        $response->assertOk()
            ->assertSee('Student Participants')
            ->assertSee($included[0]->full_name)
            ->assertSee($included[1]->full_name)
            ->assertDontSee($excluded->full_name)
            ->assertSee('Mark this page present')
            ->assertSee('Save Attendance');
    }

    public function test_roster_search_and_status_filters_are_applied_server_side(): void
    {
        $present = $this->student([
            'first_name' => 'Recorded',
            'last_name' => 'Student',
            'id_number' => 'REC-100',
        ]);
        $unrecorded = $this->student([
            'first_name' => 'Waiting',
            'last_name' => 'Student',
            'id_number' => 'WAIT-200',
        ]);
        $event = $this->event(['audience_type' => 'all_students']);
        Attendance::create([
            'event_id' => $event->id,
            'user_id' => $present->id,
            'status' => 'present',
            'checked_in_at' => now(),
            'recorded_by' => $this->adviser->id,
        ]);

        $this->actingAs($this->adviser)
            ->get(route('adviser.attendance.show', ['event' => $event, 'search' => 'WAIT-200', 'status' => 'unrecorded']))
            ->assertOk()
            ->assertSee($unrecorded->full_name)
            ->assertDontSee($present->full_name)
            ->assertSee('showing 1 matching student');
    }

    public function test_large_rosters_are_paginated_to_fifty_students(): void
    {
        User::factory()->count(51)->create([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => $this->active->id,
        ]);
        $event = $this->event(['audience_type' => 'all_students']);

        $response = $this->actingAs($this->adviser)->get(route('adviser.attendance.show', $event));

        $response->assertOk()->assertViewHas('participants', function ($participants) {
            return $participants->perPage() === 50
                && $participants->count() === 50
                && $participants->total() === 51;
        });
    }

    public function test_multi_day_event_header_shows_the_complete_date_range(): void
    {
        $this->student();
        $event = $this->event([
            'audience_type' => 'all_students',
            'start_at' => '2026-09-01 10:00:00',
            'end_at' => '2026-09-09 12:00:00',
        ]);

        $this->actingAs($this->adviser)->get(route('adviser.attendance.show', $event))
            ->assertOk()
            ->assertSee('Tue, Sep 1 · 10:00 AM')
            ->assertSee('Wed, Sep 9, 2026 · 12:00 PM');
    }

    public function test_adviser_can_record_change_and_clear_attendance_statuses(): void
    {
        $students = collect([$this->student(), $this->student()]);
        $event = $this->event(['audience_type' => 'all_students']);

        $this->actingAs($this->adviser)->put(route('adviser.attendance.update', $event), [
            'records' => [
                $students[0]->id => ['status' => 'present'],
                $students[1]->id => ['status' => 'late'],
            ],
        ])->assertSessionHasNoErrors();

        $present = Attendance::whereBelongsTo($event)->whereBelongsTo($students[0])->firstOrFail();
        $this->assertSame('present', $present->status);
        $this->assertNotNull($present->checked_in_at);
        $this->assertSame($this->adviser->id, $present->recorded_by);
        $this->assertDatabaseHas('attendances', ['event_id' => $event->id, 'user_id' => $students[1]->id, 'status' => 'late']);
        $this->assertDatabaseHas('activity_logs', ['event_id' => $event->id, 'action' => 'attendance_updated']);

        $this->put(route('adviser.attendance.update', $event), [
            'records' => [
                $students[0]->id => ['status' => 'absent'],
                $students[1]->id => ['status' => null],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('attendances', ['event_id' => $event->id, 'user_id' => $students[0]->id, 'status' => 'absent', 'checked_in_at' => null]);
        $this->assertDatabaseMissing('attendances', ['event_id' => $event->id, 'user_id' => $students[1]->id]);
    }

    public function test_invalid_statuses_and_students_outside_the_roster_are_rejected(): void
    {
        $included = $this->student();
        $excluded = $this->student();
        $event = $this->event(['audience_type' => 'specific_students']);
        $event->participants()->attach($included);

        $this->actingAs($this->adviser)->put(route('adviser.attendance.update', $event), [
            'records' => [
                $included->id => ['status' => 'unknown'],
                $excluded->id => ['status' => 'present'],
            ],
        ])->assertSessionHasErrors(['records.'.$included->id.'.status', 'records']);

        $this->assertDatabaseCount('attendances', 0);
    }

    private function student(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => $this->active->id,
        ], $attributes));
    }

    private function event(array $attributes = []): Event
    {
        return Event::create(array_merge([
            'title' => 'IT General Assembly',
            'location' => 'Main Auditorium',
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(2),
            'event_status_id' => $this->eventStatus->id,
            'created_by' => $this->adviser->id,
        ], $attributes));
    }
}
