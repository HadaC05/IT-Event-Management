<?php

declare(strict_types=1);

require_once __DIR__.'/attendance.php';

function attendanceRosterAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$database=(new Database())->connection();
$eventId=(int)($database->query('SELECT event_id FROM tbl_event_membership_snapshots GROUP BY event_id ORDER BY COUNT(*) DESC LIMIT 1')->fetchColumn()?:0);
attendanceRosterAssert($eventId>0,'An event membership snapshot is required for the attendance roster scaling checks.');

$repository=new AttendanceManagementRepository($database);
$started=microtime(true);$first=$repository->roster($eventId,[]);$elapsedMs=(microtime(true)-$started)*1000;
$date=$first['attendance_date'];

$expectedTotalStatement=$database->prepare('SELECT COUNT(*) FROM (
    SELECT user_id FROM tbl_event_membership_snapshots WHERE event_id=?
    UNION
    SELECT user_id FROM tbl_attendances WHERE event_id=? AND attendance_date=?
) roster');
$expectedTotalStatement->execute([$eventId,$eventId,$date]);$expectedTotal=(int)$expectedTotalStatement->fetchColumn();

attendanceRosterAssert(count($first['participants'])<=10,'An attendance roster page may contain at most 10 participants.');
attendanceRosterAssert($first['pagination']['per_page']===10,'Attendance roster pagination must remain at 10 participants.');
attendanceRosterAssert($first['pagination']['total']===$expectedTotal,'The unfiltered roster total must match the distinct database participant total.');
attendanceRosterAssert($first['participant_total']===$expectedTotal,'The participant summary must match the distinct database participant total.');
attendanceRosterAssert(strlen((string)json_encode($first))<50000,'An attendance roster page must remain below 50 KB.');
attendanceRosterAssert($elapsedMs<2000,'The first attendance roster page must load in under two seconds.');
foreach($first['participants'] as $participant){attendanceRosterAssert(is_bool($participant['is_expected']),'Roster rows must expose a boolean expected-participant flag.');}

if($first['pagination']['last_page']>1){
    $second=$repository->roster($eventId,['page'=>2]);
    attendanceRosterAssert(!array_intersect(array_column($first['participants'],'id'),array_column($second['participants'],'id')),'Adjacent attendance roster pages must not overlap.');
}

if($first['participants']){
    $participant=$first['participants'][0];
    $searched=$repository->roster($eventId,['search'=>$participant['id_number']]);
    attendanceRosterAssert(in_array($participant['id'],array_column($searched['participants'],'id'),true),'Exact ID search must return the participant.');
}

$unrecorded=$repository->roster($eventId,['status'=>'unrecorded']);
foreach($unrecorded['participants'] as $participant)attendanceRosterAssert($participant['attendance_status']===null,'The unrecorded filter may return only participants without an attendance record.');

$denied=false;
try{$repository->update($eventId,['attendance_date'=>$date,'records'=>['999999999'=>['status'=>'present']]],1);}catch(InvalidArgumentException){$denied=true;}
attendanceRosterAssert($denied,'Attendance updates must reject users outside the event participant set.');

echo 'Attendance roster scaling checks passed in '.round($elapsedMs,2)." ms.\n";
