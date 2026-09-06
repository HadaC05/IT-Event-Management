<?php

namespace App\Services;

use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EventConflictDetector
{
    public function detect(
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $location = null,
        array $assignedUserIds = [],
        ?int $exceptEventId = null,
    ): array {
        $overlapping = Event::query()
            ->with('assignedUsers:id,first_name,middle_name,last_name')
            ->when($exceptEventId, fn ($query) => $query->whereKeyNot($exceptEventId))
            ->where('start_at', '<', $end)
            ->where('end_at', '>', $start);

        $locationConflicts = collect();
        if (filled($location)) {
            $locationConflicts = (clone $overlapping)
                ->whereRaw('LOWER(location) = ?', [mb_strtolower(trim($location))])
                ->get();
        }

        $userIds = collect($assignedUserIds)->map(fn ($id) => (int) $id)->unique()->values();
        $personConflicts = $userIds->isEmpty()
            ? collect()
            : (clone $overlapping)
                ->whereHas('assignedUsers', fn ($query) => $query->whereIn('users.id', $userIds))
                ->get();

        return [
            'location' => $this->format($locationConflicts),
            'people' => $this->format($personConflicts, $userIds),
        ];
    }

    private function format(Collection $events, ?Collection $userIds = null): array
    {
        return $events->map(function (Event $event) use ($userIds) {
            $people = $userIds
                ? $event->assignedUsers->whereIn('id', $userIds)->pluck('full_name')->values()->all()
                : [];

            return [
                'id' => $event->id,
                'title' => $event->title,
                'location' => $event->location,
                'schedule' => $event->start_at->format('M j, g:i A').'–'.$event->end_at->format('g:i A'),
                'people' => $people,
            ];
        })->values()->all();
    }
}
