<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/faculty.php';
require_once __DIR__.'/student-attendance-qr.php';

$live=(new Database())->connection();
$source=(string)$live->query('SELECT DATABASE()')->fetchColumn();
$scratch='faculty_scan_test_'.bin2hex(random_bytes(4));
$tables=['tbl_roles','tbl_user_statuses','tbl_users','tbl_teams','tbl_team_user','tbl_locations','tbl_events','tbl_event_locations','tbl_event_user',
    'tbl_event_team','tbl_event_year_level','tbl_event_participants','tbl_event_membership_snapshots','tbl_attendance_session_modes','tbl_event_attendance_schedules',
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
    $facultyId=(int)$db->query('SELECT id FROM tbl_users ORDER BY id LIMIT 1')->fetchColumn();
    $teamRows=$db->query("SELECT t.id FROM tbl_teams t JOIN tbl_team_user tu ON tu.team_id=t.id JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' GROUP BY t.id HAVING COUNT(*)>0 ORDER BY t.id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
    if(count($teamRows)<2)throw new RuntimeException('The Faculty scanner test requires two teams with students.');
    [$team1,$team2]=array_map('intval',$teamRows);
    $eventId=(int)$db->query('SELECT id FROM tbl_events ORDER BY id LIMIT 1')->fetchColumn();
    $scheduleId=(int)$db->query("SELECT id FROM tbl_event_attendance_schedules WHERE event_id=$eventId ORDER BY id LIMIT 1")->fetchColumn();
    $locationId=(int)$db->query('SELECT id FROM tbl_locations ORDER BY id LIMIT 1')->fetchColumn();
    if(!$facultyId||!$eventId||!$scheduleId||!$locationId)throw new RuntimeException('The Faculty scanner test fixture is incomplete.');
    $db->prepare("UPDATE tbl_users SET role_id=(SELECT id FROM tbl_roles WHERE name='Faculty') WHERE id=?")->execute([$facultyId]);
    $db->prepare('DELETE FROM tbl_team_user WHERE user_id=?')->execute([$facultyId]);
    $db->prepare('INSERT INTO tbl_team_user(team_id,user_id,created_at,updated_at) VALUES(?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([$team1,$facultyId]);
    $db->prepare('UPDATE tbl_locations SET latitude=8.4699237,longitude=124.6342058,radius=100 WHERE id=?')->execute([$locationId]);
    $db->prepare("UPDATE tbl_events SET start_at=?,end_at=?,audience_type='all_students',location_id=?,attendance_location_policy='strict' WHERE id=?")
        ->execute([$date.' 00:00:00',$date.' 23:59:59',$locationId,$eventId]);
    $db->prepare('UPDATE tbl_event_attendance_schedules SET schedule_date=?,attendance_session_mode_id=2,
        whole_day_in_time=?,whole_day_in_close_time=?,whole_day_out_open_time=?,whole_day_out_time=? WHERE id=?')
        ->execute([$date,$now->modify('-5 minutes')->format('H:i:s'),$now->modify('+5 minutes')->format('H:i:s'),
            $now->modify('+6 minutes')->format('H:i:s'),$now->modify('+20 minutes')->format('H:i:s'),$scheduleId]);
    $studentStatement=$db->prepare("SELECT u.id,u.id_number FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE tu.team_id=? ORDER BY u.id LIMIT 1");
    $studentStatement->execute([$team1]);$student1Row=$studentStatement->fetch();
    $studentStatement->execute([$team2]);$student2Row=$studentStatement->fetch();
    $student1=(int)$student1Row['id'];$student2=(int)$student2Row['id'];
    $repo=new FacultyRepository($db);$qr=new StudentAttendanceQrRepository($db);
    $sessions=$repo->scanSessions($facultyId);
    $assert((bool)array_filter($sessions,fn($s)=>(int)$s['event_schedule_id']===$scheduleId),'A team-assigned Faculty automatically sees the event attendance session');
    $dashboard=$repo->attendance($facultyId);
    $assert(!empty($dashboard['assignments'])&&isset($dashboard['assignments'][0]['venues'],$dashboard['counts']['remaining']),
        'Faculty receives the complete SBO-style scanner dashboard and GPS venue data');
    $assert(count($qr->issue($facultyId)['sessions'])===0,'Faculty cannot receive a student QR');
    $token1=$qr->issue($student1)['sessions'][0]['token'];
    $payload=['schedule_id'=>$scheduleId,'session'=>'whole_day','checkpoint'=>'in','mode'=>'qr','token'=>$token1,
        'location'=>['latitude'=>8.4699237,'longitude'=>124.6342058,'accuracy_m'=>5,'timestamp_ms'=>time()*1000]];
    $result=$repo->scan($facultyId,$payload);
    $assert((int)$result['student']['team_id']===$team1,'Faculty records a student from the assigned team');
    $entry=$db->query('SELECT sbo_event_assignment_id,activity_id,team_id,recorded_by FROM tbl_attendance_entries LIMIT 1')->fetch();
    $assert($entry['sbo_event_assignment_id']===null&&$entry['activity_id']===null&&(int)$entry['team_id']===$team1&&(int)$entry['recorded_by']===$facultyId,
        'Faculty scan uses shared attendance entries with Faculty ownership');
    $db->prepare('UPDATE tbl_event_attendance_schedules SET whole_day_in_close_time=?,whole_day_out_open_time=?,whole_day_out_time=? WHERE id=?')
        ->execute([$now->modify('-1 minute')->format('H:i:s'),$now->modify('-30 seconds')->format('H:i:s'),$now->modify('+5 minutes')->format('H:i:s'),$scheduleId]);
    $outToken=$qr->issue($student1)['sessions'][0]['token'];
    $out=$repo->scan($facultyId,[...$payload,'checkpoint'=>'out','token'=>$outToken]);
    $assert($out['checkpoint']==='out'&&(int)$db->query("SELECT COUNT(*) FROM tbl_attendance_entries WHERE phase='out'")->fetchColumn()===1,
        'Faculty records Time Out through the shared session logic');
    $db->prepare('UPDATE tbl_event_attendance_schedules SET whole_day_in_close_time=?,whole_day_out_open_time=?,whole_day_out_time=? WHERE id=?')
        ->execute([$now->modify('+5 minutes')->format('H:i:s'),$now->modify('+6 minutes')->format('H:i:s'),$now->modify('+20 minutes')->format('H:i:s'),$scheduleId]);
    $token2=$qr->issue($student2)['sessions'][0]['token'];
    try{$repo->scan($facultyId,([...$payload,'token'=>$token2]));throw new RuntimeException('Cross-team scan was accepted.');}
    catch(InvalidArgumentException $e){$assert(str_contains($e->getMessage(),'limited to'),'Another team’s student is rejected');}
    $assert((int)$db->query('SELECT COUNT(*) FROM tbl_attendance_entries')->fetchColumn()===2,'Rejected QR did not create attendance');
    try{$repo->scan($facultyId,([...$payload,'mode'=>'manual','student_id'=>$student2Row['id_number']]));throw new RuntimeException('Cross-team manual entry was accepted.');}
    catch(InvalidArgumentException $e){$assert(str_contains($e->getMessage(),'limited to'),'Faculty manual entry is also restricted to the assigned team');}
} finally {
    $live->exec("DROP DATABASE IF EXISTS `$scratch`");
}
