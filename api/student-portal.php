<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class StudentPortalRepository
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function events(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT e.id, e.title, e.description, e.location, e.poster_path,
                    e.start_at, e.end_at, es.label AS status, et.label AS type
             FROM tbl_events e
             LEFT JOIN tbl_event_statuses es ON es.id = e.event_status_id
             LEFT JOIN tbl_event_types et ON et.id = e.event_type_id
             WHERE e.deleted_at IS NULL
               AND {$this->eligibleEventSql('e')}
             ORDER BY e.start_at"
        );
        $statement->execute([$userId, $userId]);
        $events = $statement->fetchAll();
        $this->attachSchedules($events);

        $active = [];
        $past = [];
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        foreach ($events as $event) {
            $event['id'] = (int) $event['id'];
            $end = new DateTimeImmutable((string) $event['end_at'], new DateTimeZone('Asia/Manila'));
            $isPast = $end <= $now;
            $event['schedule_state'] = $isPast
                ? 'completed'
                : ((new DateTimeImmutable((string) $event['start_at'], new DateTimeZone('Asia/Manila'))) > $now ? 'upcoming' : 'ongoing');
            if ($isPast) {
                array_unshift($past, $event);
            } else {
                $active[] = $event;
            }
        }

        return ['active' => $active, 'past' => $past];
    }

    public function attendance(int $userId): array
    {
        $summaryStatement = $this->db->prepare(
            "SELECT COUNT(*) AS total,
                    SUM(effective_status = 'present') AS present,
                    SUM(effective_status = 'late') AS late,
                    SUM(effective_status = 'absent') AS absent,
                    SUM(effective_status = 'excused') AS excused
             FROM vw_attendance_effective
             WHERE user_id = ?"
        );
        $summaryStatement->execute([$userId]);
        $summary = $summaryStatement->fetch() ?: [];
        foreach (['total', 'present', 'late', 'absent', 'excused'] as $field) {
            $summary[$field] = (int) ($summary[$field] ?? 0);
        }
        $attended = $summary['present'] + $summary['late'];
        $summary['rate'] = $summary['total'] > 0
            ? (int) round(($attended / $summary['total']) * 100)
            : 0;

        $history = $this->db->prepare(
            "SELECT a.id, a.attendance_date, a.effective_status status, a.manual_status, a.checked_in_at,
                    a.morning_in_at, a.morning_out_at,
                    a.afternoon_in_at, a.afternoon_out_at, a.notes,
                    e.title AS event_title, e.start_at, asm.code AS attendance_mode
             FROM vw_attendance_effective a
             JOIN tbl_events e ON e.id = a.event_id
             LEFT JOIN tbl_event_attendance_schedules eas ON eas.event_id=a.event_id AND eas.schedule_date=a.attendance_date
             LEFT JOIN tbl_attendance_session_modes asm ON asm.id=eas.attendance_session_mode_id
             WHERE a.user_id = ?
             ORDER BY a.attendance_date DESC, a.updated_at DESC
             LIMIT 50"
        );
        $history->execute([$userId]);
        $records = $history->fetchAll();
        foreach ($records as &$record) {
            $record['id'] = (int) $record['id'];
        }
        unset($record);

        $events = $this->events($userId);
        return [
            'summary' => $summary,
            'current_event' => $events['active'][0] ?? null,
            'records' => $records,
        ];
    }

    public function team(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT t.id, t.name, t.color, sy.label AS school_year,
                    COUNT(DISTINCT members.user_id) AS members_count,
                    COALESCE(SUM(DISTINCT score_totals.total), 0) AS total_score
             FROM tbl_team_user mine
             JOIN tbl_teams t ON t.id = mine.team_id
             JOIN tbl_school_years sy ON sy.id = t.school_year_id
             LEFT JOIN tbl_team_user members ON members.team_id = t.id AND EXISTS(SELECT 1 FROM tbl_users mu JOIN tbl_roles mr ON mr.id=mu.role_id WHERE mu.id=members.user_id AND mr.name='Student')
             LEFT JOIN (
                 SELECT team_id, SUM(points) AS total
                 FROM vw_finalized_scores
                 GROUP BY team_id
             ) score_totals ON score_totals.team_id = t.id
             WHERE mine.user_id = ? AND t.is_active = 1
             GROUP BY t.id, t.name, t.color, sy.label
             ORDER BY sy.id DESC
             LIMIT 1"
        );
        $statement->execute([$userId]);
        $team = $statement->fetch();
        if (!$team) {
            return ['team' => null];
        }

        $teamId = (int) $team['id'];
        $team['id'] = $teamId;
        $team['members_count'] = (int) $team['members_count'];
        $team['total_score'] = (float) $team['total_score'];
        $team['rank'] = $this->teamRank($teamId);

        $members = $this->db->prepare(
            "SELECT u.id, u.first_name, u.middle_name, u.last_name,
                    u.profile_photo_path, yl.label AS year_level
             FROM tbl_team_user tu
             JOIN tbl_users u ON u.id = tu.user_id
             JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student'
             LEFT JOIN tbl_year_levels yl ON yl.id = u.year_level
             WHERE tu.team_id = ?
             ORDER BY yl.id, u.last_name, u.first_name"
        );
        $members->execute([$teamId]);
        $team['members'] = $members->fetchAll();
        foreach ($team['members'] as &$member) {
            $member['id'] = (int) $member['id'];
            $member['full_name'] = $this->fullName($member);
            $member['initials'] = $this->initials($member);
        }
        unset($member);

        $scores = $this->db->prepare(
            "SELECT COALESCE(sc.name, 'General') AS category,
                    SUM(s.points) AS points, MAX(s.updated_at) AS updated_at
             FROM vw_finalized_scores s
             LEFT JOIN tbl_score_categories sc ON sc.id = s.score_category_id
             WHERE s.team_id = ?
             GROUP BY sc.id, sc.name
             ORDER BY points DESC"
        );
        $scores->execute([$teamId]);
        $team['scores'] = $scores->fetchAll();
        foreach ($team['scores'] as &$score) {
            $score['points'] = (float) $score['points'];
        }
        unset($score);

        $leaders = $this->db->prepare(
            "SELECT soa.position, u.first_name, u.middle_name, u.last_name
             FROM tbl_sbo_officer_assignments soa
             JOIN tbl_users u ON u.id = soa.officer_user_id
             WHERE soa.team_id = ? AND LOWER(soa.status) = 'active'
             ORDER BY soa.position"
        );
        $leaders->execute([$teamId]);
        $team['leaders'] = $leaders->fetchAll();
        foreach ($team['leaders'] as &$leader) {
            $leader['full_name'] = $this->fullName($leader);
        }
        unset($leader);

        $activities = $this->db->prepare(
            "SELECT e.id, e.title, e.start_at, e.end_at, e.location
             FROM tbl_event_team et
             JOIN tbl_events e ON e.id = et.event_id
             WHERE et.team_id = ? AND e.deleted_at IS NULL AND e.end_at >= CURRENT_TIMESTAMP
             ORDER BY e.start_at
             LIMIT 5"
        );
        $activities->execute([$teamId]);
        $team['activities'] = $activities->fetchAll();

        return ['team' => $team];
    }

    public function leaderboard(int $userId): array
    {
        $eventStatement = $this->db->prepare(
            "SELECT e.id, e.title, e.start_at, e.end_at
             FROM tbl_events e
             WHERE e.deleted_at IS NULL
               AND e.end_at >= CURRENT_TIMESTAMP
               AND {$this->eligibleEventSql('e')}
             ORDER BY e.start_at
             LIMIT 1"
        );
        $eventStatement->execute([$userId, $userId]);
        $event = $eventStatement->fetch();
        if (!$event) {
            return ['event' => null, 'categories' => [], 'teams' => []];
        }
        $eventId = (int) $event['id'];
        $event['id'] = $eventId;

        $categories = $this->db->prepare(
            "SELECT id, name, max_points
             FROM tbl_score_categories
             WHERE event_id = ?
             ORDER BY sort_order, name"
        );
        $categories->execute([$eventId]);
        $categoryRows = $categories->fetchAll();
        foreach ($categoryRows as &$category) {
            $category['id'] = (int) $category['id'];
            $category['max_points'] = (float) $category['max_points'];
        }
        unset($category);

        $teams = $this->db->prepare(
            "SELECT t.id, t.name, t.color,
                    COUNT(DISTINCT tu.user_id) AS members_count,
                    COALESCE(SUM(s.points), 0) AS total_score,
                    COUNT(s.id) AS score_entries,
                    MAX(s.updated_at) AS last_scored_at
             FROM tbl_teams t
             LEFT JOIN tbl_team_user tu ON tu.team_id = t.id
             LEFT JOIN vw_finalized_scores s ON s.team_id = t.id AND s.event_id = ?
             WHERE t.is_active = 1
             GROUP BY t.id, t.name, t.color
             ORDER BY COUNT(s.id) > 0 DESC, COALESCE(SUM(s.points), 0) DESC, t.name"
        );
        $teams->execute([$eventId]);
        $teamRows = $teams->fetchAll();

        $categoryScores = $this->db->prepare(
            "SELECT team_id, score_category_id, SUM(points) AS points
             FROM vw_finalized_scores
             WHERE event_id = ?
             GROUP BY team_id, score_category_id"
        );
        $categoryScores->execute([$eventId]);
        $scoreMap = [];
        foreach ($categoryScores->fetchAll() as $score) {
            $scoreMap[(int) $score['team_id']][(int) $score['score_category_id']] = (float) $score['points'];
        }

        $rank = 0;
        $lastPoints = null;
        $lastRank = null;
        foreach ($teamRows as &$team) {
            $rank++;
            $team['id'] = (int) $team['id'];
            $team['members_count'] = (int) $team['members_count'];
            $team['total_score'] = (float) $team['total_score'];
            $team['has_score'] = (int) $team['score_entries'] > 0;
            $team['category_scores'] = $scoreMap[$team['id']] ?? [];
            if (!$team['has_score']) {
                $team['rank'] = null;
            } elseif ($lastPoints !== null && abs($team['total_score'] - $lastPoints) < 0.00001) {
                $team['rank'] = $lastRank;
            } else {
                $team['rank'] = $rank;
                $lastRank = $rank;
                $lastPoints = $team['total_score'];
            }
            unset($team['score_entries']);
        }
        unset($team);

        return ['event' => $event, 'categories' => $categoryRows, 'teams' => $teamRows];
    }

    private function eligibleEventSql(string $eventAlias): string
    {
        return "(
            EXISTS (SELECT 1 FROM tbl_event_user eu WHERE eu.event_id = {$eventAlias}.id AND eu.user_id = ?)
            OR EXISTS (SELECT 1 FROM tbl_event_membership_snapshots membership WHERE membership.event_id={$eventAlias}.id AND membership.user_id=?)
        )";
    }

    private function attachSchedules(array &$events): void
    {
        if (!$events) {
            return;
        }
        $ids = array_map(static fn (array $event): int => (int) $event['id'], $events);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->prepare(
            "SELECT eas.event_id, eas.schedule_date, asm.name AS mode,
                    eas.whole_day_in_time, eas.whole_day_out_time,
                    eas.morning_in_time, eas.morning_out_time,
                    eas.afternoon_in_time, eas.afternoon_out_time
             FROM tbl_event_attendance_schedules eas
             JOIN tbl_attendance_session_modes asm ON asm.id = eas.attendance_session_mode_id
             WHERE eas.event_id IN ({$placeholders})
             ORDER BY eas.schedule_date"
        );
        $statement->execute($ids);
        $byEvent = [];
        foreach ($statement->fetchAll() as $schedule) {
            $byEvent[(int) $schedule['event_id']][] = $schedule;
        }
        foreach ($events as &$event) {
            $event['schedules'] = $byEvent[(int) $event['id']] ?? [];
        }
        unset($event);
    }

    private function teamRank(int $teamId): ?int
    {
        $rows = $this->db->query(
            "SELECT t.id, COUNT(s.id) AS entries, COALESCE(SUM(s.points), 0) AS points
             FROM tbl_teams t
             LEFT JOIN vw_finalized_scores s ON s.team_id = t.id
             WHERE t.is_active = 1
             GROUP BY t.id
             ORDER BY COUNT(s.id) > 0 DESC, COALESCE(SUM(s.points), 0) DESC, t.name"
        )->fetchAll();
        foreach ($rows as $index => $row) {
            if ((int) $row['id'] === $teamId) {
                return (int) $row['entries'] > 0 ? $index + 1 : null;
            }
        }
        return null;
    }

    private function fullName(array $person): string
    {
        return trim(implode(' ', array_filter([
            $person['first_name'] ?? null,
            $person['middle_name'] ?? null,
            $person['last_name'] ?? null,
        ])));
    }

    private function initials(array $person): string
    {
        return mb_strtoupper(
            mb_substr((string) ($person['first_name'] ?? ''), 0, 1)
            .mb_substr((string) ($person['last_name'] ?? ''), 0, 1)
        );
    }
}

$actor = AuthGuard::requireRole('Student');
$repository = new StudentPortalRepository((new Database())->connection());
$page = (string) ($_GET['page'] ?? 'events');

try {
    $data = match ($page) {
        'events' => $repository->events((int) $actor['id']),
        'attendance' => $repository->attendance((int) $actor['id']),
        'team' => $repository->team((int) $actor['id']),
        'leaderboard' => $repository->leaderboard((int) $actor['id']),
        default => null,
    };
    if ($data === null) {
        JsonResponse::send(['success' => false, 'message' => 'Unknown student page.'], 404);
    }
    JsonResponse::send(['success' => true, 'data' => $data]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'The student page could not be loaded.'], 500);
}
