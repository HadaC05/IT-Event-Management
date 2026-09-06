<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Team;
use App\Models\User;
use App\Models\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $adviser;

    private UserStatus $active;

    private UserStatus $inactive;

    private SchoolYear $schoolYear;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['SBO Adviser', 'SBO', 'Faculty', 'Student'] as $role) {
            Role::create(['name' => $role]);
        }

        $this->active = UserStatus::create(['label' => 'active']);
        $this->inactive = UserStatus::create(['label' => 'inactive']);
        $this->schoolYear = SchoolYear::create(['label' => '2026-2027']);
        $this->adviser = User::factory()->create([
            'role_id' => Role::where('name', 'SBO Adviser')->value('id'),
            'status' => $this->active->id,
        ]);
    }

    public function test_only_sbo_adviser_can_access_team_management(): void
    {
        $student = $this->student();

        $this->actingAs($student)->get(route('adviser.teams.index'))->assertForbidden();
        $this->actingAs($this->adviser)->get(route('adviser.teams.index'))
            ->assertOk()
            ->assertSee('Tribe Management')
            ->assertSee('Create Tribe')
            ->assertDontSee('Search tribe or member');
    }

    public function test_empty_state_directs_adviser_to_students_before_creating_a_tribe(): void
    {
        $this->actingAs($this->adviser)->get(route('adviser.teams.index'))
            ->assertOk()
            ->assertSee('Students need to be added first')
            ->assertSee('Manage Students')
            ->assertDontSee('Create Tribe')
            ->assertDontSee('Search tribe or member');
    }

    public function test_create_workflow_is_context_aware_and_preselects_the_school_year(): void
    {
        $this->actingAs($this->adviser)->get(route('adviser.teams.create'))
            ->assertOk()
            ->assertSeeInOrder(['1', 'Tribe Details', '2', 'Choose Members'])
            ->assertSee('Live Tribe Preview')
            ->assertSee('Current school year selected automatically')
            ->assertSee('Go to User Management')
            ->assertSee('Green')
            ->assertSee('Custom')
            ->assertDontSee('Search by name, student ID, or year level')
            ->assertSee('data-submit-tribe disabled', false);

        $this->student();

        $this->get(route('adviser.teams.create'))
            ->assertOk()
            ->assertSee('Search by name, student ID, or year level')
            ->assertSee('<span data-selected-count>0</span>&nbsp;selected', false);
    }

    public function test_adviser_can_create_a_tribe_and_assign_active_students(): void
    {
        $students = collect([$this->student(), $this->student()]);

        $this->actingAs($this->adviser)->post(route('adviser.teams.store'), [
            'name' => 'Green Falcons',
            'school_year_id' => $this->schoolYear->id,
            'color' => '#41B06E',
            'member_ids' => $students->pluck('id')->all(),
        ])->assertRedirect(route('adviser.teams.index'))
            ->assertSessionHasNoErrors();

        $team = Team::where('name', 'Green Falcons')->firstOrFail();

        $this->assertTrue($team->is_active);
        $this->assertEqualsCanonicalizing($students->pluck('id')->all(), $team->members()->pluck('users.id')->all());
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $this->adviser->id,
            'action' => 'team_created',
        ]);
    }

    public function test_only_active_students_can_be_assigned(): void
    {
        $faculty = User::factory()->create([
            'role_id' => Role::where('name', 'Faculty')->value('id'),
            'status' => $this->active->id,
        ]);
        $inactiveStudent = $this->student($this->inactive);

        $this->actingAs($this->adviser)->post(route('adviser.teams.store'), [
            'name' => 'Invalid Tribe',
            'school_year_id' => $this->schoolYear->id,
            'color' => '#141E46',
            'member_ids' => [$faculty->id, $inactiveStudent->id],
        ])->assertSessionHasErrors('member_ids');

        $this->assertDatabaseMissing('teams', ['name' => 'Invalid Tribe']);
    }

    public function test_a_student_can_only_join_one_tribe_per_school_year(): void
    {
        $student = $this->student();
        $firstTeam = Team::create([
            'name' => 'Blue Sharks',
            'school_year_id' => $this->schoolYear->id,
            'color' => '#2563EB',
        ]);
        $firstTeam->members()->attach($student);

        $this->actingAs($this->adviser)->post(route('adviser.teams.store'), [
            'name' => 'Red Lions',
            'school_year_id' => $this->schoolYear->id,
            'color' => '#DC2626',
            'member_ids' => [$student->id],
        ])->assertSessionHasErrors('member_ids');

        $nextSchoolYear = SchoolYear::create(['label' => '2027-2028']);
        $this->post(route('adviser.teams.store'), [
            'name' => 'Red Lions',
            'school_year_id' => $nextSchoolYear->id,
            'color' => '#DC2626',
            'member_ids' => [$student->id],
        ])->assertRedirect(route('adviser.teams.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $student->teams()->count());
    }

    public function test_adviser_can_edit_filter_and_deactivate_a_tribe(): void
    {
        $firstStudent = $this->student();
        $replacement = $this->student();
        $team = Team::create([
            'name' => 'Original Tribe',
            'school_year_id' => $this->schoolYear->id,
            'color' => '#41B06E',
        ]);
        $team->members()->attach($firstStudent);

        $this->actingAs($this->adviser)->put(route('adviser.teams.update', $team), [
            'name' => 'Emerald Eagles',
            'school_year_id' => $this->schoolYear->id,
            'color' => '#059669',
            'member_ids' => [$replacement->id],
        ])->assertRedirect(route('adviser.teams.index'))
            ->assertSessionHasNoErrors();

        $this->patch(route('adviser.teams.status', $team))->assertSessionHasNoErrors();
        $this->get(route('adviser.teams.index', [
            'search' => 'Emerald',
            'school_year' => $this->schoolYear->id,
            'status' => 'inactive',
        ]))->assertOk()
            ->assertSee('Emerald Eagles')
            ->assertSee($replacement->full_name)
            ->assertSee('Search tribe or member');

        $team->refresh();
        $this->assertFalse($team->is_active);
        $this->assertSame('#059669', $team->color);
        $this->assertEqualsCanonicalizing([$replacement->id], $team->members()->pluck('users.id')->all());
        $this->assertSame(1, ActivityLog::where('action', 'team_updated')->count());
        $this->assertSame(1, ActivityLog::where('action', 'team_status_changed')->count());
    }

    private function student(?UserStatus $status = null): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', 'Student')->value('id'),
            'status' => ($status ?? $this->active)->id,
        ]);
    }
}
