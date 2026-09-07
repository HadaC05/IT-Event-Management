<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create(): RedirectResponse
    {
        return redirect()->route('home', ['login' => 1]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $authenticated = false;
        if (filter_var($validated['login'], FILTER_VALIDATE_EMAIL)) {
            $candidates = \App\Models\User::query()->with('studentProfile')
                ->where('email', $validated['login'])
                ->orWhereHas('studentProfile', fn ($query) => $query->where('email', $validated['login']))
                ->get();
            $account = $candidates->first(fn ($candidate) => Hash::check($validated['password'], $candidate->password));
            if ($account) {
                Auth::login($account, $request->boolean('remember'));
                $authenticated = true;
            }
        } else {
            $authenticated = Auth::attempt(['username' => $validated['login'], 'password' => $validated['password']], $request->boolean('remember'));
        }

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'login' => 'The username/email or password is incorrect.',
            ]);
        }

        if (Auth::user()->userStatus?->label === 'inactive') {
            Auth::logout();

            throw ValidationException::withMessages([
                'login' => 'This account is inactive. Please contact the SBO Adviser.',
            ]);
        }

        $request->session()->regenerate();
        $redirectUrl = Auth::user()->must_change_password
            ? route('password.change')
            : (Auth::user()->isSboOfficer() ? route('officer.attendance.index') : route('dashboard'));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Signed in successfully, '.Auth::user()->first_name.'. Opening your portal now.',
                'redirect_url' => $redirectUrl,
            ]);
        }

        return redirect()->intended($redirectUrl);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
