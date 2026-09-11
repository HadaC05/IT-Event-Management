<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EventFeatureController extends Controller
{
    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('feature', $event);

        $data = $request->validate([
            'is_featured' => ['required', 'boolean'],
            'featured_order' => ['nullable', 'integer', 'min:1', 'max:999'],
            'featured_until' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $event->loadMissing('status');
        $isFeatured = $request->boolean('is_featured');

        if ($isFeatured && (! in_array($event->status?->label, ['upcoming', 'ongoing'], true) || $event->end_at->isPast())) {
            throw ValidationException::withMessages([
                'is_featured' => 'Only upcoming or ongoing events can be featured.',
            ]);
        }

        $event->update([
            'is_featured' => $isFeatured,
            'featured_order' => $isFeatured ? ($data['featured_order'] ?? null) : null,
            'featured_until' => $isFeatured ? ($data['featured_until'] ?? null) : null,
        ]);

        ActivityLog::create([
            'actor_id' => $request->user()->id,
            'event_id' => $event->id,
            'action' => $isFeatured ? 'event_featured' : 'event_unfeatured',
            'acting_role' => $request->user()->role?->name,
            'description' => $isFeatured
                ? "{$event->title} was added to the featured carousel."
                : "{$event->title} was removed from the featured carousel.",
        ]);

        return back()->with('success', $isFeatured
            ? 'Event added to the featured carousel.'
            : 'Event removed from the featured carousel.');
    }
}
