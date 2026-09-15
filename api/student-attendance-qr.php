<?php
declare(strict_types=1);
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class StudentAttendanceQrRepository {
    public function __construct(private readonly PDO $db) {}
    public function issue(int $studentId):array {
        $now=new DateTimeImmutable('now',new DateTimeZone('Asia/Manila'));
        $today=$now->format('Y-m-d');$clock=$now->format('H:i:s');
        $q=$this->db->prepare("SELECT e.id event_id,e.title,e.audience_type,s.schedule_date,s.whole_day_in_time,s.whole_day_out_time,
            s.morning_in_time,s.morning_out_time,s.afternoon_in_time,s.afternoon_out_time,m.code session_mode
            FROM tbl_events e JOIN tbl_event_attendance_schedules s ON s.event_id=e.id
            JOIN tbl_attendance_session_modes m ON m.id=s.attendance_session_mode_id
            WHERE e.deleted_at IS NULL AND s.schedule_date=? AND ? BETWEEN DATE(e.start_at) AND DATE(e.end_at)
            ORDER BY e.start_at");
        $q->execute([$today,$today]);
        foreach($q->fetchAll() as $event){
            $sessions=$event['session_mode']==='two_sessions'?
                [['morning',$event['morning_in_time'],$event['morning_out_time']],['afternoon',$event['afternoon_in_time'],$event['afternoon_out_time']]]:
                ($event['session_mode']==='whole_day'?[['whole_day',$event['whole_day_in_time'],$event['whole_day_out_time']]]:[]);
            foreach($sessions as [$session,$start,$end]){
                if(!$start||!$end||$clock<$start||$clock>$end)continue;
                if(!$this->eligible($studentId,$event))continue;
                $find=$this->db->prepare('SELECT id,token,DATE(updated_at) issued_date FROM tbl_attendance_qr_tokens WHERE event_id=? AND user_id=? AND session=?');
                $find->execute([$event['event_id'],$studentId,$session]);$existing=$find->fetch();
                $token=$existing['token']??null;
                if($existing&&$existing['issued_date']!==$today){
                    $replacement=rtrim(strtr(base64_encode(random_bytes(24)),'+/','-_'),'=');
                    $this->db->prepare('UPDATE tbl_attendance_qr_tokens SET token=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND DATE(updated_at)<>?')
                        ->execute([$replacement,$existing['id'],$today]);
                    $find->execute([$event['event_id'],$studentId,$session]);$token=$find->fetch()['token'];
                }
                if(!$token){
                    $token=rtrim(strtr(base64_encode(random_bytes(24)),'+/','-_'),'=');
                    try{$this->db->prepare('INSERT INTO tbl_attendance_qr_tokens(event_id,user_id,session,token,created_at,updated_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')
                        ->execute([$event['event_id'],$studentId,$session,$token]);}
                    catch(PDOException $e){if((string)$e->getCode()!=='23000')throw $e;$find->execute([$event['event_id'],$studentId,$session]);$token=$find->fetch()['token']??null;if(!$token)throw $e;}
                }
                return ['event_name'=>$event['title'],'session'=>$session,'schedule_date'=>$today,'token'=>$token];
            }
        }
        return ['event_name'=>null,'session'=>null,'schedule_date'=>$today,'token'=>null];
    }
    private function eligible(int $student,array $event):bool {
        $q=$this->db->prepare("SELECT COUNT(*) FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student'
            JOIN tbl_user_statuses us ON us.id=u.status AND us.label='active'
            WHERE u.id=? AND
            (?='all_students'
             OR (?='selected_tribes' AND EXISTS(SELECT 1 FROM tbl_team_user tu JOIN tbl_event_team et ON et.team_id=tu.team_id WHERE tu.user_id=u.id AND et.event_id=?))
             OR (?='selected_year_levels' AND EXISTS(SELECT 1 FROM tbl_event_year_level yl WHERE yl.event_id=? AND yl.year_level_id=u.year_level))
             OR (?='specific_students' AND EXISTS(SELECT 1 FROM tbl_event_participants p WHERE p.event_id=? AND p.user_id=u.id)))");
        $audience=$event['audience_type'];$id=$event['event_id'];
        $q->execute([$student,$audience,$audience,$id,$audience,$id,$audience,$id]);
        return (bool)$q->fetchColumn();
    }
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))!==__FILE__)return;
$actor=AuthGuard::requireRole('Student');
if($_SERVER['REQUEST_METHOD']!=='POST')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
if(!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null))JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
try{$result=(new StudentAttendanceQrRepository((new Database())->connection()))->issue((int)$actor['id']);JsonResponse::send(['success'=>true,'message'=>$result['token']?'Attendance QR is ready.':'No active attendance session is available for your account.','data'=>$result]);}
catch(Throwable $e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>'Attendance QR could not be prepared.'],500);}
