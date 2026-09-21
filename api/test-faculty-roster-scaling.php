<?php

declare(strict_types=1);

require_once __DIR__.'/faculty.php';

function facultyScalingAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$database=(new Database())->connection();
$faculty=$database->query("SELECT u.id FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id AND r.name='Faculty'
    WHERE EXISTS(SELECT 1 FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1 WHERE tu.user_id=u.id)
    ORDER BY u.id LIMIT 1")->fetchColumn();
facultyScalingAssert((int)$faculty>0,'A Faculty account with an active team is required for the scaling test.');
$repository=new FacultyRepository($database);$teamId=$repository->teamId((int)$faculty);
facultyScalingAssert($teamId!==null,'The Faculty test account must resolve to an active team.');
$expected=$database->prepare("SELECT COUNT(*) FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE tu.team_id=?");$expected->execute([$teamId]);$expectedTotal=(int)$expected->fetchColumn();

$first=$repository->students((int)$faculty,[]);
facultyScalingAssert(count($first['students'])<=25,'Faculty student pages may contain at most 25 students.');
facultyScalingAssert($first['pagination']['per_page']===25,'Faculty student pagination must remain at 25 records.');
facultyScalingAssert($first['pagination']['total']===$expectedTotal,'Faculty student total must match the assigned team.');
facultyScalingAssert(strlen(json_encode($first))<50000,'A Faculty student page must remain below 50 KB.');
foreach($first['students'] as $student)facultyScalingAssert(!array_key_exists('attendance',$student),'Student pages must not embed attendance history.');

if($first['pagination']['last_page']>1){$second=$repository->students((int)$faculty,['page_number'=>2]);facultyScalingAssert(!array_intersect(array_column($first['students'],'id'),array_column($second['students'],'id')),'Adjacent Faculty student pages must not overlap.');}
if($first['students']){
    $student=$first['students'][0];$searched=$repository->students((int)$faculty,['search'=>$student['id_number']]);facultyScalingAssert(in_array($student['id'],array_column($searched['students'],'id'),true),'Exact student-ID search must return the team member.');
    $history=$repository->studentAttendance((int)$faculty,['student_id'=>$student['id']]);facultyScalingAssert(count($history['records'])<=10,'Lazy attendance history may contain at most 10 records per page.');facultyScalingAssert($history['pagination']['per_page']===10,'Attendance-history pagination must remain at 10 records.');
    $eventId=(int)($database->query('SELECT id FROM tbl_events WHERE deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn()?:0);
    if($eventId){
        $database->beginTransaction();
        try{
            $insert=$database->prepare("INSERT INTO tbl_attendances(event_id,user_id,attendance_date,status,created_at,updated_at) VALUES(?,?,?,'present',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
            for($day=1;$day<=11;$day++)$insert->execute([$eventId,$student['id'],sprintf('2098-01-%02d',$day)]);
            $pagedHistory=$repository->studentAttendance((int)$faculty,['student_id'=>$student['id'],'event_id'=>$eventId]);
            facultyScalingAssert(count($pagedHistory['records'])===10,'Lazy attendance history must cap populated pages at 10 records.');
            facultyScalingAssert($pagedHistory['pagination']['total']>=11&&$pagedHistory['pagination']['last_page']>1,'Long attendance history must expose pagination.');
        }finally{if($database->inTransaction())$database->rollBack();}
    }
}

$outside=$database->prepare("SELECT u.id FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE tu.team_id<>? AND NOT EXISTS(SELECT 1 FROM tbl_team_user own WHERE own.team_id=? AND own.user_id=u.id) LIMIT 1");$outside->execute([$teamId,$teamId]);$outsideId=(int)($outside->fetchColumn()?:0);
if($outsideId){$denied=false;try{$repository->studentAttendance((int)$faculty,['student_id'=>$outsideId]);}catch(DomainException){$denied=true;}facultyScalingAssert($denied,'Faculty must not load attendance for students outside the assigned team.');}

$team=$repository->team((int)$faculty)['team'];
facultyScalingAssert(!array_key_exists('members',$team),'Faculty team summary must not contain the full roster.');
facultyScalingAssert($team['members_count']===$expectedTotal,'Faculty team summary must retain the exact member count.');
facultyScalingAssert(count($team['member_preview'])<=8,'Faculty team summary may preview at most eight members.');

echo "Faculty roster scaling checks passed.\n";
