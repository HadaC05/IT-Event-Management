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
        $attention = $this->attention();

        return [
            'stats' => [
                'total_users' => $this->scalar('SELECT COUNT(*) FROM tbl_users'),
                'students' => $this->roleCount('Student'),
                'faculty' => $this->roleCount('Faculty'),
                'sbo' => $this->rolesCount(['SBO', 'SBO Officer']),
                'active_teams' => $this->scalar('SELECT COUNT(*) FROM tbl_teams WHERE is_active = 1'),
                'students_present' => $this->todayPresentStudents(),
                'upcoming_events' => $this->scalar('SELECT COUNT(*) FROM tbl_events WHERE deleted_at IS NULL AND start_at > CURRENT_TIMESTAMP'),
                'points_awarded' => (float) $this->database->query('SELECT COALESCE(SUM(points), 0) FROM vw_finalized_scores')->fetchColumn(),
                'ranked_teams' => $this->scalar('SELECT COUNT(DISTINCT tbl_teams.id) FROM tbl_teams JOIN vw_finalized_scores ON vw_finalized_scores.team_id = tbl_teams.id WHERE tbl_teams.is_active = 1'),
                'attendance_rate' => $attendance['rate'],
            ],
            'today_attendance' => $attendance,
            'today_events' => $this->todayEvents(),
            'upcoming_events' => $upcoming,
            'recent_attendance' => $this->recentAttendance(),
            'recent_activity' => $this->recentActivity(),
            'leaderboard' => $this->leaderboard(),
            'starting_tomorrow' => $this->startingTomorrow(),
            'attention' => $attention,
        ];
    }

    private function todayAttendance(): array
    {
        $statement = $this->database->query(
            "SELECT attendance.effective_status AS status, COUNT(*) AS total
             FROM vw_attendance_effective AS attendance
             LEFT JOIN tbl_events ON tbl_events.id = attendance.event_id
             WHERE DATE(attendance.attendance_date) = CURRENT_DATE
                OR (attendance.attendance_date IS NULL AND DATE(tbl_events.start_at) = CURRENT_DATE)
             GROUP BY attendance.effective_status"
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
             FROM vw_attendance_effective AS attendance
             LEFT JOIN tbl_events ON tbl_events.id = attendance.event_id
             WHERE attendance.effective_status IN ('present','late')
               AND (DATE(attendance.attendance_date) = CURRENT_DATE
                    OR (attendance.attendance_date IS NULL AND DATE(tbl_events.start_at) = CURRENT_DATE))"
        );
    }

    private function todayEvents(): array
    {
        $events = $this->database->query(
            "SELECT tbl_events.id, tbl_events.title, tbl_events.start_at, tbl_events.end_at, tbl_events.location,
                    tbl_events.audience_type, COUNT(DISTINCT tbl_event_user.user_id) AS assigned_count,
                    COUNT(DISTINCT tbl_attendances.id) AS attendances_count
             FROM tbl_events
             LEFT JOIN tbl_event_user ON tbl_event_user.event_id = tbl_events.id
             LEFT JOIN vw_attendance_effective AS tbl_attendances ON tbl_attendances.event_id = tbl_events.id
                AND (tbl_attendances.attendance_date = CURRENT_DATE OR tbl_attendances.attendance_date IS NULL)
             WHERE tbl_events.deleted_at IS NULL
               AND tbl_events.start_at < CURRENT_DATE + INTERVAL 1 DAY
               AND tbl_events.end_at >= CURRENT_DATE
             GROUP BY tbl_events.id
             ORDER BY tbl_events.start_at"
        )->fetchAll();
        foreach ($events as &$event) {
            $event['id'] = (int) $event['id'];
            $event['assigned_count'] = (int) $event['assigned_count'];
            $event['attendances_count'] = (int) $event['attendances_count'];
            $event['expected_count'] = $this->expectedStudentCount($event);
        }
        unset($event);
        return $events;
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
            "SELECT attendance.id, attendance.effective_status AS status, attendance.manual_status, attendance.checked_in_at, attendance.updated_at,
                    tbl_events.title AS event_title,
                    TRIM(CONCAT_WS(' ', tbl_users.first_name, NULLIF(tbl_users.middle_name, ''), tbl_users.last_name)) AS student_name
             FROM vw_attendance_effective AS attendance
             JOIN tbl_users ON tbl_users.id = attendance.user_id
             JOIN tbl_events ON tbl_events.id = attendance.event_id
             ORDER BY COALESCE(attendance.checked_in_at, attendance.updated_at) DESC
             LIMIT 6"
        )->fetchAll();
    }

    private function recentActivity(): array
    {
        return $this->database->query(
            "SELECT logs.action, logs.description, logs.created_at, events.title AS event_title,
                    TRIM(CONCAT_WS(' ', users.first_name, NULLIF(users.middle_name, ''), users.last_name)) AS actor_name
             FROM tbl_activity_logs AS logs
             LEFT JOIN tbl_users AS users ON users.id = logs.actor_id
             LEFT JOIN tbl_events AS events ON events.id = logs.event_id
             ORDER BY logs.created_at DESC, logs.id DESC
             LIMIT 6"
        )->fetchAll();
    }

    private function attention(): array
    {
        $pendingPosts = $this->scalar("SELECT COUNT(*) FROM tbl_posts WHERE is_official = 0 AND status = 'pending' AND deleted_at IS NULL");
        $schoolYearId = $this->database->query('SELECT id FROM tbl_school_years ORDER BY id DESC LIMIT 1')->fetchColumn();
        $unassignedStudents = 0;
        if ($schoolYearId !== false) {
            $statement = $this->database->prepare(
                "SELECT COUNT(*) FROM tbl_users AS users
                 JOIN tbl_roles AS roles ON roles.id = users.role_id
                 JOIN tbl_user_statuses AS statuses ON statuses.id = users.status
                 WHERE roles.name = 'Student' AND statuses.label = 'active'
                   AND NOT EXISTS (
                       SELECT 1 FROM tbl_team_user AS membership
                       JOIN tbl_teams AS teams ON teams.id = membership.team_id
                       WHERE membership.user_id = users.id AND teams.school_year_id = ?
                   )"
            );
            $statement->execute([(int) $schoolYearId]);
            $unassignedStudents = (int) $statement->fetchColumn();
        }
        $scoringSetup = $this->scalar(
            "SELECT COUNT(*) FROM tbl_events AS events
             WHERE events.deleted_at IS NULL
               AND events.end_at >= CURRENT_TIMESTAMP - INTERVAL 30 DAY
               AND NOT EXISTS (SELECT 1 FROM tbl_score_categories AS categories WHERE categories.event_id = events.id)"
        );
        $attendanceIncomplete = $this->incompleteAttendanceEvents();

        $items = [];
        if ($pendingPosts > 0) $items[] = ['count' => $pendingPosts, 'label' => 'Posts waiting for review', 'description' => 'Approve or reject student submissions.', 'href' => 'pages/adviser/posts.html'];
        if ($unassignedStudents > 0) $items[] = ['count' => $unassignedStudents, 'label' => 'Students without a tribe', 'description' => 'Assign active students for the current school year.', 'href' => 'pages/adviser/teams.html'];
        if ($scoringSetup > 0) $items[] = ['count' => $scoringSetup, 'label' => 'Events need scoring setup', 'description' => 'Create criteria before judging begins.', 'href' => 'pages/adviser/scores.html'];
        if ($attendanceIncomplete > 0) $items[] = ['count' => $attendanceIncomplete, 'label' => 'Attendance rosters incomplete', 'description' => 'Finish attendance for started events.', 'href' => 'pages/adviser/attendance.html'];

        return [
            'pending_posts' => $pendingPosts,
            'unassigned_students' => $unassignedStudents,
            'scoring_setup' => $scoringSetup,
            'attendance_incomplete' => $attendanceIncomplete,
            'items' => $items,
        ];
    }

    private function incompleteAttendanceEvents(): int
    {
        $events = $this->database->query(
            "SELECT events.id, events.audience_type,
                    COUNT(DISTINCT attendance.user_id) AS attendances_count
             FROM tbl_events AS events
             LEFT JOIN vw_attendance_effective AS attendance ON attendance.event_id = events.id
             WHERE events.deleted_at IS NULL
               AND events.start_at <= CURRENT_TIMESTAMP
               AND events.end_at >= CURRENT_TIMESTAMP - INTERVAL 30 DAY
             GROUP BY events.id"
        )->fetchAll();
        $incomplete = 0;
        foreach ($events as $event) {
            $expected = $this->expectedStudentCount($event);
            if ($expected > 0 && (int) $event['attendances_count'] < $expected) $incomplete++;
        }
        return $incomplete;
    }

    private function expectedStudentCount(array $event): int
    {
        $statement = $this->database->prepare('SELECT COUNT(*) FROM tbl_event_membership_snapshots WHERE event_id=?');
        $statement->execute([(int)$event['id']]);
        return (int) $statement->fetchColumn();
    }

    private function leaderboard(): array
    {
        $rows = $this->database->query(
            "SELECT tbl_teams.id, tbl_teams.name, COUNT(DISTINCT tbl_team_user.user_id) AS members_count, SUM(tbl_scores.points) AS total_score
             FROM tbl_teams
             JOIN vw_finalized_scores AS tbl_scores ON tbl_scores.team_id = tbl_teams.id
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

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

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
