<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostReaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostReactionController extends Controller
{
    public function update(Request $request, Post $post): RedirectResponse
    {
        $this->authorize('interact', $post);

        $data = $request->validate([
            'reaction' => ['required', Rule::in(PostReaction::TYPES)],
        ]);

        $reaction = $post->reactions()->where('user_id', $request->user()->id)->first();

        if ($reaction?->type === $data['reaction']) {
            $reaction->delete();

            return back()->with('success', 'Reaction removed.');
        }

        $post->reactions()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['type' => $data['reaction']],
        );

        return back()->with('success', 'Reaction updated.');
    }
}
