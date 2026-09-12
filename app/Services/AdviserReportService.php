<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Event;
use App\Models\SchoolYear;
use App\Models\Score;
use App\Models\ScoreCategory;
use App\Models\Team;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AdviserReportService
{
    public function __construct(private readonly LeaderboardService $leaderboards) {}

    public function generate(string $type, array $filters, bool $all = false): array
    {
        return match ($type) {
            'participation' => $this->participation($filters, $all),
            'scores' => $this->scores($filters, $all),
            'rankings' => $this->rankings($filters, $all),
            default => $this->attendance($filters, $all),
        };
    }

    private function attendance(array $filters, bool $all): array
    {
        $query = Attendance::query()
            ->with(['event', 'user.yearLevel', 'user.teams.schoolYear'])
            ->when($filters['event_id'] ?? null, fn (Builder $query, int $eventId) => $query->where('event_id', $eventId))
            ->when($filters['school_year_id'] ?? null, fn (Builder $query, int $schoolYearId) => $query
                ->whereHas('user.teams', fn ($query) => $query->where('school_year_id', $schoolYearId)))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('attendance_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('attendance_date', '<=', $date))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = $this->like($search);
                $query->where(fn (Builder $query) => $query
                    ->whereHas('event', fn ($query) => $query->where('title', 'like', $term))
                    ->orWhereHas('user', fn ($query) => $query
                        ->where('first_name', 'like', $term)
                        ->orWhere('middle_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('id_number', 'like', $term)));
            });

        $total = (clone $query)->count();
        $attended = (clone $query)->whereIn('status', Attendance::ATTENDED_STATUSES)->count();

        return [
            'rows' => $all
                ? $query->latest('attendance_date')->latest('id')->get()
                : $query->latest('attendance_date')->latest('id')->paginate(25)->withQueryString(),
            'summary' => [
                'records' => $total,
                'attended' => $attended,
                'absent' => (clone $query)->where('status', 'absent')->count(),
                'excused' => (clone $query)->where('status', 'excused')->count(),
                'rate' => $total > 0 ? round(($attended / $total) * 100, 1) : null,
            ],
        ];
    }

    private function participation(array $filters, bool $all): array
    {
        $events = Event::query()
            ->with('status')
            ->when($filters['event_id'] ?? null, fn (Builder $query, int $eventId) => $query->whereKey($eventId))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('start_at', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('start_at', '<=', $date))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = $this->like($search);
                $query->where(fn (Builder $query) => $query->where('title', 'like', $term)->orWhere('location', 'like', $term));
            })
            ->latest('start_at')
            ->get()
            ->map(function (Event $event) use ($filters) {
                $schoolYearId = $filters['school_year_id'] ?? null;
                $expectedQuery = $event->expectedParticipantsQuery()
                    ->when($schoolYearId, fn (Builder $query, int $id) => $query
                        ->whereHas('teams', fn ($query) => $query->where('school_year_id', $id)));
                $attendanceQuery = $event->attendances()
                    ->when($schoolYearId, fn ($query, int $id) => $query
                        ->whereHas('user.teams', fn ($query) => $query->where('school_year_id', $id)));
                $expected = $expectedQuery->distinct()->count('users.id');
                $recorded = (clone $attendanceQuery)->distinct()->count('user_id');
                $attended = (clone $attendanceQuery)->whereIn('status', Attendance::ATTENDED_STATUSES)->distinct()->count('user_id');

                $event->setAttribute('expected_count', $expected);
                $event->setAttribute('recorded_count', $recorded);
                $event->setAttribute('attended_count', $attended);
                $event->setAttribute('participation_rate', $expected > 0 ? min(100, round(($attended / $expected) * 100, 1)) : null);

                return $event;
            });

        $expected = $events->sum('expected_count');
        $attended = $events->sum('attended_count');

        return [
            'rows' => $all ? $events : $this->paginate($events),
            'summary' => [
                'events' => $events->count(),
                'expected' => $expected,
                'recorded' => $events->sum('recorded_count'),
                'attended' => $attended,
                'rate' => $expected > 0 ? min(100, round(($attended / $expected) * 100, 1)) : null,
            ],
        ];
    }

    private function scores(array $filters, bool $all): array
    {
        $query = Score::query()
            ->whereNotNull('score_category_id')
            ->with(['event', 'team.schoolYear', 'category', 'recorder'])
            ->when($filters['event_id'] ?? null, fn (Builder $query, int $eventId) => $query->where('event_id', $eventId))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $categoryId) => $query->where('score_category_id', $categoryId))
            ->when($filters['school_year_id'] ?? null, fn (Builder $query, int $schoolYearId) => $query
                ->whereHas('team', fn ($query) => $query->where('school_year_id', $schoolYearId)))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date) => $query
                ->whereHas('event', fn ($query) => $query->whereDate('start_at', '>=', $date)))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date) => $query
                ->whereHas('event', fn ($query) => $query->whereDate('start_at', '<=', $date)))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $term = $this->like($search);
                $query->where(fn (Builder $query) => $query
                    ->whereHas('event', fn ($query) => $query->where('title', 'like', $term))
                    ->orWhereHas('team', fn ($query) => $query->where('name', 'like', $term))
                    ->orWhereHas('category', fn ($query) => $query->where('name', 'like', $term)));
            });

        $entries = (clone $query)->count();

        return [
            'rows' => $all
                ? $query->latest('updated_at')->get()
                : $query->latest('updated_at')->paginate(25)->withQueryString(),
            'summary' => [
                'entries' => $entries,
                'teams' => (clone $query)->distinct()->count('team_id'),
                'points' => (float) (clone $query)->sum('points'),
                'average' => $entries > 0 ? round((float) (clone $query)->avg('points'), 2) : null,
            ],
        ];
    }

    private function rankings(array $filters, bool $all): array
    {
        $event = isset($filters['event_id']) ? Event::find($filters['event_id']) : null;
        $schoolYear = isset($filters['school_year_id']) ? SchoolYear::find($filters['school_year_id']) : null;
        $category = isset($filters['category_id']) ? ScoreCategory::find($filters['category_id']) : null;
        $rankings = $this->leaderboards->rankings($event, $schoolYear, $category);
        $ranked = $rankings->where('has_score', true);
        $search = mb_strtolower(trim($filters['search'] ?? ''));
        $visible = $rankings
            ->when($search !== '', fn (Collection $teams) => $teams->filter(
                fn (Team $team) => str_contains(mb_strtolower($team->name), $search)
            ))
            ->values();

        return [
            'rows' => $all ? $visible : $this->paginate($visible),
            'summary' => [
                'ranked' => $ranked->count(),
                'eligible' => $rankings->count(),
                'points' => (float) $ranked->sum('total_score'),
                'events' => $ranked->flatMap->scores->pluck('event_id')->unique()->count(),
                'leader' => $ranked->first()?->name,
            ],
        ];
    }

    private function paginate(Collection $items, int $perPage = 25): LengthAwarePaginatorContract
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function like(string $search): string
    {
        return '%'.addcslashes(trim($search), '%_\\').'%';
    }
}
