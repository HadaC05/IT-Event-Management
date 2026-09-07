<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountPasswordReset;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AccountPasswordResetController extends Controller
{
    public function create(): View { return view('auth.forgot-password'); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'account_type' => ['required', Rule::in(['Student', 'SBO Officer'])]]);
        $user = User::query()->whereHas('role', fn (Builder $query) => $query->where('name', $data['account_type']))
            ->whereHas('studentProfile', fn (Builder $query) => $query->where('email', $data['email']))->first();
        if ($user) {
            $token = Str::random(64);
            DB::table('account_password_reset_tokens')->updateOrInsert(['user_id' => $user->id], ['token' => Hash::make($token), 'created_at' => now()]);
            $user->notify(new AccountPasswordReset($token, $data['account_type']));
        }
        return back()->with('success', 'If that account exists, an account-specific reset link has been sent.');
    }

    public function edit(Request $request, User $user, string $token): View
    {
        abort_unless($this->validToken($user->id, $token), 422, 'This reset link is invalid or expired.');
        return view('auth.reset-password', compact('user', 'token'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer'], 'token' => ['required'], 'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()]]);
        abort_unless($this->validToken((int) $data['user_id'], $data['token']), 422, 'This reset link is invalid or expired.');
        $user = User::findOrFail($data['user_id']);
        DB::transaction(function () use ($user, $data) { $user->update(['password' => $data['password'], 'must_change_password' => false]); DB::table('account_password_reset_tokens')->where('user_id', $user->id)->delete(); });
        return to_route('home', ['login' => 1])->with('success', 'Password reset. Sign in to the selected account.');
    }

    private function validToken(int $userId, string $token): bool
    {
        $record = DB::table('account_password_reset_tokens')->where('user_id', $userId)->first();
        return $record
            && Carbon::parse($record->created_at)->greaterThanOrEqualTo(now()->subMinutes(60))
            && Hash::check($token, $record->token);
    }
}
