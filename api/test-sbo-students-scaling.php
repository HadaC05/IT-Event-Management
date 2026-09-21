<?php

declare(strict_types=1);

require_once __DIR__.'/sbo-students.php';

function sboStudentScalingAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$database = (new Database())->connection();
$fixture = $database->query("SELECT oa.id officer_assignment_id,oa.officer_user_id,oa.assigned_by,
        e.id event_id,s.id schedule_id,a.id activity_id,MIN(ms.team_id) team_id
    FROM tbl_sbo_officer_assignments oa
    JOIN tbl_events e ON e.deleted_at IS NULL
    JOIN tbl_event_attendance_schedules s ON s.event_id=e.id
    JOIN tbl_event_activities a ON a.event_id=e.id AND a.status='active'
    JOIN tbl_event_membership_snapshots ms ON ms.event_id=e.id
    JOIN tbl_teams t ON t.id=ms.team_id AND t.is_active=1
    WHERE oa.status='Active'
    GROUP BY oa.id,oa.officer_user_id,oa.assigned_by,e.id,s.id,a.id
    ORDER BY e.id,oa.id LIMIT 1")->fetch();
sboStudentScalingAssert((bool) $fixture, 'An active officer and event membership snapshot are required for the scaling test.');
$responsibilityId = (int) $database->query("SELECT id FROM tbl_officer_responsibilities WHERE code='attendance' LIMIT 1")->fetchColumn();
sboStudentScalingAssert($responsibilityId > 0, 'The attendance responsibility is required for the scaling test.');

$database->beginTransaction();
try {
    $database->prepare("UPDATE tbl_events SET start_at=CONCAT(CURDATE(),' 00:00:00'),end_at=CONCAT(CURDATE(),' 23:59:59') WHERE id=?")
        ->execute([(int) $fixture['event_id']]);
    $database->prepare('UPDATE tbl_event_attendance_schedules SET schedule_date=CURDATE() WHERE id=?')
        ->execute([(int) $fixture['schedule_id']]);
    $database->prepare("INSERT INTO tbl_sbo_event_assignments
        (officer_assignment_id,event_schedule_id,session_code,activity_id,team_id,responsibility_id,scanner_mode,scanner_team_id,status,assigned_by,created_at,updated_at)
        VALUES(?,?,?,?,?,?,'general',NULL,'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
        ->execute([
            (int) $fixture['officer_assignment_id'],
            (int) $fixture['schedule_id'],
            'whole_day',
            (int) $fixture['activity_id'],
            (int) $fixture['team_id'],
            $responsibilityId,
            $fixture['assigned_by'] === null ? null : (int) $fixture['assigned_by'],
        ]);

    $repository = new SboStudentsRepository($database, new SboAuthorization($database));
    $first = $repository->index((int) $fixture['officer_user_id'], []);
    sboStudentScalingAssert($first['selected']['scanner_mode'] === 'general', 'The test assignment must exercise general scanner scope.');
    sboStudentScalingAssert(count($first['students']) <= 25, 'A student roster page may contain at most 25 records.');
    sboStudentScalingAssert($first['pagination']['per_page'] === 25, 'SBO student pagination must remain at 25 records.');
    sboStudentScalingAssert(strlen(json_encode($first)) < 50000, 'A paginated SBO student response must remain below 50 KB.');

    if ($first['pagination']['last_page'] > 1) {
        $second = $repository->index((int) $fixture['officer_user_id'], [
            'assignment_id' => $first['selected']['id'],
            'page' => 2,
        ]);
        sboStudentScalingAssert(
            !array_intersect(array_column($first['students'], 'id'), array_column($second['students'], 'id')),
            'Adjacent SBO student pages must not overlap.'
        );
    }

    if ($first['students']) {
        $student = $first['students'][0];
        $searched = $repository->index((int) $fixture['officer_user_id'], [
            'assignment_id' => $first['selected']['id'],
            'search' => $student['id_number'],
        ]);
        sboStudentScalingAssert(
            in_array($student['id'], array_column($searched['students'], 'id'), true),
            'Exact student-ID search must return the eligible student.'
        );
    }

    $page = file_get_contents(__DIR__.'/../pages/sbo/students.html');
    sboStudentScalingAssert(substr_count($page, 'data-student-results') === 1, 'The page must have one responsive student-result host.');
    sboStudentScalingAssert(!str_contains($page, 'data-student-cards') && !str_contains($page, 'data-students'), 'The page must not render parallel mobile and desktop rosters.');

    echo "SBO student scaling checks passed.\n";
} finally {
    if ($database->inTransaction()) $database->rollBack();
}
