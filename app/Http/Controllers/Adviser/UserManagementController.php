<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\Role;
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
    private const MANAGEABLE_ROLES = ['SBO', 'Faculty', 'Student'];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(self::MANAGEABLE_ROLES)],
        ]);

        $users = User::query()
            ->with(['role', 'userStatus', 'assignedEvents' => fn ($query) => $query->orderBy('start_at')])
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
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(10)
            ->withQueryString();

        return view('adviser.users.index', [
            'users' => $users,
            'roles' => Role::whereIn('name', self::MANAGEABLE_ROLES)
                ->orderByRaw("CASE name WHEN 'SBO' THEN 1 WHEN 'Faculty' THEN 2 WHEN 'Student' THEN 3 ELSE 4 END")
                ->get(),
            'yearLevels' => YearLevel::orderBy('id')->get(),
            'events' => Event::query()
                ->where(function (Builder $query) {
                    $query->whereDoesntHave('status')
                        ->orWhereHas('status', fn (Builder $query) => $query->where('label', 'active'));
                })
                ->orderBy('start_at')
                ->get(),
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

        return to_route('adviser.users.index', $request->only(['search', 'role']))
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
        $roleIds = Role::whereIn('name', self::MANAGEABLE_ROLES)->pluck('id')->all();

        return $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'id_number' => ['nullable', 'string', 'max:255', Rule::unique('users')->ignore($user)],
            'username' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['required', Rule::in($roleIds)],
            'year_level' => ['nullable', Rule::exists('year_levels', 'id')],
        ]);
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
