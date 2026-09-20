<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/SboAuthorization.php';
require_once __DIR__.'/sbo-attendance.php';
require_once __DIR__.'/adviser-attendance-scans.php';
require_once __DIR__.'/student-attendance-qr.php';
require_once __DIR__.'/sbo-assignments.php';

$live=(new Database())->connection();
$originalDbName=getenv('DB_NAME');
$source=(string)$live->query('SELECT DATABASE()')->fetchColumn();
$scratch='event_qr_test_'.bin2hex(random_bytes(4));
if(!preg_match('/^event_qr_test_[0-9a-f]{8}$/',$scratch))throw new RuntimeException('Invalid test database name.');
$exists=$live->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
$exists->execute([$scratch]);
if($exists->fetchColumn())throw new RuntimeException('Test database name already exists.');
$tables=['tbl_roles','tbl_user_statuses','tbl_users','tbl_teams','tbl_team_user','tbl_locations',
    'tbl_events','tbl_event_locations','tbl_attendance_session_modes','tbl_event_attendance_schedules','tbl_event_activities',
    'tbl_event_team','tbl_event_year_level','tbl_event_participants','tbl_sbo_officer_assignments',
    'tbl_sbo_event_assignments','tbl_attendances','tbl_attendance_entries','tbl_attendance_qr_tokens',
    'tbl_activity_logs','tbl_sbo_scan_rate_limits'];
$assert=function(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label,PHP_EOL;};
$expect=function(callable $action,string $type,string $message='')use($assert):void{
    try{$action();$assert(false,$type.' rejection');}
    catch(Throwable $e){$assert($e instanceof $type&&($message===''||str_contains($e->getMessage(),$message)),$type.' rejection '.$message);}
};
$live->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    foreach($tables as $table){
        $live->exec("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`");
        if($table!=='tbl_sbo_scan_rate_limits'&&$table!=='tbl_attendance_entries'&&$table!=='tbl_attendance_qr_tokens'&&$table!=='tbl_sbo_event_assignments')
            $live->exec("INSERT INTO `$scratch`.`$table` SELECT * FROM `$source`.`$table`");
    }
    putenv('DB_NAME='.$scratch);
    $db=(new Database())->connection();
    $zone=new DateTimeZone('Asia/Manila');$now=new DateTimeImmutable('now',$zone);
    $today=$now->format('Y-m-d');$tomorrow=$now->modify('+1 day')->format('Y-m-d');$third=$now->modify('+2 days')->format('Y-m-d');
    $start=$now->modify('-1 hour')->format('H:i:s');$end=$now->modify('+1 hour')->format('H:i:s');
    $inClose=$now->modify('+10 minutes')->format('H:i:s');$outOpen=$now->modify('+20 minutes')->format('H:i:s');
    $db->exec("UPDATE tbl_locations SET latitude=8.4699237,longitude=124.6342058,radius=100 WHERE id=1");
    $db->prepare("UPDATE tbl_events SET start_at=?,end_at=?,audience_type='all_students',location_id=1,attendance_location_policy='strict' WHERE id=1")
        ->execute([$today.' 00:00:00',$third.' 23:59:59']);
    $db->prepare("UPDATE tbl_event_attendance_schedules SET schedule_date=?,attendance_session_mode_id=2,whole_day_in_time=?,whole_day_in_close_time=?,whole_day_out_open_time=?,whole_day_out_time=? WHERE id=1")
        ->execute([$today,$start,$inClose,$outOpen,$end]);
    $db->prepare("INSERT INTO tbl_event_attendance_schedules(event_id,schedule_date,attendance_session_mode_id,whole_day_in_time,whole_day_out_time,created_at,updated_at) VALUES(1,?,2,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
        ->execute([$tomorrow,$start,$end]);$day2=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO tbl_event_attendance_schedules(event_id,schedule_date,attendance_session_mode_id,whole_day_in_time,whole_day_out_time,created_at,updated_at) VALUES(1,?,2,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
        ->execute([$third,$start,$end]);$day3=(int)$db->lastInsertId();
    $db->exec("INSERT INTO tbl_event_activities(event_id,name,status,created_by,created_at,updated_at) VALUES(1,'Attendance Test','active',14,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    $activity=(int)$db->lastInsertId();
    $addAssignment=$db->prepare("INSERT INTO tbl_sbo_event_assignments(officer_assignment_id,event_schedule_id,session_code,activity_id,team_id,responsibility,status,assigned_by,created_at,updated_at) VALUES(1,?,'whole_day',?,?,'attendance','active',14,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    $addAssignment->execute([1,$activity,1]);$assignment=(int)$db->lastInsertId();
    $auth=new SboAuthorization($db);$repository=new SboAttendanceRepository($db,$auth);
    $officer=15;
    $students=$db->query("SELECT u.id,u.id_number,tu.team_id FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE tu.team_id IN(1,2) ORDER BY tu.team_id,u.id")->fetchAll();
    $team1=array_values(array_filter($students,fn($s)=>(int)$s['team_id']===1));
    $team2=array_values(array_filter($students,fn($s)=>(int)$s['team_id']===2));
    $assert(count($team1)>=2&&count($team2)>=1,'student fixtures available');
    $dashboard=$repository->dashboard($officer);
    $assert(count($dashboard['assignments'])===1&&$dashboard['selected_assignment']['id']===$assignment,'single assignment auto selected');
    $assert($dashboard['selected_assignment']['is_session_active']===true,'active session detected with server time');
    $assert($repository->dashboard(14)['selected_assignment']===null,'officer with no assignment');
    $tokenStatement=$db->prepare('INSERT INTO tbl_attendance_qr_tokens(event_id,user_id,session,token,created_at,updated_at) VALUES(1,?,\'whole_day\',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
    $tokens=[];
    foreach($students as $student){$token=rtrim(strtr(base64_encode(random_bytes(24)),'+/','-_'),'=');$tokens[(int)$student['id']]=$token;$tokenStatement->execute([$student['id'],$token]);}
    $inside=['latitude'=>8.4699237,'longitude'=>124.6342058,'accuracy_m'=>15,'timestamp_ms'=>time()*1000];
    $outside=['latitude'=>8.4799237,'longitude'=>124.6342058,'accuracy_m'=>15,'timestamp_ms'=>time()*1000];
    $payload=fn(array $student,array $location)=>['assignment_id'=>$assignment,'mode'=>'qr','token'=>$tokens[(int)$student['id']],'location'=>$location];
    $result=$repository->scan($officer,$payload($team1[0],$inside));
    $assert($result['location']['status']==='inside'&&count($result['recent_scans'])===1,'valid assigned-team scan inside venue');
    $expect(fn()=>$repository->scan($officer,$payload($team1[0],$inside)),LogicException::class,'already recorded');
    $closingEnd=$now->modify('+10 minutes')->format('H:i:s');
    $db->prepare('UPDATE tbl_event_attendance_schedules SET whole_day_in_close_time=?,whole_day_out_open_time=?,whole_day_out_time=? WHERE id=1')
        ->execute([$now->modify('-2 minutes')->format('H:i:s'),$now->modify('-1 minute')->format('H:i:s'),$closingEnd]);
    $outPayload=$payload($team1[0],$inside)+['checkpoint'=>'out'];
    $expect(fn()=>$repository->scan($officer,$payload($team1[1],$inside)+['checkpoint'=>'out']),LogicException::class,'time in');
    $timeOut=$repository->scan($officer,$outPayload);
    $assert($timeOut['checkpoint']==='out'&&$timeOut['counts']['checked_out']===1&&$timeOut['recent_scans'][0]['out_at']!==null,
        'same student QR records time out and updates officer progress');
    $savedTime=$db->prepare('SELECT morning_in_at,morning_out_at FROM tbl_attendances WHERE event_id=1 AND user_id=?');
    $savedTime->execute([$team1[0]['id']]);
    $times=$savedTime->fetch();
    $assert($times['morning_in_at']!==null&&$times['morning_out_at']!==null,'student attendance shows both time in and time out');
    $expect(fn()=>$repository->scan($officer,$outPayload),LogicException::class,'already recorded');
    $db->prepare('UPDATE tbl_event_attendance_schedules SET whole_day_out_time=? WHERE id=1')->execute([$end]);
    $expect(fn()=>$repository->scan($officer,$payload($team2[0],$inside)),InvalidArgumentException::class,'belongs to');
    $expect(fn()=>$repository->scan($officer,['assignment_id'=>$assignment,'mode'=>'qr','token'=>str_repeat('x',32),'location'=>$inside]),InvalidArgumentException::class,'Invalid QR');
    $db->exec("UPDATE tbl_events SET audience_type='specific_students' WHERE id=1");
    $expect(fn()=>$repository->scan($officer,$payload($team1[1],$inside)),InvalidArgumentException::class,'not registered');
    $db->exec("UPDATE tbl_events SET audience_type='all_students' WHERE id=1");
    $expect(fn()=>$repository->scan($officer,$payload($team1[1],$outside)),InvalidArgumentException::class,'outside');
    $expect(fn()=>$repository->scan($officer,$payload($team1[1],['unavailable_reason'=>'permission_denied'])),InvalidArgumentException::class,'could not be verified');
    $expect(fn()=>$repository->scan($officer,$payload($team1[1],array_replace($inside,['timestamp_ms'=>(time()-40)*1000]))),InvalidArgumentException::class,'could not be verified');
    $db->exec("UPDATE tbl_events SET attendance_location_policy='warning' WHERE id=1");
    $warning=$repository->scan($officer,$payload($team1[1],$outside));
    $assert($warning['location']['status']==='outside','warning mode records outside venue');
    $pastEnd=$now->modify('-10 minutes')->format('H:i:s');
    $db->prepare('UPDATE tbl_event_attendance_schedules SET whole_day_out_time=? WHERE id=1')->execute([$pastEnd]);
    $db->prepare('UPDATE tbl_events SET end_at=? WHERE id=1')->execute([$today.' '.$pastEnd]);
    $afterEndQr=(new StudentAttendanceQrRepository($db))->issue((int)$team1[1]['id']);
    $assert($afterEndQr['token']===$tokens[(int)$team1[1]['id']],'student QR remains available shortly after session end');
    $afterEndOut=$repository->scan($officer,$payload($team1[1],$outside)+['checkpoint'=>'out']);
    $assert($afterEndOut['checkpoint']==='out','officer records time out after scheduled event end');
    $db->prepare('UPDATE tbl_event_attendance_schedules SET whole_day_out_time=? WHERE id=1')->execute([$end]);
    $db->prepare('UPDATE tbl_events SET end_at=? WHERE id=1')->execute([$third.' 23:59:59']);
    if(isset($team1[2])){
        $expect(fn()=>$repository->scan($officer,$payload($team1[2],['unavailable_reason'=>'permission_denied'])),InvalidArgumentException::class,'could not be verified');
    }
    $scanView=(new AdviserAttendanceScanRepository($db))->index(['event_id'=>'1','location_status'=>'inside']);
    $assert(count($scanView['scans'])===1&&$scanView['scans'][0]['scan_latitude']!==null,'adviser can view scan coordinates');
    $issued=(new StudentAttendanceQrRepository($db))->issue((int)$team1[0]['id']);
    $assert($issued['token']===$tokens[(int)$team1[0]['id']]&&strlen($issued['token'])===32,'student QR contains only the saved random token');
    $expect(fn()=>$repository->scan($officer,$payload($team1[0],$inside)+['event_id'=>999,'team_id'=>999,'session_id'=>999,'officer_id'=>999]),LogicException::class,'already recorded');
    $assert((int)$db->query('SELECT COUNT(*) FROM tbl_attendance_entries')->fetchColumn()===3,'client-supplied event/team/officer IDs ignored');
    $warningContext=$repository->dashboard($officer,$assignment)['selected_assignment'];
    $warningContext['location_policy']='warning';
    $locationMethod=new ReflectionMethod(SboAttendanceRepository::class,'location');
    $assert($locationMethod->invoke($repository,$warningContext,$outside)['status']==='outside','Warning mode permits a GPS position outside the venue');
    $expect(fn()=>$locationMethod->invoke($repository,$warningContext,['unavailable_reason'=>'permission_denied']),InvalidArgumentException::class,'could not be verified');
    $db->prepare('UPDATE tbl_attendance_qr_tokens SET updated_at=? WHERE event_id=1 AND user_id=? AND session=?')
        ->execute([$now->modify('-1 day')->format('Y-m-d H:i:s'),$team1[0]['id'],'whole_day']);
    $rotated=(new StudentAttendanceQrRepository($db))->issue((int)$team1[0]['id']);
    $assert($rotated['token']!==$tokens[(int)$team1[0]['id']],'student token rotates on a new event day');
    $expect(fn()=>$repository->scan($officer,$payload($team1[0],$inside)),InvalidArgumentException::class,'Invalid QR');
    $addAssignment->execute([1,$activity,2]);
    $assert(count($repository->dashboard($officer)['assignments'])===2,'multiple assignments exposed');
    $taskManager=new SboAssignmentRepository($db);
    $assignedTaskId=$taskManager->assign([
        'officer_assignment_id'=>1,'event_schedule_id'=>1,'session_code'=>'whole_day',
        'activity_name'=>'Event duties','team_id'=>1,'responsibility'=>'attendance',
    ],14);
    $assignedTask=current(array_filter($taskManager->index()['tasks'],fn($task)=>(int)$task['id']===$assignedTaskId));
    $assert($assignedTask&&$assignedTask['event_name']==='IT Days 2026'&&$assignedTask['responsibility']==='attendance',
        'adviser assigns officer to a specific event through responsibility API');
    $db->prepare("UPDATE tbl_event_activities SET status='inactive' WHERE event_id=1 AND name='Event duties'")->execute();
    $reactivatedId=$taskManager->assign([
        'officer_assignment_id'=>1,'event_schedule_id'=>1,'session_code'=>'whole_day',
        'activity_name'=>'Event duties','team_id'=>1,'responsibility'=>'media',
    ],14);
    $visibleIds=array_column($taskManager->index()['tasks'],'id');
    $assert(in_array($reactivatedId,$visibleIds,true)&&in_array($assignedTaskId,$visibleIds,true),
        'reassigning an inactive activity restores officer access and visibility');
    $addAssignment->execute([$day2,$activity,1]);$addAssignment->execute([$day3,$activity,1]);
    $days=$auth->assignments($officer,'attendance',false);
    $numbers=array_column($days,'day_number');
    $assert(in_array(1,$numbers,true)&&in_array(2,$numbers,true)&&in_array(3,$numbers,true),'Day 1, Day 2, Day 3 derived from dates');
    $db->prepare('UPDATE tbl_event_attendance_schedules SET whole_day_in_time=?,whole_day_out_time=? WHERE id=1')->execute(['01:00:00','02:00:00']);
    $expect(fn()=>$repository->scan($officer,$payload($team1[0],$inside)),InvalidArgumentException::class,'closed');
    $db->prepare('UPDATE tbl_event_attendance_schedules SET whole_day_in_time=?,whole_day_out_time=? WHERE id=1')->execute([$start,$end]);
    for($i=0;$i<21;$i++){try{$repository->scan($officer,['assignment_id'=>9999,'mode'=>'qr','token'=>str_repeat('x',32)]);}catch(DomainException|InvalidArgumentException|ScanRateLimitException){}}
    $expect(fn()=>$repository->scan($officer,['assignment_id'=>9999,'mode'=>'qr','token'=>str_repeat('x',32)]),ScanRateLimitException::class,'Too many');
    $assert((int)$db->query('SELECT COUNT(*) FROM tbl_attendance_entries')->fetchColumn()>=2,'duplicate attempts did not create extra scan entries');
} finally {
    $originalDbName===false?putenv('DB_NAME'):putenv('DB_NAME='.$originalDbName);
    $check=$live->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');$check->execute([$scratch]);
    if($check->fetchColumn())$live->exec("DROP DATABASE `$scratch`");
}
