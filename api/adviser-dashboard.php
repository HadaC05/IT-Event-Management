<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class AdviserDashboardRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function metrics(): array
    {
        $attendance = $this->todayAttendance();
        $upcoming = $this->upcomingEvents();

        return [
            'stats' => [
                'total_users' => $this->scalar('SELECT COUNT(*) FROM tbl_users'),
                'students' => $this->roleCount('Student'),
                'faculty' => $this->roleCount('Faculty'),
                'sbo' => $this->rolesCount(['SBO', 'SBO Officer']),
                'students_present' => $this->todayPresentStudents(),
                'upcoming_events' => $this->scalar('SELECT COUNT(*) FROM tbl_events WHERE deleted_at IS NULL AND start_at > CURRENT_TIMESTAMP'),
                'points_awarded' => (float) $this->database->query('SELECT COALESCE(SUM(points), 0) FROM tbl_scores')->fetchColumn(),
                'ranked_teams' => $this->scalar('SELECT COUNT(DISTINCT tbl_teams.id) FROM tbl_teams JOIN tbl_scores ON tbl_scores.team_id = tbl_teams.id WHERE tbl_teams.is_active = 1'),
                'attendance_rate' => $attendance['rate'],
            ],
            'today_attendance' => $attendance,
            'today_events' => $this->todayEvents(),
            'upcoming_events' => $upcoming,
            'recent_attendance' => $this->recentAttendance(),
            'leaderboard' => $this->leaderboard(),
            'starting_tomorrow' => $this->startingTomorrow(),
        ];
    }

    private function todayAttendance(): array
    {
        $statement = $this->database->query(
            "SELECT attendance.status, COUNT(*) AS total
             FROM tbl_attendances AS attendance
             LEFT JOIN tbl_events ON tbl_events.id = attendance.event_id
             WHERE DATE(attendance.attendance_date) = CURRENT_DATE
                OR (attendance.attendance_date IS NULL AND DATE(tbl_events.start_at) = CURRENT_DATE)
             GROUP BY attendance.status"
        );
        $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];
        foreach ($statement->fetchAll() as $row) {
            if (array_key_exists($row['status'], $counts)) {
                $counts[$row['status']] = (int) $row['total'];
            }
        }
        $counts['marked'] = array_sum($counts);
        $counts['attended'] = $counts['present'] + $counts['late'];
        $counts['rate'] = $counts['marked'] > 0 ? round($counts['attended'] / $counts['marked'] * 100, 1) : null;
        return $counts;
    }

    private function todayPresentStudents(): int
    {
        return $this->scalar(
            "SELECT COUNT(DISTINCT attendance.user_id)
             FROM tbl_attendances AS attendance
             LEFT JOIN tbl_events ON tbl_events.id = attendance.event_id
             WHERE attendance.status = 'present'
               AND (DATE(attendance.attendance_date) = CURRENT_DATE
                    OR (attendance.attendance_date IS NULL AND DATE(tbl_events.start_at) = CURRENT_DATE))"
        );
    }

    private function todayEvents(): array
    {
        return $this->database->query(
            "SELECT tbl_events.id, tbl_events.title, tbl_events.start_at, tbl_events.end_at, tbl_events.location,
                    COUNT(tbl_event_user.user_id) AS assigned_count
             FROM tbl_events
             LEFT JOIN tbl_event_user ON tbl_event_user.event_id = tbl_events.id
             WHERE tbl_events.deleted_at IS NULL
               AND tbl_events.start_at < CURRENT_DATE + INTERVAL 1 DAY
               AND tbl_events.end_at >= CURRENT_DATE
             GROUP BY tbl_events.id
             ORDER BY tbl_events.start_at"
        )->fetchAll();
    }

    private function upcomingEvents(): array
    {
        return $this->database->query(
            "SELECT id, title, start_at, end_at, location
             FROM tbl_events
             WHERE deleted_at IS NULL AND start_at > CURRENT_TIMESTAMP
             ORDER BY start_at
             LIMIT 3"
        )->fetchAll();
    }

    private function recentAttendance(): array
    {
        return $this->database->query(
            "SELECT attendance.id, attendance.status, attendance.checked_in_at, attendance.updated_at,
                    tbl_events.title AS event_title,
                    TRIM(CONCAT_WS(' ', tbl_users.first_name, NULLIF(tbl_users.middle_name, ''), tbl_users.last_name)) AS student_name
             FROM tbl_attendances AS attendance
             JOIN tbl_users ON tbl_users.id = attendance.user_id
             JOIN tbl_events ON tbl_events.id = attendance.event_id
             ORDER BY COALESCE(attendance.checked_in_at, attendance.updated_at) DESC
             LIMIT 6"
        )->fetchAll();
    }

    private function leaderboard(): array
    {
        $rows = $this->database->query(
            "SELECT tbl_teams.id, tbl_teams.name, COUNT(DISTINCT tbl_team_user.user_id) AS members_count, SUM(tbl_scores.points) AS total_score
             FROM tbl_teams
             JOIN tbl_scores ON tbl_scores.team_id = tbl_teams.id
             LEFT JOIN tbl_team_user ON tbl_team_user.team_id = tbl_teams.id
             WHERE tbl_teams.is_active = 1
             GROUP BY tbl_teams.id
             ORDER BY total_score DESC, tbl_teams.name
             LIMIT 5"
        )->fetchAll();
        foreach ($rows as $index => &$row) {
            $row['rank'] = $index + 1;
            $row['members_count'] = (int) $row['members_count'];
            $row['total_score'] = (float) $row['total_score'];
        }
        return $rows;
    }

    private function startingTomorrow(): ?array
    {
        $statement = $this->database->query(
            "SELECT id, title, start_at
             FROM tbl_events
             WHERE deleted_at IS NULL AND DATE(start_at) = CURRENT_DATE + INTERVAL 1 DAY
             ORDER BY start_at
             LIMIT 1"
        );
        return $statement->fetch() ?: null;
    }

    private function roleCount(string $role): int
    {
        $statement = $this->database->prepare('SELECT COUNT(*) FROM tbl_users JOIN tbl_roles ON tbl_roles.id = tbl_users.role_id WHERE tbl_roles.name = :role');
        $statement->execute(['role' => $role]);
        return (int) $statement->fetchColumn();
    }

    private function rolesCount(array $roles): int
    {
        $statement = $this->database->prepare('SELECT COUNT(*) FROM tbl_users JOIN tbl_roles ON tbl_roles.id = tbl_users.role_id WHERE tbl_roles.name IN (:first, :second)');
        $statement->execute(['first' => $roles[0], 'second' => $roles[1]]);
        return (int) $statement->fetchColumn();
    }

    private function scalar(string $sql): int
    {
        return (int) $this->database->query($sql)->fetchColumn();
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$user = AuthGuard::requireRole('SBO Adviser');

try {
    $repository = new AdviserDashboardRepository((new Database())->connection());
    JsonResponse::send(['success' => true, 'user' => $user, 'data' => $repository->metrics()]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'Dashboard data could not be loaded.'], 500);
}
