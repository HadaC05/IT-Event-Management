<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/leaderboard.php';
require_once __DIR__.'/sbo-attendance.php';

final class FacultyRepository
{
    private const STUDENTS_PER_PAGE = 25;
    private const HISTORY_PER_PAGE = 10;

    public function __construct(private readonly PDO $db) {}

    private function rows(string $sql, array $params = []): array
    {
        $query = $this->db->prepare($sql);
        $query->execute($params);
        return $query->fetchAll();
    }

    public function teamId(int $userId): ?int
    {
        $rows = $this->rows('SELECT t.id FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id WHERE tu.user_id=? AND t.is_active=1 ORDER BY t.school_year_id DESC,tu.id DESC LIMIT 1', [$userId]);
        return isset($rows[0]) ? (int) $rows[0]['id'] : null;
    }

    public function students(int $userId, array $filters = []): array
    {
        $teamId = $this->teamId($userId);
        if (!$teamId) return ['team' => null, 'students' => [], 'events' => [], 'pagination' => $this->pagination(1, 0, self::STUDENTS_PER_PAGE)];
        $team = $this->rows('SELECT id,name,color FROM tbl_teams WHERE id=?', [$teamId])[0];
        $eventRows = $this->rows("SELECT DISTINCT e.id,e.title FROM tbl_team_user tu
            JOIN vw_attendance_effective a ON a.user_id=tu.user_id JOIN tbl_events e ON e.id=a.event_id
            WHERE tu.team_id=? ORDER BY e.start_at DESC,e.id DESC", [$teamId]);
        $events = [];
        foreach ($eventRows as $event) $events[(int) $event['id']] = $event['title'];

        $search = trim((string) ($filters['search'] ?? ''));
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $eventId = (int) ($filters['event_id'] ?? 0);
        $page = max(1, (int) ($filters['page_number'] ?? $filters['page'] ?? 1));
        if (mb_strlen($search) > 100) throw new InvalidArgumentException('Search may not exceed 100 characters.');
        if (!in_array($status, ['', 'present', 'late', 'absent', 'excused', 'unrecorded'], true)) throw new InvalidArgumentException('Choose a valid attendance status.');
        if ($eventId && !isset($events[$eventId])) throw new InvalidArgumentException('Choose an event with attendance records for this team.');

        $where = ["tu.team_id=:team_id", "r.name='Student'"];
        $params = ['team_id' => $teamId];
        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $where[] = "(u.id_number LIKE :q_id ESCAPE '\\\\' OR u.first_name LIKE :q_first ESCAPE '\\\\' OR u.middle_name LIKE :q_middle ESCAPE '\\\\' OR u.last_name LIKE :q_last ESCAPE '\\\\' OR CONCAT_WS(' ',u.first_name,NULLIF(u.middle_name,''),u.last_name) LIKE :q_full ESCAPE '\\\\')";
            foreach (['q_id','q_first','q_middle','q_last','q_full'] as $key) $params[$key] = $term;
        }
        $eventClause = $eventId ? ' AND attendance.event_id='.$eventId : '';
        if ($status === 'unrecorded') {
            $where[] = "NOT EXISTS(SELECT 1 FROM vw_attendance_effective attendance WHERE attendance.user_id=u.id{$eventClause})";
        } elseif ($status !== '') {
            $where[] = "EXISTS(SELECT 1 FROM vw_attendance_effective attendance WHERE attendance.user_id=u.id{$eventClause} AND attendance.effective_status=:attendance_status)";
            $params['attendance_status'] = $status;
        }
        $from = ' FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level WHERE '.implode(' AND ', $where);
        $count = $this->db->prepare('SELECT COUNT(DISTINCT u.id)'.$from);
        foreach ($params as $key=>$value) $count->bindValue(':'.$key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $count->execute();$total=(int)$count->fetchColumn();$lastPage=max(1,(int)ceil($total/self::STUDENTS_PER_PAGE));$page=min($page,$lastPage);$offset=($page-1)*self::STUDENTS_PER_PAGE;
        $historyEventClause = $eventId ? ' AND history.event_id='.$eventId : '';
        $statement = $this->db->prepare("SELECT DISTINCT u.id,u.id_number,u.first_name,u.middle_name,u.last_name,yl.label year_level,
            (SELECT COUNT(*) FROM vw_attendance_effective history WHERE history.user_id=u.id{$historyEventClause}) attendance_count{$from}
            ORDER BY u.last_name,u.first_name,u.id LIMIT :limit OFFSET :offset");
        foreach ($params as $key=>$value) $statement->bindValue(':'.$key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
        $statement->bindValue(':limit',self::STUDENTS_PER_PAGE,PDO::PARAM_INT);$statement->bindValue(':offset',$offset,PDO::PARAM_INT);$statement->execute();$students=$statement->fetchAll();
        foreach($students as &$student){$student['id']=(int)$student['id'];$student['attendance_count']=(int)$student['attendance_count'];$student['name']=trim($student['first_name'].' '.($student['middle_name']??'').' '.$student['last_name']);$student['team']=$team['name'];unset($student['first_name'],$student['middle_name'],$student['last_name']);}unset($student);
        return ['team'=>$team,'students'=>$students,'events'=>$events,'pagination'=>$this->pagination($page,$total,self::STUDENTS_PER_PAGE)];
    }

    public function studentAttendance(int $userId, array $filters): array
    {
        $teamId=$this->teamId($userId);$studentId=(int)($filters['student_id']??0);$eventId=(int)($filters['event_id']??0);$page=max(1,(int)($filters['page_number']??1));
        if(!$teamId)throw new DomainException('No team is assigned to your Faculty account.');
        $student=$this->rows("SELECT u.id,u.id_number,u.first_name,u.middle_name,u.last_name FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE tu.team_id=? AND u.id=? LIMIT 1",[$teamId,$studentId])[0]??null;
        if(!$student)throw new DomainException('That student is not part of your assigned team.');
        $where=' WHERE a.user_id=?';$params=[$studentId];if($eventId){$where.=' AND a.event_id=?';$params[]=$eventId;}
        $count=$this->db->prepare('SELECT COUNT(*) FROM vw_attendance_effective a'.$where);$count->execute($params);$total=(int)$count->fetchColumn();$lastPage=max(1,(int)ceil($total/self::HISTORY_PER_PAGE));$page=min($page,$lastPage);$offset=($page-1)*self::HISTORY_PER_PAGE;
        $statement=$this->db->prepare("SELECT a.event_id,e.title event_title,a.attendance_date date,a.effective_status status,a.manual_status,a.morning_in_at,a.morning_out_at,a.afternoon_in_at,a.afternoon_out_at FROM vw_attendance_effective a JOIN tbl_events e ON e.id=a.event_id{$where} ORDER BY a.attendance_date DESC,a.event_id DESC LIMIT ? OFFSET ?");
        $position=1;foreach($params as $value)$statement->bindValue($position++,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);$statement->bindValue($position++,self::HISTORY_PER_PAGE,PDO::PARAM_INT);$statement->bindValue($position,$offset,PDO::PARAM_INT);$statement->execute();$records=$statement->fetchAll();foreach($records as &$record)$record['event_id']=(int)$record['event_id'];unset($record);
        return ['student'=>['id'=>(int)$student['id'],'id_number'=>$student['id_number'],'name'=>trim($student['first_name'].' '.($student['middle_name']??'').' '.$student['last_name'])],'records'=>$records,'pagination'=>$this->pagination($page,$total,self::HISTORY_PER_PAGE)];
    }

    private function pagination(int $page,int $total,int $perPage):array{$offset=($page-1)*$perPage;return['current_page'=>$page,'last_page'=>max(1,(int)ceil($total/$perPage)),'per_page'=>$perPage,'total'=>$total,'from'=>$total?$offset+1:null,'to'=>$total?min($offset+$perPage,$total):null];}

    public function team(int $userId): array
    {
        $teamId = $this->teamId($userId);
        if (!$teamId) return ['team'=>null];
        $team = $this->rows('SELECT t.id,t.name,t.color,t.school_year_id,sy.label school_year FROM tbl_teams t JOIN tbl_school_years sy ON sy.id=t.school_year_id WHERE t.id=?', [$teamId])[0];
        $team['members_count'] = (int) $this->rows("SELECT COUNT(*) total FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE tu.team_id=?",[$teamId])[0]['total'];
        $team['member_preview'] = $this->rows("SELECT u.id_number,TRIM(CONCAT_WS(' ',u.first_name,NULLIF(u.middle_name,''),u.last_name)) name,yl.label year_level FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level WHERE tu.team_id=? ORDER BY u.last_name,u.first_name,u.id LIMIT 8",[$teamId]);
        $team['scores'] = $this->rows('SELECT e.title event_title,COALESCE(c.name,\'General\') category,SUM(s.points) points FROM vw_finalized_scores s JOIN tbl_events e ON e.id=s.event_id LEFT JOIN tbl_score_categories c ON c.id=s.score_category_id WHERE s.team_id=? GROUP BY e.id,c.id ORDER BY e.start_at DESC LIMIT 5', [$teamId]);
        foreach ($team['scores'] as &$score) $score['points'] = (float) $score['points'];
        unset($score);
        $team['total_score'] = (float) $this->rows('SELECT COALESCE(SUM(points),0) total FROM vw_finalized_scores WHERE team_id=?', [$teamId])[0]['total'];
        $team['attendance'] = $this->rows("SELECT a.effective_status status,COUNT(*) total FROM vw_attendance_effective a JOIN tbl_team_user tu ON tu.user_id=a.user_id JOIN tbl_users u ON u.id=a.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' JOIN tbl_events e ON e.id=a.event_id JOIN tbl_academic_periods ap ON ap.id=e.academic_period_id WHERE tu.team_id=? AND ap.school_year_id=? GROUP BY a.effective_status", [$teamId,(int)$team['school_year_id']]);
        $team['attendance_total'] = 0;
        foreach ($team['attendance'] as &$attendance) {
            $attendance['total'] = (int) $attendance['total'];
            $team['attendance_total'] += $attendance['total'];
        }
        unset($attendance);
        $team['activities'] = $this->rows('SELECT e.title,e.start_at,e.location FROM tbl_event_team et JOIN tbl_events e ON e.id=et.event_id WHERE et.team_id=? AND e.deleted_at IS NULL AND e.end_at>=CURRENT_TIMESTAMP ORDER BY e.start_at LIMIT 8', [$teamId]);
        $team['announcements'] = $this->rows("SELECT p.content,p.created_at,e.title event_title FROM tbl_posts p LEFT JOIN tbl_events e ON e.id=p.event_id WHERE p.is_official=1 AND p.status='approved' AND p.deleted_at IS NULL AND (p.event_id IS NULL OR EXISTS(SELECT 1 FROM tbl_event_team et WHERE et.event_id=p.event_id AND et.team_id=?)) ORDER BY p.created_at DESC LIMIT 5", [$teamId]);
        $ranked = $this->rows('SELECT s.team_id,SUM(s.points) points FROM vw_finalized_scores s JOIN tbl_teams t ON t.id=s.team_id WHERE t.school_year_id=? GROUP BY s.team_id ORDER BY points DESC', [(int)$team['school_year_id']]);
        $team['rank'] = null;
        foreach ($ranked as $index=>$row) if ((int)$row['team_id']===$teamId) $team['rank']=$index+1;
        unset($team['school_year_id']);
        return ['team'=>$team];
    }

    public function leaderboard(int $userId, array $input): array
    {
        $data = (new LeaderboardRepository($this->db))->view($input);
        $data['team_id'] = $this->teamId($userId);
        $data['events'] = $this->rows('SELECT e.id,e.title,e.start_at,e.end_at FROM tbl_events e WHERE e.deleted_at IS NULL ORDER BY e.start_at DESC');
        return $data;
    }

    public function attendance(int $userId, int $requested=0): array
    {
        $assignments=$this->scanSessions($userId);$selected=null;$next=null;$now=AttendanceScanWindows::now();
        $scanner=new SboAttendanceRepository($this->db,new SboAuthorization($this->db));
        foreach($assignments as &$assignment){
            $assignment+=$scanner->venue((int)$assignment['event_id']);
            if($requested>0&&(int)$assignment['id']===$requested)$selected=$assignment;
            $start=new DateTimeImmutable($assignment['schedule_date'].' '.$assignment['session_start'],new DateTimeZone('Asia/Manila'));
            if($start>$now&&(!$next||$start<$next['date']))$next=['date'=>$start,'name'=>$assignment['session_name'],'event'=>$assignment['event_name']];
        }unset($assignment);
        if(!$selected&&$requested===0){
            foreach($assignments as $candidate)if($candidate['is_session_active']){$selected=$candidate;break;}
            if(!$selected&&count($assignments)===1)$selected=$assignments[0];
        }
        return ['assignments'=>$assignments,'selected_assignment'=>$selected,'server_now'=>$now->format('Y-m-d H:i:s'),
            'next_session'=>$next?['name'=>$next['name'],'event'=>$next['event'],'starts_at'=>$next['date']->format('Y-m-d H:i:s')]:null,
            'recent_scans'=>$selected?$scanner->facultyRecent($selected):[],
            'counts'=>$selected?$scanner->facultyCounts($selected):['total'=>0,'checked_out'=>0,'remaining'=>0]];
    }

    public function scanSessions(int $userId, ?int $teamId=null): array
    {
        $teamId ??= $this->teamId($userId);
        if (!$teamId) return [];
        $rows=$this->rows("SELECT s.*,e.id event_id,e.title event_name,e.location,e.audience_type,t.name scanner_team_name,m.code session_mode,
              1+(SELECT COUNT(*) FROM tbl_event_attendance_schedules prior WHERE prior.event_id=e.id AND prior.schedule_date<s.schedule_date) day_number
            FROM tbl_event_attendance_schedules s JOIN tbl_events e ON e.id=s.event_id AND e.deleted_at IS NULL
            JOIN tbl_teams t ON t.id=? AND t.is_active=1
            JOIN tbl_attendance_session_modes m ON m.id=s.attendance_session_mode_id
            WHERE (e.audience_type='all_students'
              OR (e.audience_type='selected_tribes' AND EXISTS(SELECT 1 FROM tbl_event_team et WHERE et.event_id=e.id AND et.team_id=t.id))
              OR (e.audience_type IN ('selected_year_levels','specific_students') AND EXISTS(SELECT 1 FROM tbl_event_membership_snapshots ms WHERE ms.event_id=e.id AND ms.team_id=t.id)))
              AND s.schedule_date=CURDATE() AND CURDATE() BETWEEN DATE(e.start_at) AND DATE(e.end_at)
            ORDER BY e.start_at,s.id",[$teamId]);
        $sessions=[]; $now=AttendanceScanWindows::now();
        foreach($rows as $row){
            $mode=$row['session_mode'];
            if($mode==='none')continue;
            foreach($mode==='two_sessions'?['morning','afternoon']:['whole_day'] as $session){
                try{$windows=AttendanceScanWindows::forSession($row,$session);}catch(DomainException $e){continue;}
                $sessions[]=$row;
                $index=array_key_last($sessions);
                $sessions[$index]['event_schedule_id']=(int)$row['id'];
                $sessions[$index]['id']=(int)$row['id']*10+match($session){'morning'=>2,'afternoon'=>3,default=>1};
                $sessions[$index]['session_code']=$session;
                $sessions[$index]['session_name']=match($session){'morning'=>'Morning Session','afternoon'=>'Afternoon Session',default=>'Whole Day Session'};
                $sessions[$index]['session_start']=$row[$session==="whole_day"?'whole_day_in_time':$session.'_in_time'];
                $sessions[$index]['session_end']=$row[$session==="whole_day"?'whole_day_out_time':$session.'_out_time'];
                $sessions[$index]['day_number']=(int)$row['day_number'];
                $sessions[$index]['scanner_mode']='specific';
                $sessions[$index]['scanner_team_id']=$teamId;
                $sessions[$index]['team_id']=$teamId;
                $sessions[$index]['team_name']=$row['scanner_team_name'];
                $sessions[$index]['activity_id']=null;
                $sessions[$index]['in_window_open']=AttendanceScanWindows::isOpen($windows['in'],$now);
                $sessions[$index]['out_window_open']=AttendanceScanWindows::isOpen($windows['out'],$now);
                $sessions[$index]['in_opens_at']=$windows['in']['opens']->format('Y-m-d H:i:s');
                $sessions[$index]['in_closes_at']=$windows['in']['closes']->format('Y-m-d H:i:s');
                $sessions[$index]['out_opens_at']=$windows['out']['opens']->format('Y-m-d H:i:s');
                $sessions[$index]['out_closes_at']=$windows['out']['closes']->format('Y-m-d H:i:s');
                $sessions[$index]['is_session_active']=$sessions[$index]['in_window_open']||$sessions[$index]['out_window_open'];
                $sessions[$index]['assignment_state']=$sessions[$index]['is_session_active']?'active':($windows['in']['opens']>$now?'upcoming':'closed');
            }
        }
        return $sessions;
    }

    public function scan(int $userId,array $input): array
    {
        $assignmentId=filter_var($input['assignment_id']??null,FILTER_VALIDATE_INT);
        $scheduleId=filter_var($input['schedule_id']??null,FILTER_VALIDATE_INT);
        $session=(string)($input['session']??'');
        if(!$assignmentId&&!$scheduleId)throw new InvalidArgumentException('Select an active attendance session.');
        $assignment=null;
        foreach($this->scanSessions($userId) as $candidate){
            if(($assignmentId&&(int)$candidate['id']===$assignmentId)||(!$assignmentId&&(int)$candidate['event_schedule_id']===$scheduleId&&$candidate['session_code']===$session)){$assignment=$candidate;break;}
        }
        if(!$assignment)throw new DomainException('You are not assigned to scan this event or team.');
        $scanner=new SboAttendanceRepository($this->db,new SboAuthorization($this->db));
        $assignment+=$scanner->venue((int)$assignment['event_id']);
        return $scanner->scanFaculty($userId,$input,$assignment);
    }

    public function profile(int $userId): array
    {
        return $this->rows('SELECT first_name,middle_name,last_name,username,email,bio,profile_photo_path FROM tbl_users WHERE id=?', [$userId])[0];
    }

    public function saveProfile(int $userId, array $input, array $files): array
    {
        $first = trim((string)($input['first_name'] ?? '')); $last = trim((string)($input['last_name'] ?? ''));
        $middle = trim((string)($input['middle_name'] ?? '')); $bio = trim((string)($input['bio'] ?? '')); $email = trim((string)($input['email'] ?? ''));
        if ($first==='' || $last==='' || mb_strlen($first)>100 || mb_strlen($last)>100 || mb_strlen($middle)>100 || mb_strlen($bio)>280 || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter valid profile details.');
        $path = null; $file=$files['photo'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
            if ($file['error']!==UPLOAD_ERR_OK || $file['size']>5*1024*1024) throw new InvalidArgumentException('Choose a photo no larger than 5 MB.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
            if (!$ext || !getimagesize($file['tmp_name'])) throw new InvalidArgumentException('Use a valid JPG, PNG, or WebP photo.');
            $dir=dirname(__DIR__).'/assets/uploads/profiles'; if (!is_dir($dir)) mkdir($dir,0775,true);
            $path='assets/uploads/profiles/'.bin2hex(random_bytes(16)).'.'.$ext;
            if (!move_uploaded_file($file['tmp_name'],dirname(__DIR__).'/'.$path)) throw new RuntimeException('Photo upload failed.');
        }
        $oldPath = null;
        try {
            $this->db->beginTransaction();
            $userLock=$this->db->prepare('SELECT profile_photo_path FROM tbl_users WHERE id=? FOR UPDATE');$userLock->execute([$userId]);$locked=$userLock->fetch();
            if(!$locked)throw new InvalidArgumentException('Faculty account not found.');
            $emailLock=$this->db->prepare('SELECT id FROM tbl_users WHERE email=? AND id<>? FOR UPDATE');$emailLock->execute([$email,$userId]);
            if($emailLock->fetch())throw new InvalidArgumentException('Email is already in use.');
            $oldPath=(string)($locked['profile_photo_path']??'');
            $sql='UPDATE tbl_users SET first_name=?,middle_name=?,last_name=?,email=?,bio=?,updated_at=CURRENT_TIMESTAMP'.($path?',profile_photo_path=?':'').' WHERE id=?';
            $params=[$first,$middle ?: null,$last,$email,$bio ?: null]; if ($path) $params[]=$path; $params[]=$userId;
            $this->db->prepare($sql)->execute($params);
            $this->db->commit();
        } catch (Throwable $exception) {
            if($this->db->inTransaction())$this->db->rollBack();
            if($path){$stored=dirname(__DIR__).'/'.$path;if(is_file($stored))unlink($stored);}
            throw $exception;
        }
        if($path&&$oldPath&&$oldPath!==$path&&str_starts_with($oldPath,'assets/uploads/profiles/')){$oldFile=dirname(__DIR__).'/'.$oldPath;if(is_file($oldFile))unlink($oldFile);}
        $_SESSION['user']['first_name']=$first; $_SESSION['user']['middle_name']=$middle; $_SESSION['user']['last_name']=$last; $_SESSION['user']['full_name']=trim("$first $middle $last"); $_SESSION['user']['email']=$email;
        return $this->profile($userId);
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;
$actor=AuthGuard::requireRole('Faculty'); $repo=new FacultyRepository((new Database())->connection()); $page=(string)($_GET['page'] ?? $_POST['page'] ?? 'students');
try {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        $data=match($page) {
            'students'=>$repo->students((int)$actor['id'],$_GET), 'student_history'=>$repo->studentAttendance((int)$actor['id'],$_GET), 'team'=>$repo->team((int)$actor['id']),
            'leaderboard'=>$repo->leaderboard((int)$actor['id'],$_GET), 'attendance'=>$repo->attendance((int)$actor['id'],(int)($_GET['assignment_id']??0)),
            'profile'=>$repo->profile((int)$actor['id']), default=>null,
        };
        if ($data===null) JsonResponse::send(['success'=>false,'message'=>'Unknown Faculty page.'],404);
        JsonResponse::send(['success'=>true,'data'=>$data]);
    }
    if ($_SERVER['REQUEST_METHOD']!=='POST') JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
    if ($page==='scan'||$page==='attendance') {
        $input=json_decode(file_get_contents('php://input'),true);
        $data=$repo->scan((int)$actor['id'],is_array($input)?$input:[]);
    }
    elseif ($page==='profile') $data=$repo->saveProfile((int)$actor['id'],$_POST,$_FILES);
    else JsonResponse::send(['success'=>false,'message'=>'This page is view only.'],405);
    JsonResponse::send(['success'=>true,'data'=>$data]);
} catch (ScanRateLimitException $e) { JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],429); }
catch (DomainException $e) { JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],403); }
catch (InvalidArgumentException $e) { JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],422); }
catch (LogicException $e) { JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],409); }
catch (Throwable $e) { error_log($e->getMessage()); JsonResponse::send(['success'=>false,'message'=>'Faculty request failed.'],500); }
