<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceQrToken;
use App\Models\AttendanceSessionMode;
use App\Models\Event;
use App\Models\EventStatus;
use App\Models\Role;
use App\Models\SchoolYear;
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
        $this->travelTo('2026-09-09 08:00:00');

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
            'id_number' => $this->student->id_number,
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

    public function test_officer_can_scan_a_session_qr_for_their_team(): void
    {
        $event = $this->event();
        $morningQr = AttendanceQrToken::create([
            'event_id' => $event->id,
            'user_id' => $this->student->id,
            'session' => 'morning',
            'token' => str_repeat('m', 40),
        ]);

        $this->actingAs($this->officer)->post(route('officer.attendance.scan', $event), [
            'qr_content' => route('home', ['attendance_pass' => $morningQr->token]),
        ])->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('attendances', [
            'event_id' => $event->id,
            'user_id' => $this->student->id,
            'recorded_by' => $this->officer->id,
        ]);
    }

    public function test_multi_day_event_records_attendance_separately_for_each_day(): void
    {
        $this->travelTo('2026-09-09 08:00:00');
        $event = $this->event([
            'start_at' => '2026-09-09 07:00:00',
            'end_at' => '2026-09-10 18:00:00',
            'morning_in_at' => null,
            'morning_out_at' => null,
            'afternoon_in_at' => null,
            'afternoon_out_at' => null,
        ]);
        $event->attendanceSchedules()->delete();
        foreach (['2026-09-09', '2026-09-10'] as $date) {
            $event->attendanceSchedules()->create([
                'schedule_date' => $date,
                'attendance_session_mode_id' => AttendanceSessionMode::where('code', AttendanceSessionMode::TWO_SESSIONS)->value('id'),
                'morning_in_time' => '07:00',
                'morning_out_time' => '11:00',
                'afternoon_in_time' => '13:00',
                'afternoon_out_time' => '17:00',
            ]);
        }

        $this->actingAs($this->officer)->post(route('officer.attendance.scan', $event), [
            'id_number' => $this->student->id_number,
        ])->assertSessionHas('success');

        $this->travelTo('2026-09-10 08:00:00');
        $this->post(route('officer.attendance.scan', $event), [
            'id_number' => $this->student->id_number,
        ])->assertSessionHas('success');

        $this->assertSame(2, Attendance::whereBelongsTo($event)->whereBelongsTo($this->student)->count());
        $this->assertDatabaseHas('attendances', ['event_id' => $event->id, 'attendance_date' => '2026-09-09 00:00:00']);
        $this->assertDatabaseHas('attendances', ['event_id' => $event->id, 'attendance_date' => '2026-09-10 00:00:00']);
    }

    public function test_qr_must_match_the_active_session_and_officer_team(): void
    {
        $event = $this->event();
        $afternoonQr = AttendanceQrToken::create([
            'event_id' => $event->id,
            'user_id' => $this->student->id,
            'session' => 'afternoon',
            'token' => str_repeat('a', 40),
        ]);
        $otherTeamQr = AttendanceQrToken::create([
            'event_id' => $event->id,
            'user_id' => $this->otherStudent->id,
            'session' => 'morning',
            'token' => str_repeat('o', 40),
        ]);

        $this->actingAs($this->officer)->post(route('officer.attendance.scan', $event), [
            'qr_content' => route('home', ['attendance_pass' => $afternoonQr->token]),
        ])->assertSessionHas('error');
        $this->post(route('officer.attendance.scan', $event), [
            'qr_content' => route('home', ['attendance_pass' => $otherTeamQr->token]),
        ])->assertSessionHas('error');

        $this->assertDatabaseCount('attendances', 0);
    }

    public function test_student_dashboard_shows_event_session_qr_buttons(): void
    {
        $event = $this->event([
            'morning_in_at' => now()->setTime(7, 0),
            'morning_out_at' => now()->setTime(11, 0),
            'afternoon_in_at' => now()->setTime(13, 0),
            'afternoon_out_at' => now()->setTime(17, 0),
            'end_at' => now()->addDay(),
        ]);

        $this->actingAs($this->student)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Student attendance pass')
            ->assertSee($event->title)
            ->assertSee($this->team->name)
            ->assertSee('7:00 AM–11:00 AM')
            ->assertSee('1:00 PM–5:00 PM')
            ->assertSee('Open Morning QR')
            ->assertSee('Open Afternoon QR');
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
        $scheduleAttributes = collect($attributes)->only(['morning_in_at', 'morning_out_at', 'afternoon_in_at', 'afternoon_out_at']);
        $event = Event::create(array_merge([
            'title' => 'IT Days Attendance',
            'location' => 'CITE Hall',
            'audience_type' => 'all_students',
            'event_status_id' => $this->eventStatus->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHours(8),
        ], collect($attributes)->except(['morning_in_at', 'morning_out_at', 'afternoon_in_at', 'afternoon_out_at'])->all()));

        $times = array_merge([
            'morning_in_at' => now()->subMinutes(15),
            'morning_out_at' => now()->addHours(2),
            'afternoon_in_at' => now()->addHours(3),
            'afternoon_out_at' => now()->addHours(7),
        ], $scheduleAttributes->all());
        $event->attendanceSchedules()->create([
            'schedule_date' => $event->start_at->toDateString(),
            'attendance_session_mode_id' => AttendanceSessionMode::where('code', AttendanceSessionMode::TWO_SESSIONS)->value('id'),
            'morning_in_time' => $times['morning_in_at']?->format('H:i'),
            'morning_out_time' => $times['morning_out_at']?->format('H:i'),
            'afternoon_in_time' => $times['afternoon_in_at']?->format('H:i'),
            'afternoon_out_time' => $times['afternoon_out_at']?->format('H:i'),
        ]);

        return $event->fresh('attendanceSchedules.attendanceSessionMode');
    }
}
