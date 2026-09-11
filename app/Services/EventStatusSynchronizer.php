<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventStatus;

class EventStatusSynchronizer
{
    public function sync(): void
    {
        $statusIds = collect(['upcoming', 'ongoing', 'completed'])
            ->mapWithKeys(fn (string $label) => [$label => EventStatus::firstOrCreate(['label' => $label])->id]);
        $archivedId = EventStatus::firstOrCreate(['label' => 'archived'])->id;
        $now = now();

        $activeEvents = Event::query()
            ->where(function ($query) use ($archivedId) {
                $query->whereNull('event_status_id')->orWhere('event_status_id', '!=', $archivedId);
            });

        (clone $activeEvents)->where('start_at', '>', $now)->update(['event_status_id' => $statusIds['upcoming']]);
        (clone $activeEvents)->where('start_at', '<=', $now)->where('end_at', '>', $now)->update(['event_status_id' => $statusIds['ongoing']]);
        (clone $activeEvents)->where('end_at', '<=', $now)->update([
            'event_status_id' => $statusIds['completed'],
            'is_featured' => false,
            'featured_order' => null,
            'featured_until' => null,
        ]);
    }
}
