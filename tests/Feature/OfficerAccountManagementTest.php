<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\SboOfficerAssignment;
use App\Models\StudentProfile;
use App\Models\Team;
use App\Models\User;
use App\Models\UserStatus;
use App\Models\YearLevel;
use App\Notifications\AccountPasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OfficerAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $adviser;
    private User $student;
    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['SBO Adviser', 'SBO Officer', 'Faculty', 'Student'] as $name) Role::firstOrCreate(['name' => $name]);
        $active = UserStatus::create(['label' => 'active']);
        UserStatus::create(['label' => 'inactive']);
        $this->adviser = User::factory()->create(['role_id' => Role::where('name', 'SBO Adviser')->value('id'), 'status' => $active->id]);
        $yearLevel = YearLevel::forceCreate(['label' => 'Third Year']);
        $profile = StudentProfile::create(['student_id' => '02-2026-000001', 'first_name' => 'Juan', 'last_name' => 'Cruz', 'email' => 'juan@example.test', 'year_level_id' => $yearLevel->id]);
        $this->student = User::factory()->create(['student_profile_id' => $profile->id, 'role_id' => Role::where('name', 'Student')->value('id'), 'status' => $active->id, 'username' => '02-2026-000001', 'password' => 'StudentPass!1']);
        $year = SchoolYear::create(['label' => '2026-2027']);
        $this->team = Team::create(['school_year_id' => $year->id, 'name' => 'Emerald', 'color' => '#397565']);
    }

    private function assign(string $username = 'juan.sbo', string $term = '2026-2027')
    {
        return $this->actingAs($this->adviser)->post(route('adviser.officers.store'), [
            'student_user_id' => $this->student->id, 'team_id' => $this->team->id,
            'position' => 'Attendance Officer', 'term' => $term, 'username' => $username,
            'password' => 'OfficerPass!1', 'password_confirmation' => 'OfficerPass!1',
        ]);
    }

    public function test_assignment_modal_can_search_students_and_filter_by_year_level(): void
    {
        $this->actingAs($this->adviser)
            ->get(route('adviser.officers.index'))
            ->assertOk()
            ->assertSee('Name, ID, or email')
            ->assertSee('All year levels')
            ->assertSee('Third Year')
            ->assertSee('data-officer-student-search', false)
            ->assertSee('data-officer-year-filter', false)
            ->assertSee('data-year="1"', false);
    }

    public function test_linked_student_and_officer_accounts_are_separate(): void
    {
        $this->assign()->assertRedirect(route('adviser.officers.index'));
        $officer = User::where('username', 'juan.sbo')->firstOrFail();
        $this->assertSame($this->student->student_profile_id, $officer->student_profile_id);
        $this->assertNotSame($this->student->username, $officer->username);
        $this->assertTrue(Hash::check('StudentPass!1', $this->student->password));
        $this->assertTrue(Hash::check('OfficerPass!1', $officer->password));
        $this->assertDatabaseCount('student_profiles', 1);
        $this->assertTrue($officer->must_change_password);
    }

    public function test_duplicate_active_assignment_for_the_same_term_is_rejected(): void
    {
        $this->assign();
        $this->assign('juan.sbo', '2026-2027')->assertStatus(422);
        $this->assertSame(1, SboOfficerAssignment::where('status', 'Active')->count());
    }

    public function test_batch_unassignment_disables_only_officer_accounts_and_keeps_history(): void
    {
        $this->assign();
        $assignment = SboOfficerAssignment::firstOrFail();
        $this->actingAs($this->adviser)->patch(route('adviser.officers.batch-unassign'), ['assignment_ids' => [$assignment->id]])->assertSessionHasNoErrors();
        $this->assertSame('Inactive', $assignment->fresh()->status);
        $this->assertNotNull($assignment->fresh()->ended_at);
        $this->assertSame('inactive', $assignment->officerAccount->fresh()->userStatus->label);
        $this->assertSame('active', $this->student->fresh()->userStatus->label);
    }

    public function test_temporary_password_blocks_portal_until_changed(): void
    {
        $this->assign();
        $officer = User::where('username', 'juan.sbo')->firstOrFail();
        $this->actingAs($officer)->get(route('officer.attendance.index'))->assertRedirect(route('password.change'));
        $this->put(route('password.update'), ['current_password' => 'OfficerPass!1', 'password' => 'NewOfficer!2', 'password_confirmation' => 'NewOfficer!2'])->assertRedirect(route('officer.attendance.index'));
        $this->assertFalse($officer->fresh()->must_change_password);
    }

    public function test_each_login_opens_only_its_role_portal(): void
    {
        $this->assign();
        $officer = User::where('username', 'juan.sbo')->firstOrFail();
        $officer->update(['must_change_password' => false]);
        $this->post(route('logout'));

        $this->post('/login', ['login' => 'juan@example.test', 'password' => 'StudentPass!1'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('officer.attendance.index'))->assertForbidden();
        $this->post(route('logout'));

        $this->post('/login', ['login' => 'juan@example.test', 'password' => 'OfficerPass!1'])
            ->assertRedirect(route('officer.attendance.index'));
        $this->get(route('adviser.users.index'))->assertForbidden();
    }

    public function test_password_reset_notification_targets_only_selected_account(): void
    {
        Notification::fake();
        $this->assign();
        $officer = User::where('username', 'juan.sbo')->firstOrFail();
        $this->post(route('logout'));
        $this->post(route('password.email'), ['email' => 'juan@example.test', 'account_type' => 'SBO Officer'])->assertSessionHas('success');
        $resetToken = null;
        Notification::assertSentTo($officer, AccountPasswordReset::class, function ($notification) use (&$resetToken) {
            $resetToken = $notification->token;
            return $notification->accountType === 'SBO Officer';
        });
        Notification::assertNotSentTo($this->student, AccountPasswordReset::class);
        $this->assertDatabaseHas('account_password_reset_tokens', ['user_id' => $officer->id]);
        $this->assertDatabaseMissing('account_password_reset_tokens', ['user_id' => $this->student->id]);

        $this->get(route('password.reset', ['user' => $officer, 'token' => $resetToken]))->assertOk()->assertSee('SBO Officer');
        $this->post(route('password.reset.update'), ['user_id' => $officer->id, 'token' => $resetToken, 'password' => 'ResetOfficer!3', 'password_confirmation' => 'ResetOfficer!3'])->assertRedirect();
        $this->assertTrue(Hash::check('ResetOfficer!3', $officer->fresh()->password));
        $this->assertTrue(Hash::check('StudentPass!1', $this->student->fresh()->password));
    }
}
