<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficerAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private UserStatus $active;
    private EventStatus $eventStatus;
    private Team $team;
    private User $officer;
    private User $student;
    private User $otherStudent;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SBO Adviser', 'SBO', 'SBO Officer', 'Faculty', 'Student'] as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
        $this->active = UserStatus::create(['label' => 'active']);
        $this->eventStatus = EventStatus::create(['label' => 'active']);
        $schoolYear = SchoolYear::create(['label' => '2026-2027']);
        $this->team = Team::create(['school_year_id' => $schoolYear->id, 'name' => 'Emerald Tribe', 'color' => '#397565']);
        $otherTeam = Team::create(['school_year_id' => $schoolYear->id, 'name' => 'Cobalt Tribe', 'color' => '#2F3AE0']);

        $this->officer = User::factory()->create([
            'role_id' => Role::where('name', 'SBO Officer')->value('id'),
            'status' => $this->active->id,
            'officer_team_id' => $this->team->id,
        ]);
        $this->student = User::factory()->create([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => $this->active->id,
            'id_number' => 'CITE-1001',
        ]);
        $this->otherStudent = User::factory()->create([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => $this->active->id,
            'id_number' => 'CITE-9001',
        ]);
        $this->team->members()->attach($this->student);
        $otherTeam->members()->attach($this->otherStudent);
    }

    public function test_officer_lands_on_a_roster_limited_to_their_tribe(): void
    {
        $event = $this->event();

        $this->actingAs($this->officer)->get(route('dashboard'))
            ->assertRedirect(route('officer.attendance.index'));

        $this->actingAs($this->officer)->get(route('officer.attendance.index', $event))
            ->assertOk()
            ->assertSee('Mark attendance.')
            ->assertSee($this->team->name)
            ->assertSee($this->student->full_name)
            ->assertSee('CITE-1001')
            ->assertDontSee($this->otherStudent->full_name)
            ->assertDontSee('CITE-9001');
    }

    public function test_officer_login_goes_directly_to_attendance(): void
    {
        $this->post('/login', [
            'login' => $this->officer->username,
            'password' => 'password',
        ])->assertRedirect(route('officer.attendance.index'));

        $this->assertAuthenticatedAs($this->officer);
    }

    public function test_adviser_assigns_and_unassigns_an_officer_after_account_creation(): void
    {
        $adviser = User::factory()->create([
            'role_id' => Role::where('name', 'SBO Adviser')->value('id'),
            'status' => $this->active->id,
        ]);
        $profile = StudentProfile::create([
            'student_id' => $this->student->id_number,
            'first_name' => $this->student->first_name,
            'middle_name' => $this->student->middle_name,
            'last_name' => $this->student->last_name,
            'email' => $this->student->email,
        ]);
        $this->student->update(['student_profile_id' => $profile->id]);
        $payload = [
            'student_user_id' => $this->student->id,
            'team_id' => $this->team->id,
            'position' => 'Attendance Officer',
            'term' => '2026-2027',
            'username' => 'alex.officer',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];

        $this->actingAs($adviser)->post(route('adviser.officers.store'), $payload)
            ->assertRedirect(route('adviser.officers.index'));

        $officer = User::where('username', 'alex.officer')->firstOrFail();
        $this->assertDatabaseHas('users', [
            'id' => $officer->id,
            'student_profile_id' => $profile->id,
            'officer_team_id' => $this->team->id,
        ]);
        $this->assertDatabaseHas('sbo_officer_assignments', ['officer_user_id' => $officer->id, 'status' => 'Active']);

        $assignment = $officer->officerAssignments()->firstOrFail();
        $this->patch(route('adviser.officers.unassign', $assignment))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', [
            'id' => $officer->id,
            'officer_team_id' => null,
            'status' => UserStatus::where('label', 'inactive')->value('id'),
        ]);
        $this->assertDatabaseHas('users', ['id' => $this->student->id, 'status' => $this->active->id]);
        $this->assertDatabaseHas('sbo_officer_assignments', ['id' => $assignment->id, 'status' => 'Inactive']);
    }

    public function test_officer_scan_records_only_the_active_checkpoint_for_their_tribe(): void
    {
        $event = $this->event();

        $this->actingAs($this->officer)->post(route('officer.attendance.scan', $event), [
            'id_number' => $this->student->id_number,
        ])->assertRedirect()->assertSessionHas('success');

        $attendance = Attendance::whereBelongsTo($event)->whereBelongsTo($this->student)->firstOrFail();
        $this->assertNotNull($attendance->morning_in_at);
        $this->assertNull($attendance->morning_out_at);
        $this->assertSame($this->officer->id, $attendance->recorded_by);

        $this->actingAs($this->officer)->post(route('officer.attendance.scan', $event), [
            'id_number' => $this->otherStudent->id_number,
        ])->assertRedirect()->assertSessionHas('error');

        $this->assertDatabaseMissing('attendances', ['event_id' => $event->id, 'user_id' => $this->otherStudent->id]);
    }

    public function test_officer_cannot_scan_before_a_scheduled_window(): void
    {
        $event = $this->event([
            'start_at' => now()->addHour(),
            'end_at' => now()->addHours(8),
            'morning_in_at' => now()->addHour(),
            'morning_out_at' => now()->addHours(3),
            'afternoon_in_at' => now()->addHours(4),
            'afternoon_out_at' => now()->addHours(7),
        ]);

        $this->actingAs($this->officer)->post(route('officer.attendance.scan', $event), [
            'id_number' => $this->student->id_number,
        ])->assertRedirect()->assertSessionHas('warning');

        $this->assertDatabaseMissing('attendances', ['event_id' => $event->id, 'user_id' => $this->student->id]);
    }

    private function event(array $attributes = []): Event
    {
        return Event::create(array_merge([
            'title' => 'IT Days Attendance',
            'location' => 'CITE Hall',
            'audience_type' => 'all_students',
            'event_status_id' => $this->eventStatus->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHours(8),
            'morning_in_at' => now()->subMinutes(15),
            'morning_out_at' => now()->addHours(2),
            'afternoon_in_at' => now()->addHours(3),
            'afternoon_out_at' => now()->addHours(7),
        ], $attributes));
    }
}
