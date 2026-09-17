<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/faculty.php';
require_once __DIR__.'/student-attendance-qr.php';

$live=(new Database())->connection();
$source=(string)$live->query('SELECT DATABASE()')->fetchColumn();
$scratch='faculty_scan_test_'.bin2hex(random_bytes(4));
$tables=['tbl_roles','tbl_user_statuses','tbl_users','tbl_teams','tbl_team_user','tbl_locations','tbl_events','tbl_event_user',
    'tbl_event_team','tbl_event_year_level','tbl_event_participants','tbl_attendance_session_modes','tbl_event_attendance_schedules',
    'tbl_attendances','tbl_attendance_entries','tbl_attendance_qr_tokens','tbl_activity_logs','tbl_sbo_scan_rate_limits'];
$empty=['tbl_attendances','tbl_attendance_entries','tbl_attendance_qr_tokens','tbl_sbo_scan_rate_limits'];
$assert=static function(bool $result,string $message):void{if(!$result)throw new RuntimeException($message);echo 'PASS: '.$message.PHP_EOL;};
$live->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    foreach($tables as $table){
        $live->exec("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`");
        if(!in_array($table,$empty,true))$live->exec("INSERT INTO `$scratch`.`$table` SELECT * FROM `$source`.`$table`");
    }
    $db=(new Database(null,$scratch))->connection();
    $now=AttendanceScanWindows::now();
    if((int)$now->format('G')<1||(int)$now->format('G')>22)throw new RuntimeException('Run between 1:00 AM and 10:59 PM.');
    $date=$now->format('Y-m-d');
    $db->exec("UPDATE tbl_users SET role_id=(SELECT id FROM tbl_roles WHERE name='Faculty') WHERE id=14");
    $db->exec('INSERT INTO tbl_team_user(team_id,user_id,created_at,updated_at) VALUES(1,14,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
    $db->exec('INSERT IGNORE INTO tbl_event_user(event_id,user_id,created_at,updated_at) VALUES(1,14,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
    $db->exec("UPDATE tbl_locations SET latitude=8.4699237,longitude=124.6342058,radius=100 WHERE id=1");
    $db->prepare("UPDATE tbl_events SET start_at=?,end_at=?,audience_type='all_students',location_id=1,attendance_location_policy='strict' WHERE id=1")
        ->execute([$date.' 00:00:00',$date.' 23:59:59']);
    $db->prepare('UPDATE tbl_event_attendance_schedules SET schedule_date=?,attendance_session_mode_id=2,
        whole_day_in_time=?,whole_day_in_close_time=?,whole_day_out_open_time=?,whole_day_out_time=? WHERE id=1')
        ->execute([$date,$now->modify('-5 minutes')->format('H:i:s'),$now->modify('+5 minutes')->format('H:i:s'),
            $now->modify('+6 minutes')->format('H:i:s'),$now->modify('+20 minutes')->format('H:i:s')]);
    $students=$db->query("SELECT tu.team_id,u.id FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE tu.team_id IN (1,2) ORDER BY tu.team_id,u.id")->fetchAll();
    $student1=(int)current(array_filter($students,fn($row)=>(int)$row['team_id']===1))['id'];
    $student2=(int)current(array_filter($students,fn($row)=>(int)$row['team_id']===2))['id'];
    $repo=new FacultyRepository($db);$qr=new StudentAttendanceQrRepository($db);
    $sessions=$repo->scanSessions(14);
    $assert((bool)array_filter($sessions,fn($s)=>(int)$s['event_schedule_id']===1),'Assigned Faculty sees the event scan session');
    $assert(count($qr->issue(14)['sessions'])===0,'Faculty cannot receive a student QR');
    $token1=$qr->issue($student1)['sessions'][0]['token'];
    $payload=['schedule_id'=>1,'session'=>'whole_day','checkpoint'=>'in','mode'=>'qr','token'=>$token1,
        'location'=>['latitude'=>8.4699237,'longitude'=>124.6342058,'accuracy_m'=>5,'timestamp_ms'=>time()*1000]];
    $result=$repo->scan(14,$payload);
    $assert((int)$result['student']['team_id']===1,'Faculty records a student from the assigned team');
    $entry=$db->query('SELECT sbo_event_assignment_id,activity_id,team_id,recorded_by FROM tbl_attendance_entries LIMIT 1')->fetch();
    $assert($entry['sbo_event_assignment_id']===null&&$entry['activity_id']===null&&(int)$entry['team_id']===1&&(int)$entry['recorded_by']===14,
        'Faculty scan uses shared attendance entries with Faculty ownership');
    $db->prepare('UPDATE tbl_event_attendance_schedules SET whole_day_in_close_time=?,whole_day_out_open_time=?,whole_day_out_time=? WHERE id=1')
        ->execute([$now->modify('-1 minute')->format('H:i:s'),$now->modify('-30 seconds')->format('H:i:s'),$now->modify('+5 minutes')->format('H:i:s')]);
    $outToken=$qr->issue($student1)['sessions'][0]['token'];
    $out=$repo->scan(14,[...$payload,'checkpoint'=>'out','token'=>$outToken]);
    $assert($out['checkpoint']==='out'&&(int)$db->query("SELECT COUNT(*) FROM tbl_attendance_entries WHERE phase='out'")->fetchColumn()===1,
        'Faculty records Time Out through the shared session logic');
    $db->prepare('UPDATE tbl_event_attendance_schedules SET whole_day_in_close_time=?,whole_day_out_open_time=?,whole_day_out_time=? WHERE id=1')
        ->execute([$now->modify('+5 minutes')->format('H:i:s'),$now->modify('+6 minutes')->format('H:i:s'),$now->modify('+20 minutes')->format('H:i:s')]);
    $token2=$qr->issue($student2)['sessions'][0]['token'];
    try{$repo->scan(14,([...$payload,'token'=>$token2]));throw new RuntimeException('Cross-team scan was accepted.');}
    catch(InvalidArgumentException $e){$assert(str_contains($e->getMessage(),'limited to'),'Another team’s student is rejected');}
    $assert((int)$db->query('SELECT COUNT(*) FROM tbl_attendance_entries')->fetchColumn()===2,'Rejected QR did not create attendance');
    try{$repo->scan(14,([...$payload,'mode'=>'manual','student_id'=>'02-2026-000001']));throw new RuntimeException('Manual Faculty entry was accepted.');}
    catch(DomainException $e){$assert(true,'Faculty cannot record attendance without a student QR');}
} finally {
    $live->exec("DROP DATABASE IF EXISTS `$scratch`");
}
