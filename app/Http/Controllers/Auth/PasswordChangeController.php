<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordChangeController extends Controller
{
    public function edit(): View { return view('auth.change-password'); }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        $request->user()->update(['password' => Hash::make($data['password']), 'must_change_password' => false]);
        return redirect()->route($request->user()->isSboOfficer() ? 'officer.attendance.index' : 'dashboard')->with('success', 'Your password was changed successfully.');
    }
}
