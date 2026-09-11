<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(StudentPortalService $portal): View
    {
        $user = request()->user()->load(['yearLevel', 'teams.schoolYear']);

        return view('student.profile.show', [
            'user' => $user,
            'posts' => $user->posts()->approved()->with('event')->latest('reviewed_at')->paginate(10),
            'myPosts' => $user->posts()->whereIn('status', ['pending', 'rejected'])->with('event')->latest()->get(),
            'events' => $portal->eligibleEvents($user, true),
        ]);
    }

    public function edit(): View
    {
        return view('student.profile.edit', ['user' => request()->user()->load(['yearLevel', 'teams.schoolYear'])]);
    }

    public function settings(): View
    {
        return view('student.settings', ['user' => request()->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'middle_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'], 'username' => ['required', 'string', 'max:80', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)], 'bio' => ['nullable', 'string', 'max:280'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'], 'remove_photo' => ['nullable', 'boolean'],
        ]);
        unset($data['profile_photo'], $data['remove_photo']);
        if ($request->boolean('remove_photo') && $user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $data['profile_photo_path'] = null;
        }
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $data['profile_photo_path'] = $request->file('profile_photo')->store('profiles', 'public');
        }
        $user->update($data);

        return redirect()->route('student.profile.show')->with('success', 'Profile updated.');
    }
}
