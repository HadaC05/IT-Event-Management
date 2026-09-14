<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/SboAuthorization.php';

final class SboAttendanceRepository
{
    public function __construct(private readonly PDO $db,private readonly SboAuthorization $authorization) {}

    public function dashboard(int $officerId,int $requestedAssignment=0):array
    {
        $assignments=$this->authorization->assignments($officerId,'attendance');
        $selected=null;
        if($requestedAssignment>0){foreach($assignments as $candidate)if($candidate['id']===$requestedAssignment)$selected=$candidate;}
        if(!$selected&&count($assignments)===1)$selected=$assignments[0];
        return ['assignments'=>$assignments,'selected_assignment'=>$selected,'recent_scans'=>$selected?$this->recent($selected['id']):[]];
    }

    public function scan(int $officerId,array $input):array
    {
        $assignmentId=(int)($input['assignment_id']??0);
        if($assignmentId<1)throw new InvalidArgumentException('Select an active assignment first.');
        $assignment=$this->authorization->assignment($officerId,$assignmentId,'attendance');
        if(!$assignment['is_session_active'])throw new InvalidArgumentException('Inactive session. Scanning is available only during the assigned session time.');
        $mode=(string)($input['mode']??'qr');
        $value=trim((string)($input[$mode==='manual'?'student_id':'code']??''));
        if($value==='')throw new InvalidArgumentException($mode==='manual'?'Enter a Student ID.':'Invalid QR code.');

        if($mode==='qr'){
            if(preg_match('/(?:[?&]token=|^token:)([A-Za-z0-9_-]{8,64})/',$value,$matches))$value=$matches[1];
            $statement=$this->db->prepare('SELECT u.* FROM tbl_attendance_qr_tokens q JOIN tbl_users u ON u.id=q.user_id WHERE q.token=? AND q.event_id=? AND q.session=? LIMIT 1');
            $statement->execute([$value,$assignment['event_id'],$assignment['session_code']]);$student=$statement->fetch();
            if(!$student&&preg_match('/^[A-Za-z0-9-]{4,40}$/',$value)){$statement=$this->db->prepare("SELECT u.* FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id WHERE u.id_number=? AND r.name='Student' LIMIT 1");$statement->execute([$value]);$student=$statement->fetch();}
            if(!$student)throw new InvalidArgumentException('Invalid QR code.');
        }else{
            $statement=$this->db->prepare("SELECT u.* FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id WHERE u.id_number=? AND r.name='Student' LIMIT 1");
            $statement->execute([$value]);$student=$statement->fetch();
            if(!$student)throw new InvalidArgumentException('Student ID was not found.');
        }

        $teamCheck=$this->db->prepare('SELECT COUNT(*) FROM tbl_team_user WHERE user_id=? AND team_id=?');$teamCheck->execute([(int)$student['id'],$assignment['team_id']]);
        if(!(bool)$teamCheck->fetchColumn())throw new InvalidArgumentException('Student from another team.');
        if(!$this->authorization->studentIsEligible($assignment,(int)$student['id']))throw new InvalidArgumentException('This student is not eligible for the assigned event.');

        $now=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
        $this->db->beginTransaction();
        try{
            $parent=$this->db->prepare("INSERT INTO tbl_attendances(event_id,user_id,attendance_date,status,checked_in_at,recorded_by,created_at,updated_at)
              VALUES(?,?,?,'present',?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
              ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),status=IF(status IN ('absent','excused'),'present',status),checked_in_at=COALESCE(checked_in_at,VALUES(checked_in_at)),recorded_by=VALUES(recorded_by),updated_at=CURRENT_TIMESTAMP");
            $parent->execute([$assignment['event_id'],(int)$student['id'],$assignment['schedule_date'],$now,$officerId]);$attendanceId=(int)$this->db->lastInsertId();
            $duplicate=$this->db->prepare('SELECT id FROM tbl_attendance_entries WHERE attendance_id=? AND event_schedule_id=? AND session_code=? AND activity_id=? AND team_id=? LIMIT 1');
            $duplicate->execute([$attendanceId,$assignment['event_schedule_id'],$assignment['session_code'],$assignment['activity_id'],$assignment['team_id']]);
            if($duplicate->fetch())throw new LogicException('Duplicate attendance. This student is already recorded for the assigned session.');
            $entry=$this->db->prepare("INSERT INTO tbl_attendance_entries(attendance_id,event_schedule_id,sbo_event_assignment_id,session_code,activity_id,team_id,recorded_by,scanned_at,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,'present',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
            $entry->execute([$attendanceId,$assignment['event_schedule_id'],$assignmentId,$assignment['session_code'],$assignment['activity_id'],$assignment['team_id'],$officerId,$now]);
            $this->db->prepare("INSERT INTO tbl_activity_logs(actor_id,event_id,officer_assignment_id,action,acting_role,description,created_at,updated_at) VALUES(?,?,?,'sbo_attendance_scanned','SBO Officer',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$officerId,$assignment['event_id'],$assignmentId,'Recorded attendance for '.$student['id_number'].'.']);
            $this->db->commit();
        }catch(Throwable $exception){if($this->db->inTransaction())$this->db->rollBack();throw$exception;}
        return ['message'=>'Attendance recorded successfully.','student'=>['id'=>(int)$student['id'],'id_number'=>$student['id_number'],'full_name'=>$this->name($student)],'scanned_at'=>$now,'recent_scans'=>$this->recent($assignmentId)];
    }

    private function recent(int $assignmentId):array
    {
        $statement=$this->db->prepare("SELECT ae.id,ae.scanned_at,ae.status,u.id_number,u.first_name,u.middle_name,u.last_name,yl.label year_level
          FROM tbl_attendance_entries ae JOIN tbl_attendances atd ON atd.id=ae.attendance_id JOIN tbl_users u ON u.id=atd.user_id
          LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level WHERE ae.sbo_event_assignment_id=? ORDER BY ae.scanned_at DESC LIMIT 12");
        $statement->execute([$assignmentId]);$rows=$statement->fetchAll();
        foreach($rows as &$row){$row['id']=(int)$row['id'];$row['full_name']=$this->name($row);}unset($row);return$rows;
    }
    private function name(array $row):string{return trim(implode(' ',array_filter([$row['first_name']??null,$row['middle_name']??null,$row['last_name']??null])));}
}

$actor=AuthGuard::requireRole('SBO Officer');$db=(new Database())->connection();$repository=new SboAttendanceRepository($db,new SboAuthorization($db));
try{
    if($_SERVER['REQUEST_METHOD']==='GET')JsonResponse::send(['success'=>true,'data'=>$repository->dashboard((int)$actor['id'],(int)($_GET['assignment_id']??0))]);
    if($_SERVER['REQUEST_METHOD']!=='POST')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
    if(!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null))JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
    $input=json_decode(file_get_contents('php://input'),true);if(!is_array($input))$input=$_POST;$result=$repository->scan((int)$actor['id'],$input);JsonResponse::send(['success'=>true]+$result);
}catch(DomainException $exception){JsonResponse::send(['success'=>false,'message'=>$exception->getMessage()],403);}catch(LogicException $exception){JsonResponse::send(['success'=>false,'message'=>$exception->getMessage(),'code'=>'duplicate'],409);}catch(InvalidArgumentException $exception){JsonResponse::send(['success'=>false,'message'=>$exception->getMessage()],422);}catch(Throwable $exception){error_log($exception->getMessage());JsonResponse::send(['success'=>false,'message'=>'Attendance scan failed.'],500);}
