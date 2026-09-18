<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/student-attendance-qr.php';
require_once __DIR__.'/SboAuthorization.php';
require_once __DIR__.'/sbo-attendance.php';
require_once __DIR__.'/adviser-events.php';
require_once __DIR__.'/officers.php';

$live=(new Database())->connection();
$source=(string)$live->query('SELECT DATABASE()')->fetchColumn();
if (!preg_match('/^[A-Za-z0-9_]+$/',$source)) throw new RuntimeException('Unsafe source database name.');
$scratch='phase_qr_test_'.bin2hex(random_bytes(4));
if (!preg_match('/^phase_qr_test_[0-9a-f]{8}$/',$scratch)) throw new RuntimeException('Unsafe test database name.');
$exists=$live->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
$exists->execute([$scratch]);
if ($exists->fetchColumn()) throw new RuntimeException('Test database name already exists.');
$tables=['tbl_roles','tbl_user_statuses','tbl_users','tbl_teams','tbl_team_user','tbl_locations',
    'tbl_events','tbl_event_locations','tbl_event_statuses','tbl_event_types','tbl_attendance_session_modes','tbl_event_attendance_schedules',
    'tbl_event_activities','tbl_event_user','tbl_event_team','tbl_event_year_level','tbl_event_participants','tbl_sbo_officer_assignments',
    'tbl_sbo_event_assignments','tbl_attendances','tbl_attendance_entries','tbl_attendance_qr_tokens',
    'tbl_activity_logs','tbl_sbo_scan_rate_limits'];
$empty=['tbl_sbo_event_assignments','tbl_attendances','tbl_attendance_entries','tbl_attendance_qr_tokens',
    'tbl_activity_logs','tbl_sbo_scan_rate_limits'];
$assert=static function(bool $okay,string $label):void { if (!$okay) throw new RuntimeException('FAIL: '.$label); echo 'PASS: '.$label,PHP_EOL; };
$reject=static function(callable $action,string $exceptionClass,string $label) use ($assert):void {
    try { $action(); $assert(false,$label); }
    catch(Throwable $error) { $assert($error instanceof $exceptionClass,$label.' ('.get_class($error).')'); }
};

$live->prepare("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")->execute();
try {
    foreach ($tables as $table) {
        $live->prepare("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`")->execute();
        if (!in_array($table,$empty,true))
            $live->prepare("INSERT INTO `$scratch`.`$table` SELECT * FROM `$source`.`$table`")->execute();
    }
    $db=(new Database(null,$scratch))->connection();
    $now=AttendanceScanWindows::now();$today=$now->format('Y-m-d');
    if ($now->modify('+20 minutes')->format('Y-m-d')!==$today)
        throw new RuntimeException('Run this test at least 20 minutes before Manila midnight.');
    $inOpen=$now->modify('-5 minutes')->format('H:i:s');
    $inClose=$now->modify('+5 minutes')->format('H:i:s');
    $outOpen=$now->modify('+10 minutes')->format('H:i:s');
    $outClose=$now->modify('+20 minutes')->format('H:i:s');
    $db->prepare("UPDATE tbl_events SET start_at=?,end_at=?,audience_type='all_students',location_id=1,attendance_location_policy='warning' WHERE id=1")
        ->execute([$today.' 00:00:00',$today.' 23:59:59']);
    $db->prepare("UPDATE tbl_sbo_officer_assignments SET team_id=1,scanner_mode='specific' WHERE id=1")->execute();
    $db->prepare('UPDATE tbl_locations SET latitude=?,longitude=?,radius=? WHERE id=1')->execute([8.4699237,124.6342058,100]);
    $db->prepare('UPDATE tbl_event_attendance_schedules SET schedule_date=?,attendance_session_mode_id=2,
        whole_day_in_time=?,whole_day_in_close_time=?,whole_day_out_open_time=?,whole_day_out_time=? WHERE id=1')
        ->execute([$today,$inOpen,$inClose,$outOpen,$outClose]);
    $db->prepare("INSERT INTO tbl_event_activities(event_id,name,status,created_by,created_at,updated_at)
        VALUES(1,'Phase QR test','active',14,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute();
    $activity=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO tbl_sbo_event_assignments(officer_assignment_id,event_schedule_id,session_code,activity_id,team_id,responsibility,status,assigned_by,created_at,updated_at)
        VALUES(1,1,'whole_day',?,1,'attendance','active',14,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$activity]);
    $assignment=(int)$db->lastInsertId();
    $fixtures=$db->query("SELECT u.id,tu.team_id FROM tbl_users u JOIN tbl_team_user tu ON tu.user_id=u.id
        JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE tu.team_id IN(1,2) ORDER BY tu.team_id,u.id")->fetchAll();
    $team1=array_values(array_filter($fixtures,static fn(array $row):bool=>(int)$row['team_id']===1));
    $team2=array_values(array_filter($fixtures,static fn(array $row):bool=>(int)$row['team_id']===2));
    $assert(count($team1)>=2&&count($team2)>=3,'student/team fixtures');
    $issuer=new StudentAttendanceQrRepository($db);
    $scanner=new SboAttendanceRepository($db,new SboAuthorization($db));
    $card=static function(int $student) use ($issuer):array {
        foreach ($issuer->issue($student)['sessions'] as $item)
            if ($item['event_name']==='IT Days 2026'&&$item['session']==='whole_day') return $item;
        throw new RuntimeException('Test session not issued.');
    };
    $inside=['latitude'=>8.4699237,'longitude'=>124.6342058,'accuracy_m'=>15,'timestamp_ms'=>time()*1000];
    $outside=['latitude'=>8.4799237,'longitude'=>124.6342058,'accuracy_m'=>15,'timestamp_ms'=>time()*1000];
    $locationMethod=new ReflectionMethod(SboAttendanceRepository::class,'location');
    $multiVenueContext=$scanner->dashboard(15,$assignment)['selected_assignment'];
    $multiVenueContext['venues']=[
        ['id'=>1,'name'=>'Primary Venue','latitude'=>8.4699237,'longitude'=>124.6342058,'radius'=>100.0,'is_primary'=>true],
        ['id'=>2,'name'=>'Second Venue','latitude'=>8.4800000,'longitude'=>124.6400000,'radius'=>80.0,'is_primary'=>false],
    ];
    $insideSecond=['latitude'=>8.4800000,'longitude'=>124.6400000,'accuracy_m'=>10,'timestamp_ms'=>time()*1000];
    $secondMatch=$locationMethod->invoke($scanner,$multiVenueContext,$insideSecond);
    $assert($secondMatch['status']==='inside'&&$secondMatch['venue_id']===2&&$secondMatch['venue_match']==='matched',
        'Strict GPS accepts and identifies a secondary event venue');
    $multiVenueContext['location_policy']='warning';
    $nearSecond=['latitude'=>8.4820000,'longitude'=>124.6400000,'accuracy_m'=>10,'timestamp_ms'=>time()*1000];
    $nearestMatch=$locationMethod->invoke($scanner,$multiVenueContext,$nearSecond);
    $assert($nearestMatch['status']==='outside'&&$nearestMatch['venue_id']===2&&$nearestMatch['venue_match']==='nearest',
        'Warning GPS records the nearest venue when outside every event venue');
    $scan=static fn(string $token,string $phase,array $location):array =>
        ['assignment_id'=>$assignment,'mode'=>'qr','token'=>$token,'checkpoint'=>$phase,'location'=>$location];
    $in=$card((int)$team1[0]['id']);
    $assert($in['state']==='in_open'&&$in['phase']==='in'&&is_string($in['token']),'only Time In QR appears in its window');
    $team2In=$card((int)$team2[0]['id']);
    $reject(fn()=>$scanner->scan(15,$scan($team2In['token'],'in',$inside)),InvalidArgumentException::class,'wrong-team QR rejected');
    $q=$db->prepare('SELECT used_at FROM tbl_attendance_qr_tokens WHERE token=?');$q->execute([$team2In['token']]);
    $assert($q->fetchColumn()===null,'wrong-team rejection does not consume QR');
    $q=$db->prepare('SELECT COUNT(*) FROM tbl_attendances WHERE event_id=1 AND user_id=?');$q->execute([$team2[0]['id']]);
    $assert((int)$q->fetchColumn()===0,'wrong-team rejection does not create attendance');
    $db->prepare('UPDATE tbl_team_user SET team_id=1 WHERE user_id=? AND team_id=2')->execute([$team2[0]['id']]);
    $moved=$scanner->scan(15,$scan($team2In['token'],'in',$inside));
    $assert($moved['student']['team_id']===1,'same QR works after student moves to allowed team');
    try {$scanner->scan(15,$scan($team2In['token'],'in',$inside));$assert(false,'duplicate QR rejected');}
    catch(LogicException $error){$assert($error->getMessage()==='This QR was already scanned.','duplicate attendance has clear QR message');}
    $otherTeamQr=$card((int)$team2[1]['id']);
    $reject(fn()=>$scanner->scan(15,$scan($otherTeamQr['token'],'in',$inside)),InvalidArgumentException::class,'second wrong-team QR rejected in Specific mode');
    (new OfficerManagementRepository($db))->configureScanner(
        ['assignment_id'=>1,'team_id'=>1,'scanner_mode'=>'general'],['id'=>14,'role'=>'SBO Adviser']);
    $generalOther=$scanner->scan(15,$scan($otherTeamQr['token'],'in',$inside));
    $assert($generalOther['student']['team_id']===2,'same rejected QR works after changing officer to General; actual team returned');
    $team1General=$card((int)$team1[1]['id']);
    $generalOwn=$scanner->scan(15,$scan($team1General['token'],'in',$inside));
    $assert($generalOwn['student']['team_id']===1,'General scans own team too');
    try {$scanner->scan(15,$scan($otherTeamQr['token'],'in',$inside));$assert(false,'General duplicate rejected');}
    catch(LogicException $error){$assert($error->getMessage()==='This QR was already scanned.','General duplicate has clear QR message');}
    $reject(fn()=>$scanner->scan(15,$scan($in['token'],'out',$inside)),InvalidArgumentException::class,'Time In QR cannot record Time Out');
    $expired=$card((int)$team2[2]['id']);
    $db->prepare('UPDATE tbl_attendance_qr_tokens SET expires_at=? WHERE token=?')
        ->execute([$now->modify('-1 minute')->format('Y-m-d H:i:s'),$expired['token']]);
    $reject(fn()=>$scanner->scan(15,$scan($expired['token'],'in',$inside)),InvalidArgumentException::class,'expired QR rejected');
    $result=$scanner->scan(15,$scan($in['token'],'in',$inside));
    $assert($result['checkpoint']==='in'&&$result['location']['status']==='inside','Time In scan saves its location');
    $reject(fn()=>$scanner->scan(15,$scan($in['token'],'in',$inside)),LogicException::class,'used/screenshot-replayed Time In QR rejected');

    $gapClose=$now->modify('-2 minutes')->format('H:i:s');
    $gapOpen=$now->modify('+5 minutes')->format('H:i:s');
    $schedule=['id'=>1,'schedule_date'=>$today,'attendance_session_mode_id'=>2,
        'whole_day_in_time'=>$inOpen,'whole_day_in_close_time'=>$gapClose,
        'whole_day_out_open_time'=>$gapOpen,'whole_day_out_time'=>$outClose,
        'morning_in_time'=>null,'morning_in_close_time'=>null,'morning_out_open_time'=>null,'morning_out_time'=>null,
        'afternoon_in_time'=>null,'afternoon_in_close_time'=>null,'afternoon_out_open_time'=>null,'afternoon_out_time'=>null];
    $edit=new ReflectionMethod(EventManagementRepository::class,'replaceSchedules');
    $edit->invoke(new EventManagementRepository($db),1,[$schedule]);
    $gap=$card((int)$team1[0]['id']);
    $assert($gap['state']==='waiting_out'&&$gap['token']===null,'gap displays waiting status and no QR');
    $assert(!$scanner->dashboard(15,$assignment)['selected_assignment']['is_session_active'],'officer scanner closed in gap');

    $schedule['whole_day_out_open_time']=$now->modify('-1 minute')->format('H:i:s');
    $schedule['whole_day_out_time']=$now->modify('+10 minutes')->format('H:i:s');
    $edit->invoke(new EventManagementRepository($db),1,[$schedule]);
    $out=$card((int)$team1[0]['id']);
    $assert($out['phase']==='out'&&$out['token']!==$in['token'],'adviser extension opens a distinct Time Out QR');
    $noIn=$card((int)$team2[2]['id']);
    $assert($noIn['token']===null&&$noIn['state']==='time_in_required','no Time Out QR without Time In');
    $forged=rtrim(strtr(base64_encode(random_bytes(24)),'+/','-_'),'=');
    $db->prepare('INSERT INTO tbl_attendance_qr_tokens(event_id,user_id,session,phase,schedule_date,token,issued_at,expires_at)
        VALUES(1,?,\'whole_day\',\'out\',?,?,?,?)')
        ->execute([$team2[2]['id'],$today,$forged,$now->format('Y-m-d H:i:s'),$now->modify('+5 minutes')->format('Y-m-d H:i:s')]);
    $reject(fn()=>$scanner->scan(15,$scan($forged,'out',$inside)),LogicException::class,'Time Out without Time In rejected server-side');
    $outResult=$scanner->scan(15,$scan($out['token'],'out',$outside));
    $assert($outResult['checkpoint']==='out'&&$outResult['location']['status']==='outside','Time Out scan saves separate location');
    $reject(fn()=>$scanner->scan(15,$scan($out['token'],'out',$inside)),LogicException::class,'duplicate/replayed Time Out QR rejected');
    $saved=$db->prepare('SELECT ae.phase,ae.recorded_by,ae.scan_latitude,ae.location_status,ae.venue_location_id,ae.venue_name_snapshot,ae.venue_latitude_snapshot FROM tbl_attendance_entries ae
        JOIN tbl_attendances atd ON atd.id=ae.attendance_id WHERE atd.event_id=1 AND atd.user_id=? ORDER BY ae.phase');
    $saved->execute([$team1[0]['id']]);$entries=$saved->fetchAll();
    $assert(count($entries)===2&&$entries[0]['phase']==='in'&&$entries[1]['phase']==='out'&&
        $entries[0]['scan_latitude']!==$entries[1]['scan_latitude']&&
        (int)$entries[0]['recorded_by']===15&&(int)$entries[1]['recorded_by']===15&&
        (int)$entries[0]['venue_location_id']===1&&$entries[0]['venue_name_snapshot']==='PHINMA COC Carmen Campus'&&$entries[0]['venue_latitude_snapshot']!==null,
        'unique phase rows retain separate officer, scan location, and detected venue snapshots');
    $db->prepare('UPDATE tbl_locations SET latitude=?,longitude=?,radius=? WHERE id=2')->execute([8.4705000,124.6350000,40]);
    $form=[
        'title'=>'Long-window phase QA','description'=>'Separated Time In and Time Out',
        'general_location_id'=>1,'specific_location_id'=>0,'location_ids'=>[1,2],'attendance_location_policy'=>'warning',
        'audience_type'=>'all_students','event_type_id'=>1,'acknowledge_conflicts'=>true,
        'attendance_days'=>[ ['date'=>$today,'attendance_session_mode_id'=>2,
            'morning_in'=>'08:00','morning_in_close'=>'10:00','morning_out_open'=>'19:00','morning_out'=>'21:00',
            'afternoon_in'=>'','afternoon_in_close'=>'','afternoon_out_open'=>'','afternoon_out'=>''] ],
    ];
    $created=(new EventManagementRepository($db))->save($form,[],14);
    $createdLocations=$db->prepare('SELECT location_id,is_primary FROM tbl_event_locations WHERE event_id=? ORDER BY location_id');
    $createdLocations->execute([$created]);
    $savedLocations=array_map(static fn(array $row):array=>array_map('intval',$row),$createdLocations->fetchAll());
    $assert($savedLocations===[['location_id'=>1,'is_primary'=>1],['location_id'=>2,'is_primary'=>0]],
        'event form saves one primary venue and additional event locations');
    $createdRow=$db->prepare('SELECT s.* FROM tbl_event_attendance_schedules s WHERE s.event_id=?');
    $createdRow->execute([$created]);$longDay=$createdRow->fetch();
    $longWindows=AttendanceScanWindows::forSession($longDay,'whole_day');
    $noon=new DateTimeImmutable($today.' 12:00:00',new DateTimeZone('Asia/Manila'));
    $assert($longDay['whole_day_in_close_time']==='10:00:00'&&$longDay['whole_day_out_open_time']==='19:00:00'&&
        !AttendanceScanWindows::isOpen($longWindows['in'],$noon)&&!AttendanceScanWindows::isOpen($longWindows['out'],$noon),
        'adviser form saves 8–10 AM and 7–9 PM windows with no noon QR');
    $split=$longDay;
    foreach (['morning','afternoon'] as $part) {
        $split[$part.'_in_time']=$part==='morning'?'08:00:00':'13:00:00';
        $split[$part.'_in_close_time']=$part==='morning'?'09:00:00':'14:00:00';
        $split[$part.'_out_open_time']=$part==='morning'?'10:00:00':'19:00:00';
        $split[$part.'_out_time']=$part==='morning'?'11:00:00':'21:00:00';
    }
    $morning=AttendanceScanWindows::forSession($split,'morning');
    $afternoon=AttendanceScanWindows::forSession($split,'afternoon');
    $assert(!AttendanceScanWindows::isOpen($morning['in'],$noon)&&!AttendanceScanWindows::isOpen($morning['out'],$noon)&&
        !AttendanceScanWindows::isOpen($afternoon['in'],$noon)&&!AttendanceScanWindows::isOpen($afternoon['out'],$noon),
        'independent morning and afternoon sessions also have closed gaps');
    $edited=$form;
    $edited['title']='IT Days 2026';
    $edited['attendance_location_policy']='warning';
    $edited['event_status_id']=(int)$db->query('SELECT event_status_id FROM tbl_events WHERE id=1')->fetchColumn();
    $edited['attendance_days'][0]=[
        'id'=>1,'date'=>$today,'attendance_session_mode_id'=>2,
        'morning_in'=>substr($inOpen,0,5),'morning_in_close'=>substr($gapClose,0,5),
        'morning_out_open'=>substr($schedule['whole_day_out_open_time'],0,5),
        'morning_out'=>$now->modify('+15 minutes')->format('H:i'),
        'afternoon_in'=>'','afternoon_in_close'=>'','afternoon_out_open'=>'','afternoon_out'=>'',
    ];
    (new EventManagementRepository($db))->save($edited,[],14,1);
    $saved->execute([$team1[0]['id']]);
    $assert(count($saved->fetchAll())===2,'adviser save extends window without rewriting either recorded scan');
} finally {
    $check=$live->prepare('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=?');
    $check->execute([$scratch]);
    if ($check->fetchColumn()) $live->prepare("DROP DATABASE `$scratch`")->execute();
}
