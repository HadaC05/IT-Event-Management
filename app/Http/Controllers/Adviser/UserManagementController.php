<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Role;
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

class UserManagementController extends Controller
{
    private const MANAGEABLE_ROLES = ['SBO Adviser', 'SBO', 'SBO Officer', 'Faculty', 'Student'];

    private const CREATABLE_ROLES = ['SBO Adviser', 'Faculty', 'Student'];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', Rule::exists('roles', 'name')],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);

        $users = User::query()
            ->with(['role', 'userStatus', 'officerTeam', 'assignedEvents' => fn ($query) => $query->orderBy('start_at')])
            ->when($validated['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $term = '%'.addcslashes($search, '%_\\').'%';
                    $query->where('first_name', 'like', $term)
                        ->orWhere('middle_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('id_number', 'like', $term);
                });
            })
            ->when($validated['role'] ?? null, fn (Builder $query, string $role) => $query->whereHas('role', fn (Builder $query) => $query->where('name', $role)))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->whereHas('userStatus', fn (Builder $query) => $query->where('label', $status)))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(10)
            ->withQueryString();

        return view('adviser.users.index', [
            'users' => $users,
            'roles' => Role::query()
                ->whereHas('users')
                ->orderBy('name')
                ->get(),
            'creatableRoles' => Role::whereIn('name', self::CREATABLE_ROLES)->orderBy('name')->get(),
            'yearLevels' => YearLevel::orderBy('id')->get(),
            'userSummary' => [
                'total' => User::count(),
                'students' => User::whereHas('role', fn (Builder $query) => $query->where('name', 'Student'))->count(),
                'faculty' => User::whereHas('role', fn (Builder $query) => $query->where('name', 'Faculty'))->count(),
                'sbo' => User::whereHas('role', fn (Builder $query) => $query->whereIn('name', ['SBO', 'SBO Officer']))->count(),
                'active' => User::whereHas('userStatus', fn (Builder $query) => $query->where('label', 'active'))->count(),
                'inactive' => User::whereHas('userStatus', fn (Builder $query) => $query->where('label', 'inactive'))->count(),
            ],
            'events' => Event::query()
                ->where(function (Builder $query) {
                    $query->whereDoesntHave('status')
                        ->orWhereHas('status', fn (Builder $query) => $query->where('label', 'active'));
                })
                ->orderBy('start_at')
                ->get(),
            'teams' => Team::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedUser($request);
        $data['status'] = UserStatus::firstOrCreate(['label' => 'active'])->id;

        $user = DB::transaction(function () use ($request, $data) {
            $user = User::create($data);
            $this->log($request, 'user_created', "{$user->full_name} was added as {$user->role->name}.", $user);

            return $user;
        });

        return to_route('adviser.users.index')->with('success', "{$user->full_name} was added successfully.");
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($user);
        $data = $this->validatedUser($request, $user);

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        DB::transaction(function () use ($request, $user, $data) {
            $user->update($data);
            $user->load('role');

            if ($user->role?->name === 'Student') {
                foreach ($user->assignedEvents()->get() as $event) {
                    $user->assignedEvents()->detach($event);
                    ActivityLog::create([
                        'actor_id' => $request->user()->id,
                        'subject_user_id' => $user->id,
                        'event_id' => $event->id,
                        'action' => 'event_unassigned',
                        'description' => "{$user->full_name} was unassigned from {$event->title} after a role change.",
                    ]);
                }
            }
        });

        return to_route('adviser.users.index', $request->only(['search', 'role', 'status']))
            ->with('success', "{$user->fresh()->full_name} was updated successfully.");
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $this->ensureManageable($user);
        $current = $user->userStatus?->label ?? 'active';
        $next = $current === 'active' ? 'inactive' : 'active';
        $verb = $next === 'active' ? 'activated' : 'deactivated';
        $user->update(['status' => UserStatus::firstOrCreate(['label' => $next])->id]);
        $this->log($request, 'user_status_changed', "{$user->full_name} was {$verb}.", $user);

        return back()->with('success', "{$user->full_name} is now {$next}.");
    }

    private function validatedUser(Request $request, ?User $user = null): array
    {
        $roleIds = Role::whereIn('name', $user ? self::MANAGEABLE_ROLES : self::CREATABLE_ROLES)->pluck('id')->all();
        $roleName = Role::whereKey($request->input('role_id'))->value('name');
        $idNumberRules = $roleName === 'Student'
            ? ['required', 'string', 'regex:/^02-\d{4}-\d{6}$/', Rule::unique('users', 'id_number')->ignore($user)]
            : ['nullable', 'string', 'max:255', Rule::unique('users', 'id_number')->ignore($user)];
        $emailRules = ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)];

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'id_number' => $idNumberRules,
            'username' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users')->ignore($user)],
            'email' => $emailRules,
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', Rule::in($roleIds)],
            'year_level' => ['nullable', Rule::exists('year_levels', 'id')],
        ], [
            'id_number.regex' => 'Student ID numbers must use the format 02-xxxx-xxxxxx (12 digits).',
        ]);

        if ($roleName !== 'SBO Officer') {
            $data['officer_team_id'] = null;
        }

        return $data;
    }

    private function ensureManageable(User $user): void
    {
        abort_unless(in_array($user->role?->name, self::MANAGEABLE_ROLES, true), 403);
    }

    private function log(Request $request, string $action, string $description, User $subject): void
    {
        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'subject_user_id' => $subject->id,
            'action' => $action,
            'description' => $description,
        ]);
    }
}
