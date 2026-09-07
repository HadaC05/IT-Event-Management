<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\SboOfficerAssignment;
use App\Models\Team;
use App\Models\User;
use App\Models\UserStatus;
use App\Models\YearLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OfficerManagementController extends Controller
{
    public function index(): View
    {
        $students = User::query()
            ->with(['studentProfile.yearLevel', 'teams.schoolYear'])
            ->whereHas('role', fn (Builder $query) => $query->where('name', 'Student'))
            ->whereHas('userStatus', fn (Builder $query) => $query->where('label', 'active'))
            ->whereNotNull('student_profile_id')
            ->orderBy('username')->get()->sortBy(fn (User $student) => $student->full_name)->values();

        $assignments = SboOfficerAssignment::query()
            ->with(['studentProfile', 'officerAccount.userStatus', 'team', 'assignedBy'])
            ->latest('assigned_at')->paginate(15)->withQueryString();

        return view('adviser.officers.index', [
            'students' => $students,
            'assignments' => $assignments,
            'teams' => Team::where('is_active', true)->orderBy('name')->get(),
            'yearLevels' => YearLevel::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_user_id' => ['required', Rule::exists('users', 'id')],
            'team_id' => ['required', Rule::exists('teams', 'id')->where(fn ($query) => $query->where('is_active', true))],
            'position' => ['required', 'string', 'max:100'],
            'term' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $student = User::with(['role', 'studentProfile'])->findOrFail($data['student_user_id']);
        abort_unless($student->role?->name === 'Student' && $student->studentProfile, 422, 'Select an existing student account.');
        $existingOfficer = User::where('student_profile_id', $student->student_profile_id)
            ->where('role_id', Role::where('name', 'SBO Officer')->value('id'))->first();
        $request->validate(['username' => [Rule::unique('users', 'username')->ignore($existingOfficer)]]);

        DB::transaction(function () use ($request, $student, $data) {
            $profile = $student->studentProfile()->lockForUpdate()->firstOrFail();
            $duplicate = SboOfficerAssignment::where('student_profile_id', $profile->id)
                ->where('term', $data['term'])->where('status', 'Active')->lockForUpdate()->exists();
            abort_if($duplicate, 422, 'This student already has an active SBO Officer assignment for this term.');

            $officerRole = Role::where('name', 'SBO Officer')->firstOrFail();
            $officer = User::where('student_profile_id', $profile->id)->where('role_id', $officerRole->id)->lockForUpdate()->first();
            $accountData = [
                'student_profile_id' => $profile->id,
                'role_id' => $officerRole->id,
                'username' => $data['username'],
                'password' => $data['password'],
                'status' => UserStatus::firstOrCreate(['label' => 'active'])->id,
                'officer_team_id' => $data['team_id'],
                'must_change_password' => true,
            ];
            $officer ? $officer->update($accountData) : $officer = User::create($accountData);

            $assignment = SboOfficerAssignment::create([
                'student_profile_id' => $profile->id,
                'officer_user_id' => $officer->id,
                'team_id' => $data['team_id'],
                'position' => $data['position'],
                'term' => $data['term'],
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
                'status' => 'Active',
            ]);
            ActivityLog::create(['actor_id' => $request->user()->id, 'subject_user_id' => $officer->id, 'student_profile_id' => $profile->id, 'officer_assignment_id' => $assignment->id, 'action' => 'officer_assigned', 'acting_role' => $request->user()->role?->name, 'description' => "{$profile->full_name} was assigned as {$assignment->position} for {$assignment->term}."]);
        });

        return to_route('adviser.officers.index')->with('success', 'SBO Officer account created. Share the temporary credentials securely.');
    }

    public function unassign(Request $request, SboOfficerAssignment $assignment): RedirectResponse
    {
        $this->endAssignments($request, collect([$assignment->id]));
        return back()->with('success', 'The officer was unassigned and their officer login was disabled.');
    }

    public function batchUnassign(Request $request): RedirectResponse
    {
        $ids = collect($request->validate(['assignment_ids' => ['required', 'array', 'min:1'], 'assignment_ids.*' => ['integer', 'distinct', Rule::exists('sbo_officer_assignments', 'id')]])['assignment_ids']);
        $count = $this->endAssignments($request, $ids);
        return back()->with('success', "{$count} officer ".($count === 1 ? 'was' : 'accounts were').' unassigned and disabled.');
    }

    private function endAssignments(Request $request, $ids): int
    {
        return DB::transaction(function () use ($request, $ids) {
            $assignments = SboOfficerAssignment::whereIn('id', $ids)->where('status', 'Active')->lockForUpdate()->get();
            $inactive = UserStatus::firstOrCreate(['label' => 'inactive'])->id;
            foreach ($assignments as $assignment) {
                $assignment->update(['status' => 'Inactive', 'ended_at' => now(), 'ended_by' => $request->user()->id]);
                $assignment->officerAccount()->update(['status' => $inactive, 'officer_team_id' => null]);
                ActivityLog::create(['actor_id' => $request->user()->id, 'subject_user_id' => $assignment->officer_user_id, 'student_profile_id' => $assignment->student_profile_id, 'officer_assignment_id' => $assignment->id, 'action' => 'officer_unassigned', 'acting_role' => $request->user()->role?->name, 'description' => "Officer assignment {$assignment->position} ({$assignment->term}) was ended."]);
            }
            return $assignments->count();
        });
    }
}
