<?php

namespace App\Http\Controllers\Adviser;

use App\Http\Controllers\Controller;
use App\Http\Requests\Adviser\ReportRequest;
use App\Models\Event;
use App\Models\SchoolYear;
use App\Services\AdviserReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(ReportRequest $request, AdviserReportService $reports): View
    {
        $filters = $request->validated();
        $type = $filters['type'];
        $event = isset($filters['event_id']) ? Event::findOrFail($filters['event_id']) : null;

        return view('adviser.reports.index', [
            'type' => $type,
            'report' => $reports->generate($type, $filters),
            'events' => Event::query()->latest('start_at')->get(['id', 'title', 'start_at']),
            'schoolYears' => SchoolYear::query()->latest('label')->get(),
            'categories' => $event?->scoreCategories()->get() ?? collect(),
            'selectedEvent' => $event,
        ]);
    }

    public function export(ReportRequest $request, AdviserReportService $reports): StreamedResponse
    {
        $filters = $request->validated();
        $type = $filters['type'];
        $rows = $reports->generate($type, $filters, true)['rows'];
        [$headers, $mapper] = $this->csvDefinition($type);
        $filename = $type.'-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($headers, $mapper, $rows) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers);

            foreach ($rows as $row) {
                fputcsv($output, array_map($this->sanitizeCsv(...), $mapper($row)));
            }

            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvDefinition(string $type): array
    {
        return match ($type) {
            'participation' => [
                ['Event', 'Schedule', 'Venue', 'Status', 'Expected Students', 'Recorded Students', 'Attended Students', 'Participation Rate'],
                fn ($event) => [
                    $event->title,
                    $event->start_at->format('Y-m-d H:i'),
                    $event->location,
                    $event->schedule_state,
                    $event->expected_count,
                    $event->recorded_count,
                    $event->attended_count,
                    $event->participation_rate === null ? 'N/A' : $event->participation_rate.'%',
                ],
            ],
            'scores' => [
                ['Event', 'Event Date', 'Tribe', 'School Year', 'Criterion', 'Points', 'Maximum Points', 'Recorded By', 'Last Updated'],
                fn ($score) => [
                    $score->event?->title,
                    $score->event?->start_at?->format('Y-m-d'),
                    $score->team?->name,
                    $score->team?->schoolYear?->label,
                    $score->category?->name,
                    $score->points,
                    $score->category?->max_points,
                    $score->recorder?->full_name,
                    $score->updated_at?->format('Y-m-d H:i'),
                ],
            ],
            'rankings' => [
                ['Rank', 'Tribe', 'School Year', 'Total Points', 'Score Entries', 'Scored Events', 'Status'],
                fn ($team) => [
                    $team->rank ?? 'Unranked',
                    $team->name,
                    $team->schoolYear?->label,
                    $team->total_score,
                    $team->score_entries_count,
                    $team->scored_events_count,
                    $team->has_score ? 'Ranked' : 'Not scored',
                ],
            ],
            default => [
                ['Student ID', 'Student', 'Year Level', 'Tribe', 'Event', 'Attendance Date', 'Status', 'Check-in'],
                fn ($attendance) => [
                    $attendance->user?->id_number,
                    $attendance->user?->full_name,
                    $attendance->user?->yearLevel?->label,
                    $attendance->user?->teams?->first()?->name,
                    $attendance->event?->title,
                    $attendance->attendance_date?->format('Y-m-d'),
                    ucfirst($attendance->status),
                    $attendance->checked_in_at?->format('Y-m-d H:i'),
                ],
            ],
        };
    }

    private function sanitizeCsv(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^[=+\-@]/', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
