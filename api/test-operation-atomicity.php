<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/users.php';
require_once __DIR__.'/teams.php';
require_once __DIR__.'/scores.php';
require_once __DIR__.'/adviser-events.php';
require_once __DIR__.'/sbo-assignments.php';
require_once __DIR__.'/SboAuthorization.php';
require_once __DIR__.'/sbo-scores.php';
require_once __DIR__.'/student-attendance-qr.php';
require_once __DIR__.'/sbo-attendance.php';
require_once __DIR__.'/announcements.php';
require_once __DIR__.'/attendance.php';
require_once __DIR__.'/MediaRepository.php';
require_once __DIR__.'/student-roster-imports.php';
require_once __DIR__.'/officers.php';
require_once __DIR__.'/auth.php';

$live=(new Database())->connection();
$originalDbName=getenv('DB_NAME');
$source=(string)$live->query('SELECT DATABASE()')->fetchColumn();
if(!preg_match('/^[A-Za-z0-9_]+$/',$source))throw new RuntimeException('Unsafe source database name.');
$scratch='atomicity_test_'.bin2hex(random_bytes(4));
if(!preg_match('/^atomicity_test_[0-9a-f]{8}$/',$scratch))throw new RuntimeException('Unsafe test database name.');
$assert=static function(bool $condition,string $label):void{if(!$condition)throw new RuntimeException('FAIL: '.$label);echo'PASS: ',$label,PHP_EOL;};
$fails=static function(callable $operation,string $label)use($assert):void{try{$operation();$assert(false,$label);}catch(Throwable){$assert(true,$label);}};
$periodLabel=AcademicPeriodLabel::parse('SY 26-27 SEM I');
$assert($periodLabel['school_year_label']==='2026-2027'&&$periodLabel['term_code']==='first_semester','academic period parser separates a short school year and semester');
$assert($periodLabel['label']==='SY 2026-2027 · First Semester','academic period parser produces the canonical reporting label');
$fails(fn()=>AcademicPeriodLabel::parse('SY 2026-2027'),'academic period parser rejects a missing semester');

$live->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try{
    $tables=$live->query("SHOW FULL TABLES FROM `$source` WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    foreach($tables as $table){
        if(!preg_match('/^[A-Za-z0-9_]+$/',(string)$table))throw new RuntimeException('Unsafe table name.');
        $live->exec("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`");
        $live->exec("INSERT INTO `$scratch`.`$table` SELECT * FROM `$source`.`$table`");
    }
    $live->exec("CREATE VIEW `$scratch`.`vw_attendance_effective` AS SELECT attendance.*,COALESCE(attendance.manual_status,attendance.status) effective_status FROM `$scratch`.`tbl_attendances` attendance");
    $live->exec("CREATE VIEW `$scratch`.`vw_finalized_scores` AS SELECT score.* FROM `$scratch`.`tbl_scores` score JOIN `$scratch`.`tbl_score_categories` category ON category.id=score.score_category_id JOIN `$scratch`.`tbl_score_sheets` sheet ON sheet.event_id=score.event_id AND sheet.activity_id=category.activity_id AND sheet.team_id=score.team_id AND sheet.status='finalized'");
    putenv('DB_NAME='.$scratch);
    $db=(new Database())->connection();
    $actor=(int)$db->query("SELECT u.id FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id JOIN tbl_user_statuses s ON s.id=u.status WHERE r.name='SBO Adviser' AND s.label='active' ORDER BY u.id LIMIT 1")->fetchColumn();
    $user=(int)$db->query("SELECT u.id FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' JOIN tbl_user_statuses s ON s.id=u.status AND s.label='active' JOIN tbl_team_user tu ON tu.user_id=u.id JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1 ORDER BY u.id LIMIT 1")->fetchColumn();
    $teamStatement=$db->prepare('SELECT t.id FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1 WHERE tu.user_id=? ORDER BY tu.id DESC LIMIT 1');$teamStatement->execute([$user]);$team=(int)$teamStatement->fetchColumn();
    if(!$actor||!$user||!$team)throw new RuntimeException('Live snapshot lacks the user/team fixtures required by the atomicity test.');
    $teamYear=(int)$db->query("SELECT school_year_id FROM tbl_teams WHERE id=$team")->fetchColumn();
    $actorStatement=$db->prepare('SELECT u.id,u.first_name,u.last_name,r.name role FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id WHERE u.id=?');$actorStatement->execute([$actor]);$actorIdentity=$actorStatement->fetch();
    $studentStatement=$db->prepare('SELECT u.id,u.first_name,u.last_name,r.name role FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id WHERE u.id=?');$studentStatement->execute([$user]);$studentIdentity=$studentStatement->fetch();
    if(!$teamYear||!$actorIdentity||!$studentIdentity)throw new RuntimeException('Atomicity identity fixtures are unavailable.');
    $upcoming=(int)$db->query("SELECT id FROM tbl_event_statuses WHERE label='upcoming'")->fetchColumn();
    $eventType=(int)$db->query('SELECT id FROM tbl_event_types ORDER BY id LIMIT 1')->fetchColumn();
    $period=(int)$db->query("SELECT id FROM tbl_academic_periods WHERE school_year_id=$teamYear ORDER BY is_active DESC,id DESC LIMIT 1")->fetchColumn();
    if(!$period)throw new RuntimeException('An academic period is required for the selected team year.');
    $insert=$db->prepare("INSERT INTO tbl_events(title,description,location,audience_type,academic_period_id,start_at,end_at,event_type_id,event_status_id,created_by,created_at,updated_at) VALUES('Atomicity Test Event','Isolated test fixture','Test venue','all_students',?,CONCAT(CURDATE(),' 00:00:00'),CONCAT(CURDATE(),' 23:59:59'),?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    $insert->execute([$period,$eventType?:null,$upcoming?:null,$actor]);$event=(int)$db->lastInsertId();
    (new AcademicPeriodScope($db))->refreshMembershipSnapshot($event);
    $snapshotTeam=$db->prepare('SELECT team_id FROM tbl_event_membership_snapshots WHERE event_id=? AND user_id=?');$snapshotTeam->execute([$event,$user]);
    $assert((int)$snapshotTeam->fetchColumn()===$team,'event snapshot captures the student team for the selected academic period');
    $otherPeriodTeam=(int)$db->query("SELECT id FROM tbl_teams WHERE school_year_id=$teamYear AND id<>$team AND is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
    if($otherPeriodTeam){
        $db->prepare('DELETE tu FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id WHERE tu.user_id=? AND t.school_year_id=?')->execute([$user,$teamYear]);
        $db->prepare('INSERT INTO tbl_team_user(team_id,user_id,created_at,updated_at) VALUES(?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([$otherPeriodTeam,$user]);
        $snapshotTeam->execute([$event,$user]);$assert((int)$snapshotTeam->fetchColumn()===$team,'historical event membership does not change when the live tribe changes');
        $db->prepare('DELETE tu FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id WHERE tu.user_id=? AND t.school_year_id=?')->execute([$user,$teamYear]);
        $db->prepare('INSERT INTO tbl_team_user(team_id,user_id,created_at,updated_at) VALUES(?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([$team,$user]);
    }
    $db->prepare("INSERT INTO tbl_locations(name,type,latitude,longitude,radius,created_at,updated_at) VALUES(?,'general',8.4699237,124.6342058,100,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute(['Atomic venue '.bin2hex(random_bytes(3))]);
    $location=(int)$db->lastInsertId();
    $db->prepare("UPDATE tbl_events SET location_id=?,location='Atomic venue',attendance_location_policy='warning' WHERE id=?")->execute([$location,$event]);

    $eventStatus=(int)$db->query("SELECT event_status_id FROM tbl_events WHERE id=$event")->fetchColumn();

    $db->prepare("INSERT INTO tbl_event_activities(event_id,name,status,created_by,created_at,updated_at) VALUES(?,'Atomic activity','active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$event,$actor]);
    $activity=(int)$db->lastInsertId();
    $categoryName='Atomic seed '.bin2hex(random_bytes(3));
    $db->prepare('INSERT INTO tbl_score_categories(event_id,activity_id,name,min_points,max_points,sort_order,created_at,updated_at) VALUES(?,?,?,0,100,999,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([$event,$activity,$categoryName]);
    $category=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO tbl_posts(user_id,category,is_official,content,status,created_at,updated_at) VALUES(?,'announcement',1,'Atomicity test announcement','draft',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$actor]);
    $announcement=(int)$db->lastInsertId();
    $mode=(int)$db->query("SELECT id FROM tbl_attendance_session_modes WHERE code='whole_day' ORDER BY id LIMIT 1")->fetchColumn();
    $clock=new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));
    $dayEnd=new DateTimeImmutable($clock->format('Y-m-d').' 23:59:59',new DateTimeZone('Asia/Manila'));
    if($dayEnd->getTimestamp()-$clock->getTimestamp()<90)throw new RuntimeException('Run the QR portion of the atomicity test at least 90 seconds before Manila midnight.');
    $inClose=$clock->modify('+30 seconds');$outOpen=$inClose;$outClose=$dayEnd;
    $db->prepare('INSERT INTO tbl_event_attendance_schedules(event_id,schedule_date,attendance_session_mode_id,whole_day_in_time,whole_day_in_close_time,whole_day_out_open_time,whole_day_out_time,created_at,updated_at) VALUES(?,CURDATE(),?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([$event,$mode,$clock->modify('-5 minutes')->format('H:i:s'),$inClose->format('H:i:s'),$outOpen->format('H:i:s'),$outClose->format('H:i:s')]);
    $schedule=(int)$db->lastInsertId();
    $studentId=(string)$db->query("SELECT COALESCE(id_number,CONCAT('TEST-',id)) FROM tbl_users WHERE id=$user")->fetchColumn();
    $officerRole=(int)$db->query("SELECT id FROM tbl_roles WHERE name='SBO Officer'")->fetchColumn();
    $activeAccount=(int)$db->query("SELECT id FROM tbl_user_statuses WHERE label='active'")->fetchColumn();
    $unique=bin2hex(random_bytes(4));
    $studentRole=(int)$db->query("SELECT id FROM tbl_roles WHERE name='Student'")->fetchColumn();
    $db->prepare("INSERT INTO tbl_users(role_id,id_number,first_name,last_name,username,password,status,email,must_change_password,created_at,updated_at) VALUES(?,?,'Atomic','Unassigned',?,?,?, ?,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$studentRole,'ATOMIC-STUDENT-'.$unique,'atomic.student.'.$unique,password_hash('AtomicTest1!',PASSWORD_BCRYPT),$activeAccount,'atomic.student.'.$unique.'@example.test']);
    $unassignedStudent=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO tbl_users(role_id,id_number,first_name,last_name,username,password,status,email,must_change_password,created_at,updated_at) VALUES(?,?,'Atomic','Officer',?,?,?, ?,0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$officerRole,'ATOMIC-'.$unique,'atomic.'.$unique,password_hash('AtomicTest1!',PASSWORD_BCRYPT),$activeAccount,'atomic.'.$unique.'@example.test']);
    $officerUser=(int)$db->lastInsertId();
    $otherTeam=(int)$db->query("SELECT id FROM tbl_teams WHERE is_active=1 AND id<>$team ORDER BY id LIMIT 1")->fetchColumn();
    if(!$otherTeam)throw new RuntimeException('The QR atomicity fixture requires two active tribes.');
    $db->prepare("INSERT INTO tbl_sbo_officer_assignments(student_id,officer_user_id,team_id,scanner_mode,scanner_team_id,position,term,assigned_by,status,created_at,updated_at) VALUES(?,?,?,'specific',?,'Atomic officer','Atomic term',?,'Active',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$studentId,$officerUser,$otherTeam,$otherTeam,$actor]);
    $officerAssignment=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO tbl_sbo_event_assignments(officer_assignment_id,event_schedule_id,session_code,activity_id,team_id,responsibility_id,status,assigned_by,created_at,updated_at) VALUES(?,?,'whole_day',?,?,(SELECT id FROM tbl_officer_responsibilities WHERE code='attendance'),'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$officerAssignment,$schedule,$activity,$otherTeam,$actor]);
    $task=(int)$db->lastInsertId();

    $db->exec("CREATE TRIGGER fail_activity_log BEFORE INSERT ON tbl_activity_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced audit failure'");
    $users=new UserManagementRepository($db);$teamsRepo=new TeamManagementRepository($db);$scores=new ScoreManagementRepository($db);$events=new EventManagementRepository($db);$assignments=new SboAssignmentRepository($db);$announcements=new AnnouncementRepository($db);$attendance=new AttendanceManagementRepository($db);$media=new MediaRepository($db);$officers=new OfficerManagementRepository($db);$authUsers=new UserRepository($db);

    $userStatus=(int)$db->query("SELECT status FROM tbl_users WHERE id=$user")->fetchColumn();
    $activeStatus=(int)$db->query("SELECT id FROM tbl_user_statuses WHERE label='active'")->fetchColumn();
    $fails(fn()=>$users->toggle($user,$actor,$userStatus!==$activeStatus),'user status reports the forced audit failure');
    $assert((int)$db->query("SELECT status FROM tbl_users WHERE id=$user")->fetchColumn()===$userStatus,'failed user status change rolls back');

    $teamActive=(bool)$db->query("SELECT is_active FROM tbl_teams WHERE id=$team")->fetchColumn();
    $fails(fn()=>$teamsRepo->toggle($team,$actor,!$teamActive),'tribe status reports the forced audit failure');
    $assert((bool)$db->query("SELECT is_active FROM tbl_teams WHERE id=$team")->fetchColumn()===$teamActive,'failed tribe status change rolls back');

    $failedTeamName='Atomic failed tribe '.bin2hex(random_bytes(3));
    $fails(fn()=>$teamsRepo->save(['name'=>$failedTeamName,'school_year_id'=>$teamYear,'color'=>'#397565','member_ids'=>[$unassignedStudent]],$actor),'tribe creation reports the forced audit failure');
    $failedTeamCheck=$db->prepare('SELECT COUNT(*) FROM tbl_teams WHERE name=? AND school_year_id=?');$failedTeamCheck->execute([$failedTeamName,$teamYear]);
    $assert((int)$failedTeamCheck->fetchColumn()===0,'failed tribe creation leaves no tribe or membership');

    $activityCount=(int)$db->query("SELECT COUNT(*) FROM tbl_event_activities WHERE event_id=$event")->fetchColumn();
    $fails(fn()=>$events->saveActivity($event,['name'=>'Atomic failed activity '.bin2hex(random_bytes(3)),'description'=>'Rollback test'],$actor),'event activity creation reports the forced audit failure');
    $assert((int)$db->query("SELECT COUNT(*) FROM tbl_event_activities WHERE event_id=$event")->fetchColumn()===$activityCount,'failed event activity creation leaves no activity');
    $activityStatus=(string)$db->query("SELECT status FROM tbl_event_activities WHERE id=$activity")->fetchColumn();
    $fails(fn()=>$events->setActivityStatus($event,$activity,$activityStatus==='active'?'inactive':'active',$actor),'event activity status reports the forced audit failure');
    $assert((string)$db->query("SELECT status FROM tbl_event_activities WHERE id=$activity")->fetchColumn()===$activityStatus,'failed event activity status change rolls back');

    $newEventTitle='Atomic failed event '.bin2hex(random_bytes(3));
    $eventPayload=['title'=>$newEventTitle,'description'=>'Rollback fixture','general_location_id'=>$location,'specific_location_id'=>0,'location_ids'=>[$location],'attendance_location_policy'=>'warning','event_type_id'=>$eventType,'academic_period_id'=>$period,'audience_type'=>'all_students','assigned_user_ids'=>[],'tribe_ids'=>[],'year_level_ids'=>[],'participant_ids'=>[],'acknowledge_conflicts'=>true,'attendance_days'=>[['date'=>date('Y-m-d'),'attendance_session_mode_id'=>$mode,'morning_in'=>'08:00','morning_in_close'=>'09:00','morning_out_open'=>'16:00','morning_out'=>'17:00','afternoon_in'=>'','afternoon_in_close'=>'','afternoon_out_open'=>'','afternoon_out'=>'']]];
    $fails(fn()=>$events->save($eventPayload,[],$actor),'event creation reports the forced audit failure');
    $newEventCheck=$db->prepare('SELECT COUNT(*) FROM tbl_events WHERE title=?');$newEventCheck->execute([$newEventTitle]);
    $assert((int)$newEventCheck->fetchColumn()===0,'failed event creation leaves no event, schedule, participant, or location rows');

    $announcementContent='Atomic failed announcement '.bin2hex(random_bytes(3));
    $fails(fn()=>$announcements->create(['content'=>$announcementContent,'intent'=>'publish','event_id'=>$event],[],$actor),'announcement creation reports the forced audit failure');
    $announcementCheck=$db->prepare('SELECT COUNT(*) FROM tbl_posts WHERE content=?');$announcementCheck->execute([$announcementContent]);
    $assert((int)$announcementCheck->fetchColumn()===0,'failed announcement creation leaves no post or audit');

    $beforeCategories=(int)$db->query("SELECT COUNT(*) FROM tbl_score_categories WHERE event_id=$event")->fetchColumn();
    $fails(fn()=>$scores->createCategory($event,['activity_id'=>$activity,'name'=>'Atomic create '.bin2hex(random_bytes(3)),'max_points'=>'50'],$actor),'category creation reports the forced audit failure');
    $assert((int)$db->query("SELECT COUNT(*) FROM tbl_score_categories WHERE event_id=$event")->fetchColumn()===$beforeCategories,'failed category creation leaves no category');
    $fails(fn()=>$scores->updateCategory($event,$category,['activity_id'=>$activity,'name'=>'Atomic renamed','max_points'=>'100'],$actor),'category update reports the forced audit failure');
    $check=$db->prepare('SELECT name FROM tbl_score_categories WHERE id=?');$check->execute([$category]);$assert($check->fetchColumn()===$categoryName,'failed category update rolls back');
    $fails(fn()=>$scores->deleteCategory($event,$category,['activity_id'=>$activity],$actor),'category deletion reports the forced audit failure');
    $assert((int)$db->query("SELECT COUNT(*) FROM tbl_score_categories WHERE id=$category")->fetchColumn()===1,'failed category deletion restores category and dependent scores');
    $scoreBefore=$db->prepare('SELECT points FROM tbl_scores WHERE event_id=? AND team_id=? AND score_category_id=?');$scoreBefore->execute([$event,$team,$category]);$scoreBeforeValue=$scoreBefore->fetchColumn();
    $fails(fn()=>$scores->saveScores($event,['activity_id'=>$activity,'scores'=>[$category=>[$team=>'25']]],$actor),'score saving reports the forced audit failure');
    $scoreBefore->execute([$event,$team,$category]);$scoreAfterValue=$scoreBefore->fetchColumn();
    $assert($scoreAfterValue===$scoreBeforeValue,'failed score save leaves prior score state unchanged');

    $completedStatus=(int)$db->query("SELECT id FROM tbl_event_statuses WHERE label='completed'")->fetchColumn();
    $fails(fn()=>$events->setStatus($event,$completedStatus,$actor),'manual event lifecycle status change is rejected');
    $assert((int)$db->query("SELECT event_status_id FROM tbl_events WHERE id=$event")->fetchColumn()===$eventStatus,'rejected lifecycle change leaves the derived status unchanged');

    $fails(fn()=>$announcements->updateStatus($announcement,'approved',$actor),'announcement publishing reports the forced audit failure');
    $assert($db->query("SELECT status FROM tbl_posts WHERE id=$announcement")->fetchColumn()==='draft','failed announcement publishing rolls back');

    $attendanceBefore=$db->prepare('SELECT COUNT(*) FROM tbl_attendances WHERE event_id=? AND user_id=? AND attendance_date=CURDATE()');$attendanceBefore->execute([$event,$unassignedStudent]);$attendanceBeforeCount=(int)$attendanceBefore->fetchColumn();
    $fails(fn()=>$attendance->update($event,['attendance_date'=>date('Y-m-d'),'records'=>[$unassignedStudent=>['status'=>'present']]],$actor),'bulk attendance saving reports the forced audit failure');
    $attendanceBefore->execute([$event,$unassignedStudent]);$assert((int)$attendanceBefore->fetchColumn()===$attendanceBeforeCount,'failed bulk attendance save leaves no partial row');

    $fails(fn()=>$assignments->end($task,$actor),'responsibility ending reports the forced audit failure');
    $assert($db->query("SELECT status FROM tbl_sbo_event_assignments WHERE id=$task")->fetchColumn()==='active','failed responsibility ending rolls back');

    $facultyRole=(int)$db->query("SELECT id FROM tbl_roles WHERE name='Faculty'")->fetchColumn();
    $createKey=bin2hex(random_bytes(4));
    $createPayload=['first_name'=>'Atomic','last_name'=>'Create','username'=>'atomic.create.'.$createKey,'email'=>'atomic.create.'.$createKey.'@example.test','role_id'=>$facultyRole,'password'=>'AtomicCreate1!','password_confirmation'=>'AtomicCreate1!'];
    $fails(fn()=>$users->save($createPayload,$actor),'user creation reports the forced audit failure');
    $createdCheck=$db->prepare('SELECT COUNT(*) FROM tbl_users WHERE username=?');$createdCheck->execute([$createPayload['username']]);
    $assert((int)$createdCheck->fetchColumn()===0,'failed user creation leaves no account');

    $officerHash=(string)$db->query("SELECT password FROM tbl_users WHERE id=$officerUser")->fetchColumn();
    $fails(fn()=>$officers->changePassword(['assignment_id'=>$officerAssignment,'password'=>'AtomicReset1!','password_confirmation'=>'AtomicReset1!'],['id'=>$actor,'role'=>'SBO Adviser']),'officer password reset reports the forced audit failure');
    $assert((string)$db->query("SELECT password FROM tbl_users WHERE id=$officerUser")->fetchColumn()===$officerHash,'failed officer password reset restores the prior credential');

    $officerAccessPayload=['student_user_id'=>$unassignedStudent,'team_id'=>$team,'position'=>'Atomic generated officer','term'=>'Atomic generated term','scanner_mode'=>'specific'];
    $unassignedStudentId=(string)$db->query("SELECT id_number FROM tbl_users WHERE id=$unassignedStudent")->fetchColumn();
    $unassignedStudentHash=(string)$db->query("SELECT password FROM tbl_users WHERE id=$unassignedStudent")->fetchColumn();
    $fails(fn()=>$officers->assign($officerAccessPayload,$actorIdentity),'officer access creation reports the forced audit failure');
    $failedOfficerCheck=$db->prepare('SELECT COUNT(*) FROM tbl_users WHERE id_number=? AND role_id=?');$failedOfficerCheck->execute([$unassignedStudentId,$officerRole]);
    $assert((int)$failedOfficerCheck->fetchColumn()===0,'failed officer access creation leaves no separate officer account');
    $failedOfficerAssignmentCheck=$db->prepare("SELECT COUNT(*) FROM tbl_sbo_officer_assignments WHERE student_id=?");$failedOfficerAssignmentCheck->execute([$unassignedStudentId]);
    $assert((int)$failedOfficerAssignmentCheck->fetchColumn()===0,'failed officer access creation leaves no officer assignment');
    $assert((string)$db->query("SELECT password FROM tbl_users WHERE id=$unassignedStudent")->fetchColumn()===$unassignedStudentHash,'failed officer access creation never changes the student password');

    $studentHash=(string)$db->query("SELECT password FROM tbl_users WHERE id=$user")->fetchColumn();
    $fails(fn()=>$authUsers->replacePassword($user,'AtomicFirstLogin1!','Student'),'first-login password change reports the forced audit failure');
    $assert((string)$db->query("SELECT password FROM tbl_users WHERE id=$user")->fetchColumn()===$studentHash,'failed first-login password change restores the prior credential');

    $issuer=new StudentAttendanceQrRepository($db);$scanner=new SboAttendanceRepository($db,new SboAuthorization($db));
    $qrCard=null;foreach($issuer->issue($user)['sessions'] as $card)if($card['event_name']==='Atomicity Test Event'&&$card['phase']==='in'){$qrCard=$card;break;}
    $assert(is_array($qrCard)&&is_string($qrCard['token']??null),'student receives an isolated attendance QR fixture');
    $scanPayload=['assignment_id'=>$task,'mode'=>'qr','token'=>$qrCard['token'],'checkpoint'=>'in','location'=>['latitude'=>8.4699237,'longitude'=>124.6342058,'accuracy_m'=>10,'timestamp_ms'=>time()*1000]];
    $fails(fn()=>$scanner->scan($officerUser,$scanPayload),'wrong-team scanner rejects the QR');
    $tokenCheck=$db->prepare('SELECT used_at FROM tbl_attendance_qr_tokens WHERE token=?');$tokenCheck->execute([$qrCard['token']]);
    $assert($tokenCheck->fetchColumn()===null,'wrong-team rejection does not consume the QR');
    $db->prepare('UPDATE tbl_sbo_officer_assignments SET scanner_team_id=? WHERE id=?')->execute([$team,$officerAssignment]);
    $db->prepare('UPDATE tbl_sbo_event_assignments SET team_id=? WHERE id=?')->execute([$team,$task]);
    $fails(fn()=>$scanner->scan($officerUser,$scanPayload),'late activity-log failure aborts attendance scan');
    $tokenCheck->execute([$qrCard['token']]);$assert($tokenCheck->fetchColumn()===null,'late attendance failure rolls back QR consumption');
    $attendanceCheck=$db->prepare('SELECT COUNT(*) FROM tbl_attendances WHERE event_id=? AND user_id=?');$attendanceCheck->execute([$event,$user]);
    $assert((int)$attendanceCheck->fetchColumn()===0,'late attendance failure leaves no partial attendance row');

    $eligible=(int)$db->query("SELECT u.id FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id JOIN tbl_user_statuses s ON s.id=u.status WHERE r.name IN('SBO Adviser','Faculty') AND s.label='active' ORDER BY u.id LIMIT 1")->fetchColumn();
    if($eligible){$db->prepare('DELETE FROM tbl_event_user WHERE event_id=? AND user_id=?')->execute([$event,$eligible]);$fails(fn()=>$events->assign($event,$eligible,$actor),'event assignment reports the forced audit failure');$q=$db->prepare('SELECT COUNT(*) FROM tbl_event_user WHERE event_id=? AND user_id=?');$q->execute([$event,$eligible]);$assert((int)$q->fetchColumn()===0,'failed event assignment leaves no assignment');}

    $db->exec('DROP TRIGGER fail_activity_log');

    $events->archive($event,$actor);
    $archivedEvent=$db->query("SELECT e.deleted_at,s.label status FROM tbl_events e JOIN tbl_event_statuses s ON s.id=e.event_status_id WHERE e.id=$event")->fetch();
    $assert($archivedEvent['deleted_at']!==null&&$archivedEvent['status']==='archived','Archive remains the explicit event lifecycle action');
    $events->restore($event,$actor);
    $restoredEvent=$db->query("SELECT e.deleted_at,s.label status FROM tbl_events e JOIN tbl_event_statuses s ON s.id=e.event_status_id WHERE e.id=$event")->fetch();
    $assert($restoredEvent['deleted_at']===null&&$restoredEvent['status']==='ongoing','restoring an event recalculates lifecycle status from its schedule');

    $createdOfficerAccess=$officers->assign($officerAccessPayload,$actorIdentity);
    $assert(($createdOfficerAccess['assignment_id']??0)>0&&($createdOfficerAccess['officer_user_id']??0)>0,'officer access creation returns the created account and assignment');
    $assert(preg_match('/^[a-z0-9._-]+$/',(string)($createdOfficerAccess['username']??''))===1,'officer access receives a generated username');
    $assert(strlen((string)($createdOfficerAccess['temporary_password']??''))>=8,'officer access receives a generated one-time password');
    $createdOfficerStatement=$db->prepare('SELECT password,must_change_password,role_id,email FROM tbl_users WHERE id=?');$createdOfficerStatement->execute([(int)$createdOfficerAccess['officer_user_id']]);$createdOfficer=$createdOfficerStatement->fetch();
    $assert(is_array($createdOfficer)&&password_verify((string)$createdOfficerAccess['temporary_password'],(string)$createdOfficer['password']),'only the generated password hash is stored for the officer account');
    $assert((int)$createdOfficer['must_change_password']===1,'new officer access requires a password change at first sign-in');
    $assert((int)$createdOfficer['role_id']===$officerRole,'generated access is an SBO Officer account');
    $studentEmail=(string)$db->query("SELECT email FROM tbl_users WHERE id=$unassignedStudent")->fetchColumn();
    $assert((string)$createdOfficer['email']!==$studentEmail&&str_ends_with((string)$createdOfficer['email'],'@officer.itevents.local'),'Officer login receives a unique account email instead of duplicating the student email');
    $assert((string)$db->query("SELECT password FROM tbl_users WHERE id=$unassignedStudent")->fetchColumn()===$unassignedStudentHash,'creating officer access leaves the student password unchanged');
    $expectedOfficerPeriod=(string)$db->query("SELECT sy.label FROM tbl_teams t JOIN tbl_school_years sy ON sy.id=t.school_year_id WHERE t.id=$team")->fetchColumn();
    $createdAssignmentStatement=$db->prepare('SELECT team_id,scanner_mode,scanner_team_id,term FROM tbl_sbo_officer_assignments WHERE id=?');$createdAssignmentStatement->execute([(int)$createdOfficerAccess['assignment_id']]);$createdAssignment=$createdAssignmentStatement->fetch();
    $assert((string)$createdAssignment['term']===$expectedOfficerPeriod,'officer assignment period is derived from the selected tribe school year');
    $alternateScannerTeam=(int)$db->query("SELECT id FROM tbl_teams WHERE school_year_id=$teamYear AND is_active=1 AND id<>$team ORDER BY id LIMIT 1")->fetchColumn();
    if(!$alternateScannerTeam)throw new RuntimeException('Scanner-scope separation requires two active teams in the assignment school year.');
    $officers->configureScanner(['assignment_id'=>$createdOfficerAccess['assignment_id'],'scanner_mode'=>'specific','scanner_team_id'=>$alternateScannerTeam],$actorIdentity);
    $createdAssignmentStatement->execute([(int)$createdOfficerAccess['assignment_id']]);$specificScannerAssignment=$createdAssignmentStatement->fetch();
    $assert((int)$specificScannerAssignment['team_id']===(int)$createdAssignment['team_id'],'specific scanner scope does not change the officer home tribe');
    $assert((int)$specificScannerAssignment['scanner_team_id']===$alternateScannerTeam,'specific scanner scope stores the allowed team separately');
    $officers->configureScanner(['assignment_id'=>$createdOfficerAccess['assignment_id'],'scanner_mode'=>'general'],$actorIdentity);
    $createdAssignmentStatement->execute([(int)$createdOfficerAccess['assignment_id']]);$generalScannerAssignment=$createdAssignmentStatement->fetch();
    $assert($generalScannerAssignment['scanner_mode']==='general','general scanner access can be saved without selecting an allowed team');
    $assert((int)$generalScannerAssignment['team_id']===(int)$createdAssignment['team_id'],'general scanner access preserves the officer home tribe');
    $assert($generalScannerAssignment['scanner_team_id']===null,'general scanner access has no misleading specific-team permission');

    $successfulTeamName='Atomic member tribe '.bin2hex(random_bytes(3));
    $successfulTeam=$teamsRepo->save(['name'=>$successfulTeamName,'school_year_id'=>$teamYear,'color'=>'#397565','member_ids'=>[$unassignedStudent]],$actor);
    $membershipCheck=$db->prepare('SELECT COUNT(*) FROM tbl_team_user WHERE team_id=? AND user_id=?');$membershipCheck->execute([$successfulTeam,$unassignedStudent]);
    $assert((int)$membershipCheck->fetchColumn()===1,'tribe creation persists every selected member in the same transaction');

    $pendingContent='Atomic pending media '.bin2hex(random_bytes(3));
    $db->prepare("INSERT INTO tbl_posts(user_id,event_id,category,is_official,content,status,created_at,updated_at) VALUES(?,?,'general',0,?,'pending',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$user,$event,$pendingContent]);
    $pendingPost=(int)$db->lastInsertId();
    $db->exec("CREATE TRIGGER fail_post_audit BEFORE INSERT ON tbl_post_audits FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='forced post audit failure'");
    $fails(fn()=>$media->review($actorIdentity,$pendingPost,'approved',''),'post approval reports the forced audit failure');
    $assert($db->query("SELECT status FROM tbl_posts WHERE id=$pendingPost")->fetchColumn()==='pending','failed post approval leaves the post pending and sends no committed notification');
    $db->exec('DROP TRIGGER fail_post_audit');
    $media->review($actorIdentity,$pendingPost,'approved','');
    $media->review($actorIdentity,$pendingPost,'approved','');
    $assert($db->query("SELECT status FROM tbl_posts WHERE id=$pendingPost")->fetchColumn()==='approved','identical post approval retry is idempotent');
    $postAuditCheck=$db->prepare("SELECT COUNT(*) FROM tbl_post_audits WHERE post_id=? AND action='approved'");$postAuditCheck->execute([$pendingPost]);
    $assert((int)$postAuditCheck->fetchColumn()===1,'identical post approval retry creates one audit row');
    $media->toggleReaction($actor,$pendingPost,'like',true);$media->toggleReaction($actor,$pendingPost,'like',true);
    $reactionCheck=$db->prepare('SELECT COUNT(*) FROM tbl_post_reactions WHERE post_id=? AND user_id=?');$reactionCheck->execute([$pendingPost,$actor]);
    $assert((int)$reactionCheck->fetchColumn()===1,'repeated Like request creates one reaction');
    $media->toggleReaction($actor,$pendingPost,'like',false);$media->toggleReaction($actor,$pendingPost,'like',false);
    $reactionCheck->execute([$pendingPost,$actor]);$assert((int)$reactionCheck->fetchColumn()===0,'repeated unlike request converges on no reaction');

    $responsibilityPayload=['officer_assignment_id'=>$officerAssignment,'event_schedule_id'=>$schedule,'session_code'=>'whole_day','activity_name'=>'Atomic retry responsibility','team_id'=>$team,'responsibility'=>'media'];
    $responsibilityId=$assignments->assign($responsibilityPayload,$actor);$responsibilityRetryId=$assignments->assign($responsibilityPayload,$actor);
    $assert($responsibilityRetryId===$responsibilityId,'identical officer responsibility retry returns the existing assignment');
    $responsibilityCount=$db->prepare("SELECT COUNT(*) FROM tbl_sbo_event_assignments sea JOIN tbl_officer_responsibilities r ON r.id=sea.responsibility_id WHERE sea.officer_assignment_id=? AND sea.event_schedule_id=? AND sea.session_code='whole_day' AND sea.team_id=? AND r.code='media' AND sea.status='active'");$responsibilityCount->execute([$officerAssignment,$schedule,$team]);
    $assert((int)$responsibilityCount->fetchColumn()===1,'identical officer responsibility retry creates one active assignment');
    $assignments->end($responsibilityId,$actor);$assignments->end($responsibilityId,$actor);
    $assert($db->query("SELECT status FROM tbl_sbo_event_assignments WHERE id=$responsibilityId")->fetchColumn()==='inactive','repeated responsibility end request is idempotent');

    $tokenIdentity=$db->prepare('SELECT event_id,session,token FROM tbl_attendance_qr_tokens WHERE token=?');$tokenIdentity->execute([$qrCard['token']]);$savedToken=$tokenIdentity->fetch();
    $assert(is_array($savedToken)&&(int)$savedToken['event_id']===$event&&(string)$savedToken['session']==='whole_day','failed scan keeps the same QR identity available for retry expected='.$event.' actual='.json_encode($savedToken));
    $scanResult=$scanner->scan($officerUser,$scanPayload);
    $assert($scanResult['checkpoint']==='in','same QR succeeds after the failed attempt is corrected/retried');
    $tokenCheck->execute([$qrCard['token']]);$assert($tokenCheck->fetchColumn()!==null,'successful scan consumes the QR exactly once');
    $scanAttendance=$db->prepare('SELECT a.id,a.status,a.manual_status FROM tbl_attendances a WHERE a.event_id=? AND a.user_id=? AND a.attendance_date=CURDATE()');$scanAttendance->execute([$event,$user]);$scannedRow=$scanAttendance->fetch();
    $scanEntryCount=$db->prepare('SELECT COUNT(*) FROM tbl_attendance_entries WHERE attendance_id=?');$scanEntryCount->execute([$scannedRow['id']]);$evidenceCount=(int)$scanEntryCount->fetchColumn();
    $attendance->update($event,['attendance_date'=>$clock->format('Y-m-d'),'records'=>[$user=>['status'=>'absent']]],$actor);
    $scanAttendance->execute([$event,$user]);$correctedRow=$scanAttendance->fetch();
    $assert($correctedRow['status']==='present'&&$correctedRow['manual_status']==='absent','manual correction is stored separately from scan evidence');
    $scanEntryCount->execute([$correctedRow['id']]);$assert((int)$scanEntryCount->fetchColumn()===$evidenceCount,'manual correction preserves every scan evidence row');
    $effectiveStatus=$db->prepare('SELECT effective_status FROM vw_attendance_effective WHERE id=?');$effectiveStatus->execute([$correctedRow['id']]);$assert($effectiveStatus->fetchColumn()==='absent','official attendance reads the Adviser correction overlay');
    $attendance->update($event,['attendance_date'=>$clock->format('Y-m-d'),'records'=>[$user=>['status'=>null]]],$actor);
    $scanAttendance->execute([$event,$user]);$clearedRow=$scanAttendance->fetch();
    $assert($clearedRow['status']==='present'&&$clearedRow['manual_status']===null,'clearing a correction restores the scan-derived status');
    $scanEntryCount->execute([$clearedRow['id']]);$assert((int)$scanEntryCount->fetchColumn()===$evidenceCount,'clearing a correction does not delete scanner evidence');

    $db->prepare("INSERT INTO tbl_score_categories(event_id,activity_id,name,min_points,max_points,sort_order,created_at,updated_at) VALUES(?,?,'Atomic judged score',0,100,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$event,$activity]);
    $judgedCategory=(int)$db->lastInsertId();
    $db->prepare("INSERT INTO tbl_sbo_event_assignments(officer_assignment_id,event_schedule_id,session_code,activity_id,team_id,responsibility_id,status,assigned_by,created_at,updated_at) VALUES(?,?,'whole_day',?,?,(SELECT id FROM tbl_officer_responsibilities WHERE code='scoring'),'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$officerAssignment,$schedule,$activity,$team,$actor]);
    $scoringTask=(int)$db->lastInsertId();
    $officerScores=new SboScoresRepository($db,new SboAuthorization($db));
    $scoreWorkspace=$officerScores->show($officerUser,$scoringTask);
    $assert(count($scoreWorkspace['teams'])===1&&(int)$scoreWorkspace['teams'][0]['id']===$team,'SBO scoring workspace exposes only the assigned team');
    $assert((int)$scoreWorkspace['selected']['event_schedule_id']===$schedule&&$scoreWorkspace['selected']['session_code']==='whole_day','SBO scoring workspace preserves the assigned event day and session');
    $scoringCategoryRows=$db->query("SELECT id,max_points FROM tbl_score_categories WHERE event_id=$event AND activity_id=$activity")->fetchAll();
    $scoringTeamRows=[['id'=>$team]];
    $completeScores=[];foreach($scoringTeamRows as$scoringTeam)foreach($scoringCategoryRows as$scoringCategory)$completeScores[(int)$scoringTeam['id']][(int)$scoringCategory['id']]=(string)min(50,(float)$scoringCategory['max_points']);
    $scorePayload=['assignment_id'=>$scoringTask,'finalize'=>true,'scores'=>$completeScores];
    $assert($officerScores->save($officerUser,$scorePayload)==='Scores finalized successfully.','first score finalization succeeds');
    $assert($officerScores->save($officerUser,$scorePayload)==='Scores were already finalized.','identical finalization retry is idempotent');
    $changedPayload=$scorePayload;$changedPayload['scores'][$team][$judgedCategory]='60';
    $fails(fn()=>$officerScores->save($officerUser,$changedPayload),'different scores cannot overwrite a finalized sheet');
    $savedScore=$db->prepare('SELECT points FROM tbl_scores WHERE event_id=? AND team_id=? AND score_category_id=?');$savedScore->execute([$event,$team,$judgedCategory]);
    $assert((float)$savedScore->fetchColumn()===50.0,'rejected finalized-score retry leaves stored points unchanged');
    $publishedScore=$db->prepare('SELECT points FROM vw_finalized_scores WHERE event_id=? AND team_id=? AND score_category_id=?');$publishedScore->execute([$event,$team,$judgedCategory]);
    $assert((float)$publishedScore->fetchColumn()===50.0,'finalized score is visible to official score consumers');
    $assert($scores->reopenScores($event,['activity_id'=>$activity],$actor)==='Scoring reopened. Draft scores are now hidden from Leaderboard and Reports.','Adviser can explicitly reopen finalized scoring');
    $publishedScore->execute([$event,$team,$judgedCategory]);$assert($publishedScore->fetchColumn()===false,'reopened draft immediately disappears from official score consumers');
    $draftResult=$scores->saveScores($event,['activity_id'=>$activity,'scores'=>[$judgedCategory=>[$team=>'55']]],$actor);
    $assert($draftResult['status']==='draft','Adviser score edits remain in Draft state');
    $savedScore->execute([$event,$team,$judgedCategory]);$assert((float)$savedScore->fetchColumn()===55.0,'draft score remains available to the score editor');
    $publishedScore->execute([$event,$team,$judgedCategory]);$assert($publishedScore->fetchColumn()===false,'draft score remains hidden from Leaderboard and Reports');

    $userLogBefore=(int)$db->query("SELECT COUNT(*) FROM tbl_activity_logs WHERE action='user_status_changed'")->fetchColumn();
    $desired=$userStatus!==$activeStatus;$users->toggle($user,$actor,$desired);$users->toggle($user,$actor,$desired);
    $assert(((int)$db->query("SELECT status FROM tbl_users WHERE id=$user")->fetchColumn()===$activeStatus)===$desired,'repeated user status request converges on requested state');
    $assert((int)$db->query("SELECT COUNT(*) FROM tbl_activity_logs WHERE action='user_status_changed'")->fetchColumn()===$userLogBefore+1,'repeated user status request logs once');

    $teamLogBefore=(int)$db->query("SELECT COUNT(*) FROM tbl_activity_logs WHERE action='team_status_changed'")->fetchColumn();
    $teamsRepo->toggle($team,$actor,!$teamActive);$teamsRepo->toggle($team,$actor,!$teamActive);
    $assert((bool)$db->query("SELECT is_active FROM tbl_teams WHERE id=$team")->fetchColumn()===!$teamActive,'repeated tribe status request converges on requested state');
    $assert((int)$db->query("SELECT COUNT(*) FROM tbl_activity_logs WHERE action='team_status_changed'")->fetchColumn()===$teamLogBefore+1,'repeated tribe status request logs once');

    if($eligible){
        $assignmentLogs=(int)$db->query("SELECT COUNT(*) FROM tbl_activity_logs WHERE action='event_assigned'")->fetchColumn();
        $events->assign($event,$eligible,$actor);$events->assign($event,$eligible,$actor);
        $q=$db->prepare('SELECT COUNT(*) FROM tbl_event_user WHERE event_id=? AND user_id=?');$q->execute([$event,$eligible]);$assert((int)$q->fetchColumn()===1,'repeated event assignment creates one row');
        $assert((int)$db->query("SELECT COUNT(*) FROM tbl_activity_logs WHERE action='event_assigned'")->fetchColumn()===$assignmentLogs+1,'repeated event assignment logs once');
        $unassignmentLogs=(int)$db->query("SELECT COUNT(*) FROM tbl_activity_logs WHERE action='event_unassigned'")->fetchColumn();
        $events->unassign($event,$eligible,$actor);$events->unassign($event,$eligible,$actor);
        $assert((int)$db->query("SELECT COUNT(*) FROM tbl_activity_logs WHERE action='event_unassigned'")->fetchColumn()===$unassignmentLogs+1,'repeated event unassignment logs once');
    }

    $batch=$db->prepare("INSERT INTO tbl_student_import_batches(original_filename,file_sha256,mode,status,imported_by,created_at) VALUES(? ,?,'replace','completed',?,CURRENT_TIMESTAMP)");
    $batch->execute(['atomicity.xlsx',str_repeat('a',64),$actor]);$batchId=(int)$db->lastInsertId();
    $imports=new StudentRosterImportService($db,new RosterSpreadsheetReader());
    $markFailed=new ReflectionMethod($imports,'markFailedIfPreviewed');$markFailed->invoke($imports,$batchId,'losing concurrent retry');
    $assert($db->query("SELECT status FROM tbl_student_import_batches WHERE id=$batchId")->fetchColumn()==='completed','losing import retry cannot overwrite completed batch as failed');

    echo 'Atomicity and retry regression suite completed.',PHP_EOL;
}finally{
    $originalDbName===false?putenv('DB_NAME'):putenv('DB_NAME='.$originalDbName);
    $live->exec("DROP DATABASE IF EXISTS `$scratch`");
}
