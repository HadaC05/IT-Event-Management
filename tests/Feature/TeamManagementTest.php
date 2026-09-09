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
            ->assertSee('Team Management')
            ->assertSee('Create Tribe')
            ->assertDontSee('Search tribe or member');
    }

    public function test_tribes_can_be_created_before_students_are_added(): void
    {
        $this->actingAs($this->adviser)->get(route('adviser.teams.index'))
            ->assertOk()
            ->assertSee('No tribes created yet')
            ->assertSee('Create Tribe')
            ->assertDontSee('Search tribe or member');
    }

    public function test_create_workflow_only_collects_tribe_details(): void
    {
        $this->actingAs($this->adviser)->get(route('adviser.teams.create'))
            ->assertRedirect(route('adviser.teams.index', ['create' => 1]));

        $this->student();

        $this->get(route('adviser.teams.index', ['create' => 1]))
            ->assertOk()
            ->assertSee('Tribe Details')
            ->assertSee('Live Tribe Preview')
            ->assertSee('Current school year selected automatically')
            ->assertSee('Green')
            ->assertSee('Custom')
            ->assertDontSee('Choose Members')
            ->assertDontSee('Search by name, student ID, or year level')
            ->assertSee('id="create-team-dialog"', false)
            ->assertSee('showModal()', false);
    }

    public function test_edit_workflow_shows_a_students_existing_tribe(): void
    {
        $student = $this->student();
        $existingTeam = Team::create([
            'name' => 'Blue Sharks',
            'school_year_id' => $this->schoolYear->id,
            'color' => '#2563EB',
        ]);
        $existingTeam->members()->attach($student);

        $otherTeam = Team::create([
            'name' => 'Red Lions',
            'school_year_id' => SchoolYear::create(['label' => '2027-2028'])->id,
            'color' => '#DC2626',
        ]);

        $this->actingAs($this->adviser)
            ->get(route('adviser.teams.edit', $otherTeam))
            ->assertOk()
            ->assertSee('Blue Sharks · SY '.$this->schoolYear->label);
    }

    public function test_creating_a_tribe_does_not_assign_students(): void
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
        $this->assertSame(0, $team->members()->count());
        $this->assertDatabaseHas('activity_logs', [
            'actor_id' => $this->adviser->id,
            'action' => 'team_created',
        ]);
    }

    public function test_adviser_can_randomly_distribute_students_evenly_across_active_tribes(): void
    {
        $students = collect([
            $this->student(), $this->student(), $this->student(),
            $this->student(), $this->student(), $this->student(),
        ]);
        $firstTeam = Team::create([
            'name' => 'Blue Sharks',
            'school_year_id' => $this->schoolYear->id,
            'color' => '#2563EB',
        ]);
        $secondTeam = Team::create([
            'name' => 'Red Lions',
            'school_year_id' => $this->schoolYear->id,
            'color' => '#DC2626',
        ]);
        $firstTeam->members()->attach($students);

        $this->actingAs($this->adviser)
            ->post(route('adviser.teams.randomize'), ['school_year_id' => $this->schoolYear->id])
            ->assertRedirect(route('adviser.teams.index', ['school_year' => $this->schoolYear->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(3, $firstTeam->members()->count());
        $this->assertSame(3, $secondTeam->members()->count());
        $this->assertEqualsCanonicalizing(
            $students->pluck('id')->all(),
            $firstTeam->members()->pluck('users.id')->merge($secondTeam->members()->pluck('users.id'))->all()
        );
        $this->assertDatabaseHas('activity_logs', ['action' => 'team_members_randomized']);
        $this->assertNotNull($this->schoolYear->fresh()->teams_randomized_at);

        $this->post(route('adviser.teams.randomize'), ['school_year_id' => $this->schoolYear->id])
            ->assertSessionHasErrors('randomize', null, 'randomize');

        $this->assertSame(1, ActivityLog::where('action', 'team_members_randomized')->count());
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
        $nextTeam = Team::create([
            'name' => 'Red Lions',
            'school_year_id' => $nextSchoolYear->id,
            'color' => '#DC2626',
        ]);

        $this->put(route('adviser.teams.update', $nextTeam), [
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
