<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Score;
use App\Models\Team;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdviserDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    private User $adviser;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SBO Adviser', 'SBO', 'Faculty', 'Student'] as $role) {
            Role::create(['name' => $role]);
        }

        $active = UserStatus::create(['label' => 'active']);
        $this->adviser = User::factory()->create([
            'role_id' => Role::where('name', 'SBO Adviser')->value('id'),
            'status' => $active->id,
        ]);
    }

    public function test_dashboard_shows_clear_empty_states_before_metrics_are_recorded(): void
    {
        $this->actingAs($this->adviser)->get('/dashboard')
            ->assertOk()
            ->assertSee('Attendance Today')
            ->assertSee('Students Present')
            ->assertSee('Upcoming Events')
            ->assertSee('People Overview')
            ->assertSee('No attendance recorded yet')
            ->assertSee('No recent attendance')
            ->assertSee('No team rankings yet');
    }

    public function test_dashboard_calculates_attendance_scores_and_tied_team_rankings(): void
    {
        $event = Event::create([
            'title' => 'IT Days',
            'location' => 'Main Hall',
            'start_at' => now()->startOfDay()->addHours(8),
            'end_at' => now()->startOfDay()->addHours(17),
            'created_by' => $this->adviser->id,
        ]);
        $students = User::factory()->count(3)->create([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => UserStatus::where('label', 'active')->value('id'),
        ]);

        foreach (['present', 'late', 'absent'] as $index => $status) {
            Attendance::create([
                'event_id' => $event->id,
                'user_id' => $students[$index]->id,
                'status' => $status,
                'recorded_by' => $this->adviser->id,
            ]);
        }

        $previousEvent = Event::create([
            'title' => 'Orientation',
            'start_at' => now()->subDays(2)->startOfDay()->addHours(8),
            'end_at' => now()->subDays(2)->startOfDay()->addHours(12),
            'created_by' => $this->adviser->id,
        ]);
        foreach (['present', 'absent'] as $index => $status) {
            Attendance::create([
                'event_id' => $previousEvent->id,
                'user_id' => $students[$index]->id,
                'status' => $status,
                'recorded_by' => $this->adviser->id,
            ]);
        }

        $schoolYear = SchoolYear::create(['label' => '2026']);
        $alpha = Team::create(['name' => 'Alpha Tribe', 'school_year_id' => $schoolYear->id]);
        $beta = Team::create(['name' => 'Beta Tribe', 'school_year_id' => $schoolYear->id]);
        $gamma = Team::create(['name' => 'Gamma Tribe', 'school_year_id' => $schoolYear->id]);
        $alpha->members()->attach($students[0]);

        foreach ([[$alpha, 120], [$beta, 90], [$gamma, 90]] as [$team, $points]) {
            Score::create([
                'event_id' => $event->id,
                'team_id' => $team->id,
                'points' => $points,
                'recorded_by' => $this->adviser->id,
            ]);
        }

        $response = $this->actingAs($this->adviser)->get('/dashboard')
            ->assertOk()
            ->assertSee('66.7%')
            ->assertSee('↑ 16.7%')
            ->assertSee('Compared with Orientation')
            ->assertSee('2 of 3 marked records attended')
            ->assertSee('Main Hall')
            ->assertSee('Recent Attendance')
            ->assertSeeInOrder(['Alpha Tribe', '120', 'Beta Tribe', '90', 'Gamma Tribe', '90']);

        $this->assertSame(1, $response->viewData('stats')['students_present']);
        $this->assertSame(1, substr_count($response->getContent(), 'aria-label="Rank 1"'));
        $this->assertSame(2, substr_count($response->getContent(), 'aria-label="Rank 2"'));
    }
}
