<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Score;
use App\Models\ScoreCategory;
use App\Models\Team;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdviserReportTest extends TestCase
{
    use RefreshDatabase;

    private User $adviser;

    private UserStatus $active;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SBO Adviser', 'SBO', 'Faculty', 'Student'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->active = UserStatus::create(['label' => 'active']);
        $this->adviser = $this->user('SBO Adviser');
    }

    public function test_reports_are_adviser_only_and_linked_in_the_navigation(): void
    {
        $this->actingAs($this->user('Student'))->get(route('adviser.reports.index'))->assertForbidden();

        $this->actingAs($this->adviser)
            ->get(route('adviser.reports.index'))
            ->assertOk()
            ->assertSee('Adviser Reports')
            ->assertSee('Attendance report')
            ->assertSee(route('adviser.reports.index'), false);
    }

    public function test_attendance_report_filters_records_and_calculates_summary(): void
    {
        $present = $this->user('Student', ['first_name' => 'Present', 'last_name' => 'Student']);
        $absent = $this->user('Student', ['first_name' => 'Absent', 'last_name' => 'Student']);
        $event = $this->event('Assembly');
        $otherEvent = $this->event('Sports Fest');
        $this->attendance($event, $present, 'present');
        $this->attendance($event, $absent, 'absent');
        $this->attendance($otherEvent, $present, 'late');

        $response = $this->actingAs($this->adviser)->get(route('adviser.reports.index', [
            'type' => 'attendance',
            'event_id' => $event->id,
        ]))->assertOk()->assertSee($present->full_name)->assertSee($absent->full_name);

        $this->assertSame(2, $response->viewData('report')['summary']['records']);
        $this->assertSame(1, $response->viewData('report')['summary']['attended']);
        $this->assertSame(50.0, $response->viewData('report')['summary']['rate']);
        $this->assertTrue($response->viewData('report')['rows']->every(
            fn (Attendance $attendance) => $attendance->event_id === $event->id
        ));
    }

    public function test_participation_score_and_ranking_reports_use_existing_operational_data(): void
    {
        $year = SchoolYear::create(['label' => '2026-2027']);
        $students = collect([$this->user('Student'), $this->user('Student')]);
        $team = Team::create(['school_year_id' => $year->id, 'name' => 'Green Falcons', 'color' => '#397565', 'is_active' => true]);
        $team->members()->attach($students->pluck('id'));
        $event = $this->event('Foundation Day');
        $category = ScoreCategory::create(['event_id' => $event->id, 'name' => 'Creativity', 'max_points' => 100, 'sort_order' => 1]);
        $this->attendance($event, $students[0], 'present');
        $this->attendance($event, $students[1], 'absent');
        Score::create(['event_id' => $event->id, 'team_id' => $team->id, 'score_category_id' => $category->id, 'points' => 92, 'recorded_by' => $this->adviser->id]);

        $participation = $this->actingAs($this->adviser)->get(route('adviser.reports.index', ['type' => 'participation']))->assertOk();
        $participationRow = $participation->viewData('report')['rows']->first();
        $this->assertSame(2, $participationRow->expected_count);
        $this->assertSame(2, $participationRow->recorded_count);
        $this->assertSame(1, $participationRow->attended_count);
        $this->assertSame(50.0, $participationRow->participation_rate);

        $scores = $this->get(route('adviser.reports.index', ['type' => 'scores', 'event_id' => $event->id]))
            ->assertOk()->assertSee('Green Falcons')->assertSee('Creativity');
        $this->assertSame(92.0, $scores->viewData('report')['summary']['points']);

        $rankings = $this->get(route('adviser.reports.index', ['type' => 'rankings', 'event_id' => $event->id]))
            ->assertOk()->assertSee('Green Falcons');
        $this->assertSame('Green Falcons', $rankings->viewData('report')['summary']['leader']);
        $this->assertSame(1, $rankings->viewData('report')['rows']->first()->rank);
    }

    public function test_csv_export_uses_filters_and_protects_spreadsheet_formulas(): void
    {
        $student = $this->user('Student', ['first_name' => '=SUM(1,1)', 'last_name' => 'Student']);
        $event = $this->event('Assembly');
        $this->attendance($event, $student, 'present');

        $response = $this->actingAs($this->adviser)->get(route('adviser.reports.export', [
            'type' => 'attendance',
            'event_id' => $event->id,
            'status' => 'present',
        ]))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('"Student ID",Student', $content);
        $this->assertStringContainsString("\"'=SUM(1,1)", $content);
        $this->assertStringContainsString('Assembly', $content);
    }

    public function test_score_category_must_belong_to_the_selected_event(): void
    {
        $event = $this->event('Assembly');
        $other = $this->event('Sports Fest');
        $category = ScoreCategory::create(['event_id' => $other->id, 'name' => 'Creativity', 'max_points' => 100]);

        $this->actingAs($this->adviser)
            ->from(route('adviser.reports.index', ['type' => 'scores']))
            ->get(route('adviser.reports.index', ['type' => 'scores', 'event_id' => $event->id, 'category_id' => $category->id]))
            ->assertRedirect(route('adviser.reports.index', ['type' => 'scores']))
            ->assertSessionHasErrors('category_id');
    }

    private function user(string $role, array $attributes = []): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->value('id'),
            'status' => $this->active->id,
            'must_change_password' => false,
            ...$attributes,
        ]);
    }

    private function event(string $title): Event
    {
        return Event::create([
            'title' => $title,
            'location' => 'Main Hall',
            'audience_type' => 'all_students',
            'start_at' => '2026-09-12 08:00:00',
            'end_at' => '2026-09-12 17:00:00',
            'created_by' => $this->adviser->id,
        ]);
    }

    private function attendance(Event $event, User $student, string $status): Attendance
    {
        return Attendance::create([
            'event_id' => $event->id,
            'user_id' => $student->id,
            'attendance_date' => '2026-09-12',
            'status' => $status,
            'checked_in_at' => in_array($status, Attendance::ATTENDED_STATUSES, true) ? '2026-09-12 08:00:00' : null,
            'recorded_by' => $this->adviser->id,
        ]);
    }
}
