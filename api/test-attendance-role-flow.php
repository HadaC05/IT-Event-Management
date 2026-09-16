<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/SboAuthorization.php';
require_once __DIR__.'/sbo-attendance.php';
require_once __DIR__.'/student-attendance-qr.php';
require_once __DIR__.'/adviser-attendance-scans.php';

$live = (new Database())->connection();
$source = (string) $live->query('SELECT DATABASE()')->fetchColumn();
$scratch = 'attendance_flow_test_'.bin2hex(random_bytes(4));
if (!preg_match('/^attendance_flow_test_[0-9a-f]{8}$/', $scratch)) throw new RuntimeException('Invalid test database name.');

$tables = [
    'tbl_roles','tbl_user_statuses','tbl_users','tbl_teams','tbl_team_user','tbl_locations','tbl_event_types','tbl_events',
    'tbl_attendance_session_modes','tbl_event_attendance_schedules','tbl_event_activities','tbl_event_team',
    'tbl_event_year_level','tbl_event_participants','tbl_sbo_officer_assignments','tbl_sbo_event_assignments',
    'tbl_attendances','tbl_attendance_entries','tbl_attendance_qr_tokens','tbl_activity_logs','tbl_sbo_scan_rate_limits',
];
$empty = ['tbl_sbo_event_assignments','tbl_attendances','tbl_attendance_entries','tbl_attendance_qr_tokens','tbl_sbo_scan_rate_limits'];
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    echo 'PASS: '.$message, PHP_EOL;
};
$expect = static function (callable $action, string $type, string $message) use ($assert): void {
    try { $action(); $assert(false, $message); }
    catch (Throwable $exception) { $assert($exception instanceof $type, $message); }
};

$live->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    foreach ($tables as $table) {
        $live->exec("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`");
        if (!in_array($table, $empty, true)) $live->exec("INSERT INTO `$scratch`.`$table` SELECT * FROM `$source`.`$table`");
    }

    $db = (new Database(null, $scratch))->connection();
    $zone = new DateTimeZone('Asia/Manila');
    $now = new DateTimeImmutable('now', $zone);
    $today = $now->format('Y-m-d');
    $formatTime = static fn(DateTimeImmutable $value): string => $value->format('H:i:s');
    if ((int) $now->format('G') < 1 || (int) $now->format('G') > 22) throw new RuntimeException('Run this flow test between 1:00 AM and 10:59 PM.');

    $db->exec("UPDATE tbl_locations SET name='Role Flow Venue',latitude=8.4699237,longitude=124.6342058,radius=100 WHERE id=1");
    $db->prepare("UPDATE tbl_events SET title='Role Flow Test Event',start_at=?,end_at=?,audience_type='all_students',location_id=1,attendance_location_policy='strict' WHERE id=1")
        ->execute([$today.' 00:00:00', $today.' 23:59:59']);
    $db->prepare('UPDATE tbl_event_attendance_schedules SET schedule_date=?,attendance_session_mode_id=2,
        whole_day_in_time=?,whole_day_in_close_time=?,whole_day_out_open_time=?,whole_day_out_time=? WHERE id=1')
        ->execute([$today,$formatTime($now->modify('-10 minutes')),$formatTime($now->modify('+10 minutes')),
            $formatTime($now->modify('+20 minutes')),$formatTime($now->modify('+40 minutes'))]);
    $db->exec("INSERT INTO tbl_event_activities(event_id,name,status,created_by,created_at,updated_at)
        VALUES(1,'Role Flow Attendance','active',14,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    $activity = (int) $db->lastInsertId();
    $db->prepare("INSERT INTO tbl_sbo_event_assignments(officer_assignment_id,event_schedule_id,session_code,activity_id,team_id,responsibility,status,assigned_by,created_at,updated_at)
        VALUES(1,1,'whole_day',?,1,'attendance','active',14,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$activity]);
    $assignment = (int) $db->lastInsertId();

    $students = $db->query("SELECT u.id,u.id_number FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id
        JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE tu.team_id=1 ORDER BY u.id LIMIT 2")->fetchAll();
    $assert(count($students) === 2, 'Adviser flow has an assigned officer, team, event, venue, and two students');

    $studentQr = new StudentAttendanceQrRepository($db);
    $studentState = $studentQr->issue((int) $students[0]['id']);
    $studentCard = current(array_filter($studentState['sessions'], static fn(array $card): bool => $card['event_name'] === 'Role Flow Test Event'));
    $assert(is_array($studentCard) && $studentCard['state'] === 'in_open' && $studentCard['phase'] === 'in' && strlen((string) $studentCard['token']) === 32,
        'Student receives a valid short-lived Time In QR');

    $authorization = new SboAuthorization($db);
    $scanner = new SboAttendanceRepository($db, $authorization);
    $dashboard = $scanner->dashboard(15);
    $assert($dashboard['selected_assignment']['id'] === $assignment && $dashboard['selected_assignment']['in_window_open'] === true,
        'SBO Officer sees the active attendance assignment');

    $inside = ['latitude'=>8.4699237,'longitude'=>124.6342058,'accuracy_m'=>15,'timestamp_ms'=>time()*1000];
    $scanIn = $scanner->scan(15, ['assignment_id'=>$assignment,'checkpoint'=>'in','mode'=>'qr','token'=>$studentCard['token'],'location'=>$inside]);
    $assert($scanIn['checkpoint'] === 'in' && $scanIn['location']['status'] === 'inside',
        'SBO Officer records Time In with a system QR inside the saved square boundary');

    $expect(static fn() => $scanner->scan(15, ['assignment_id'=>$assignment,'checkpoint'=>'in','mode'=>'qr','token'=>str_repeat('x',32),'location'=>$inside]),
        InvalidArgumentException::class, 'Scanner rejects a QR that was not generated by the system');

    $secondState = $studentQr->issue((int) $students[1]['id']);
    $secondCard = current(array_filter($secondState['sessions'], static fn(array $card): bool => $card['event_name'] === 'Role Flow Test Event'));
    $outside = ['latitude'=>8.4809237,'longitude'=>124.6342058,'accuracy_m'=>15,'timestamp_ms'=>time()*1000];
    $expect(static fn() => $scanner->scan(15, ['assignment_id'=>$assignment,'checkpoint'=>'in','mode'=>'qr','token'=>$secondCard['token'],'location'=>$outside]),
        InvalidArgumentException::class, 'Strict scanning rejects an officer outside the saved location box');

    $db->exec("UPDATE tbl_events SET attendance_location_policy='warning' WHERE id=1");
    $warningDashboard = $scanner->dashboard(15, $assignment);
    $assert($warningDashboard['selected_assignment']['location_policy'] === 'warning',
        'SBO Officer receives the Adviser Warning policy immediately on the next request');
    $expect(static fn() => $scanner->scan(15, [
        'assignment_id'=>$assignment,'checkpoint'=>'in','mode'=>'qr','token'=>$secondCard['token'],
        'location'=>['unavailable_reason'=>'not_provided'],
    ]), InvalidArgumentException::class, 'Warning requires an available GPS position');
    $warningScan = $scanner->scan(15, [
        'assignment_id'=>$assignment,'checkpoint'=>'in','mode'=>'qr','token'=>$secondCard['token'],'location'=>$outside,
    ]);
    $assert($warningScan['checkpoint'] === 'in' && $warningScan['location']['status'] === 'outside',
        'Warning allows a valid student QR scan outside the venue with GPS');
    $expect(static fn() => $scanner->scan(15, [
        'assignment_id'=>$assignment,'checkpoint'=>'in','mode'=>'qr','token'=>str_repeat('z',32),
        'location'=>$outside,
    ]), InvalidArgumentException::class, 'Warning still rejects a QR that was not generated by the system');
    $db->exec("UPDATE tbl_events SET attendance_location_policy='strict' WHERE id=1");

    $db->prepare('UPDATE tbl_event_attendance_schedules SET whole_day_in_time=?,whole_day_in_close_time=?,whole_day_out_open_time=?,whole_day_out_time=? WHERE id=1')
        ->execute([$formatTime($now->modify('-30 minutes')),$formatTime($now->modify('-20 minutes')),
            $formatTime($now->modify('-10 minutes')),$formatTime($now->modify('+10 minutes'))]);
    $outState = $studentQr->issue((int) $students[0]['id']);
    $outCard = current(array_filter($outState['sessions'], static fn(array $card): bool => $card['event_name'] === 'Role Flow Test Event'));
    $assert(is_array($outCard) && $outCard['state'] === 'out_open' && $outCard['phase'] === 'out' && $outCard['token'] !== $studentCard['token'],
        'Student receives a separate short-lived Time Out QR after Time In');
    $scanOut = $scanner->scan(15, ['assignment_id'=>$assignment,'checkpoint'=>'out','mode'=>'qr','token'=>$outCard['token'],'location'=>$inside]);
    $assert($scanOut['checkpoint'] === 'out' && $scanOut['counts']['checked_out'] === 1,
        'SBO Officer records Time Out and progress updates');

    $adviserView = (new AdviserAttendanceScanRepository($db))->index(['event_id'=>'1']);
    $insideScans = array_values(array_filter($adviserView['scans'], static fn(array $scan): bool => $scan['location_status'] === 'inside'));
    $outsideScans = array_values(array_filter($adviserView['scans'], static fn(array $scan): bool => $scan['location_status'] === 'outside'));
    $assert(count($adviserView['scans']) === 3 && count($insideScans) === 2 && count($outsideScans) === 1,
        'SBO Adviser can review strict inside scans and the Warning outside scan');

    $todayView = (new AdviserAttendanceScanRepository($db))->index(['schedule_date'=>$today]);
    $scheduledEvent = current(array_filter($todayView['options']['events'], static fn(array $event): bool =>
        (int)$event['id'] === 1 && $event['schedule_date'] === $today
    ));
    $assert(count($todayView['scans']) === 3 && $scheduledEvent && $scheduledEvent['venue_latitude'] !== null,
        'Adviser date filter returns every scan and mapped event boundary for the selected day');
} finally {
    $exists = $live->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
    $exists->execute([$scratch]);
    if ($exists->fetchColumn()) $live->exec("DROP DATABASE `$scratch`");
}
