<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/leaderboard.php';
require_once __DIR__.'/sbo-attendance.php';

final class FacultyRepository
{
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

    public function students(int $userId): array
    {
        $teamId = $this->teamId($userId);
        if (!$teamId) return ['team' => null, 'students' => [], 'events' => []];
        $team = $this->rows('SELECT id,name,color FROM tbl_teams WHERE id=?', [$teamId])[0];
        $students = $this->rows("SELECT u.id,u.id_number,u.first_name,u.middle_name,u.last_name,yl.label year_level,
            a.event_id,e.title event_title,a.attendance_date,a.status,a.morning_in_at,a.morning_out_at,a.afternoon_in_at,a.afternoon_out_at
            FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student'
            LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level
            LEFT JOIN tbl_attendances a ON a.user_id=u.id LEFT JOIN tbl_events e ON e.id=a.event_id
            WHERE tu.team_id=? ORDER BY u.last_name,u.first_name,a.attendance_date DESC", [$teamId]);
        $result = []; $events = [];
        foreach ($students as $row) {
            $id = (int) $row['id'];
            if (!isset($result[$id])) $result[$id] = ['id'=>$id, 'id_number'=>$row['id_number'], 'name'=>trim($row['first_name'].' '.($row['middle_name'] ?? '').' '.$row['last_name']), 'year_level'=>$row['year_level'], 'team'=>$team['name'], 'attendance'=>[]];
            if ($row['event_id'] !== null) {
                $events[(int)$row['event_id']] = $row['event_title'];
                $result[$id]['attendance'][] = ['event_id'=>(int)$row['event_id'], 'event_title'=>$row['event_title'], 'date'=>$row['attendance_date'], 'status'=>$row['status'], 'morning_in_at'=>$row['morning_in_at'], 'morning_out_at'=>$row['morning_out_at'], 'afternoon_in_at'=>$row['afternoon_in_at'], 'afternoon_out_at'=>$row['afternoon_out_at']];
            }
        }
        return ['team'=>$team, 'students'=>array_values($result), 'events'=>$events];
    }

    public function team(int $userId): array
    {
        $teamId = $this->teamId($userId);
        if (!$teamId) return ['team'=>null];
        $team = $this->rows('SELECT t.id,t.name,t.color,sy.label school_year FROM tbl_teams t JOIN tbl_school_years sy ON sy.id=t.school_year_id WHERE t.id=?', [$teamId])[0];
        $team['members'] = $this->students($userId)['students'];
        $team['scores'] = $this->rows('SELECT e.title event_title,COALESCE(c.name,\'General\') category,SUM(s.points) points FROM tbl_scores s JOIN tbl_events e ON e.id=s.event_id LEFT JOIN tbl_score_categories c ON c.id=s.score_category_id WHERE s.team_id=? GROUP BY e.id,c.id ORDER BY e.start_at DESC', [$teamId]);
        $team['attendance'] = $this->rows("SELECT a.status,COUNT(*) total FROM tbl_attendances a JOIN tbl_team_user tu ON tu.user_id=a.user_id JOIN tbl_users u ON u.id=a.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE tu.team_id=? GROUP BY a.status", [$teamId]);
        $team['activities'] = $this->rows('SELECT e.title,e.start_at,e.location FROM tbl_event_team et JOIN tbl_events e ON e.id=et.event_id WHERE et.team_id=? AND e.deleted_at IS NULL AND e.end_at>=CURRENT_TIMESTAMP ORDER BY e.start_at LIMIT 8', [$teamId]);
        $team['announcements'] = $this->rows("SELECT p.content,p.created_at,e.title event_title FROM tbl_posts p LEFT JOIN tbl_events e ON e.id=p.event_id WHERE p.is_official=1 AND p.status='approved' AND p.deleted_at IS NULL AND (p.event_id IS NULL OR EXISTS(SELECT 1 FROM tbl_event_team et WHERE et.event_id=p.event_id AND et.team_id=?)) ORDER BY p.created_at DESC LIMIT 5", [$teamId]);
        $ranked = $this->rows('SELECT team_id,SUM(points) points FROM tbl_scores GROUP BY team_id ORDER BY points DESC');
        $team['rank'] = null;
        foreach ($ranked as $index=>$row) if ((int)$row['team_id']===$teamId) $team['rank']=$index+1;
        return ['team'=>$team];
    }

    public function leaderboard(int $userId, array $input): array
    {
        $data = (new LeaderboardRepository($this->db))->view($input);
        $data['team_id'] = $this->teamId($userId);
        $data['events'] = $this->rows('SELECT e.id,e.title,e.start_at,e.end_at FROM tbl_events e WHERE e.deleted_at IS NULL ORDER BY e.start_at DESC');
        return $data;
    }

    public function attendance(int $userId): array
    {
        $teamId=$this->teamId($userId);
        if (!$teamId) return ['team'=>null,'sessions'=>[],'records'=>[]];
        $team=$this->rows('SELECT id,name FROM tbl_teams WHERE id=?',[$teamId])[0];
        $records=$this->rows("SELECT ae.scanned_at,ae.phase,a.status,a.attendance_date,e.title event_title,u.id_number,u.first_name,u.middle_name,u.last_name
            FROM tbl_attendance_entries ae JOIN tbl_attendances a ON a.id=ae.attendance_id JOIN tbl_events e ON e.id=a.event_id
            JOIN tbl_users u ON u.id=a.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student'
            WHERE ae.recorded_by=? AND ae.team_id=? ORDER BY ae.scanned_at DESC LIMIT 100",[$userId,$teamId]);
        foreach($records as &$record)$record['student_name']=trim($record['first_name'].' '.($record['middle_name']??'').' '.$record['last_name']);
        unset($record);
        return ['team'=>$team,'sessions'=>$this->scanSessions($userId,$teamId),'records'=>$records];
    }

    public function scanSessions(int $userId, ?int $teamId=null): array
    {
        $teamId ??= $this->teamId($userId);
        if (!$teamId) return [];
        $rows=$this->rows("SELECT s.*,e.id event_id,e.title event_name,e.audience_type,t.name scanner_team_name,m.code session_mode
            FROM tbl_event_attendance_schedules s JOIN tbl_events e ON e.id=s.event_id AND e.deleted_at IS NULL
            JOIN tbl_teams t ON t.id=? AND t.is_active=1
            JOIN tbl_attendance_session_modes m ON m.id=s.attendance_session_mode_id
            WHERE EXISTS(SELECT 1 FROM tbl_event_user eu WHERE eu.event_id=e.id AND eu.user_id=?)
              AND s.schedule_date=CURDATE() AND CURDATE() BETWEEN DATE(e.start_at) AND DATE(e.end_at)
            ORDER BY e.start_at,s.id",[$teamId,$userId]);
        $sessions=[]; $now=AttendanceScanWindows::now();
        foreach($rows as $row){
            $mode=$row['session_mode'];
            if($mode==='none')continue;
            foreach($mode==='two_sessions'?['morning','afternoon']:['whole_day'] as $session){
                try{$windows=AttendanceScanWindows::forSession($row,$session);}catch(DomainException $e){continue;}
                $sessions[]=$row;
                $index=array_key_last($sessions);
                $sessions[$index]['event_schedule_id']=(int)$row['id'];
                $sessions[$index]['session_code']=$session;
                $sessions[$index]['session_name']=match($session){'morning'=>'Morning Session','afternoon'=>'Afternoon Session',default=>'Whole Day Session'};
                $sessions[$index]['scanner_mode']='specific';
                $sessions[$index]['scanner_team_id']=$teamId;
                $sessions[$index]['activity_id']=null;
                $sessions[$index]['in_window_open']=AttendanceScanWindows::isOpen($windows['in'],$now);
                $sessions[$index]['out_window_open']=AttendanceScanWindows::isOpen($windows['out'],$now);
                $sessions[$index]['in_opens_at']=$windows['in']['opens']->format('Y-m-d H:i:s');
                $sessions[$index]['in_closes_at']=$windows['in']['closes']->format('Y-m-d H:i:s');
                $sessions[$index]['out_opens_at']=$windows['out']['opens']->format('Y-m-d H:i:s');
                $sessions[$index]['out_closes_at']=$windows['out']['closes']->format('Y-m-d H:i:s');
            }
        }
        return $sessions;
    }

    public function scan(int $userId,array $input): array
    {
        $scheduleId=filter_var($input['schedule_id']??null,FILTER_VALIDATE_INT);
        $session=(string)($input['session']??'');
        if(!$scheduleId || !in_array($session,['whole_day','morning','afternoon'],true))throw new InvalidArgumentException('Select an active attendance session.');
        $assignment=null;
        foreach($this->scanSessions($userId) as $candidate)if((int)$candidate['event_schedule_id']===$scheduleId && $candidate['session_code']===$session){$assignment=$candidate;break;}
        if(!$assignment)throw new DomainException('You are not assigned to scan this event or team.');
        return (new SboAttendanceRepository($this->db,new SboAuthorization($this->db)))->scanFaculty($userId,$input,$assignment);
    }

    public function posts(int $userId): array
    {
        $posts = $this->rows("SELECT p.id,p.user_id,p.event_id,p.content,p.image_path,p.created_at,e.title event_title,e.end_at,u.first_name,u.last_name,r.name author_role FROM tbl_posts p JOIN tbl_users u ON u.id=p.user_id JOIN tbl_roles r ON r.id=u.role_id LEFT JOIN tbl_events e ON e.id=p.event_id WHERE p.status='approved' AND p.deleted_at IS NULL ORDER BY p.created_at DESC LIMIT 100");
        return ['posts'=>$posts, 'user_id'=>$userId, 'events'=>$this->rows('SELECT id,title,end_at FROM tbl_events WHERE deleted_at IS NULL ORDER BY start_at DESC')];
    }

    public function savePost(int $userId, array $input, array $files): void
    {
        $action = (string)($input['action'] ?? 'create');
        if (!in_array($action, ['create','update','delete'], true)) throw new InvalidArgumentException('Unknown post action.');
        $id = (int)($input['id'] ?? 0);
        if ($action !== 'create' && !$this->rows("SELECT id FROM tbl_posts WHERE id=? AND user_id=? AND deleted_at IS NULL", [$id,$userId])) throw new DomainException('You can change only your own posts.');
        if ($action === 'delete') { $this->db->prepare('UPDATE tbl_posts SET deleted_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=?')->execute([$id,$userId]); return; }
        $content = trim((string)($input['content'] ?? ''));
        if ($content==='' || mb_strlen($content)>3000) throw new InvalidArgumentException('Write a post of up to 3,000 characters.');
        $eventId = filter_var($input['event_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($eventId && !$this->rows('SELECT id FROM tbl_events WHERE id=? AND deleted_at IS NULL', [$eventId])) throw new InvalidArgumentException('Choose a valid event.');
        $imagePath = null;
        $file = $files['image'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5*1024*1024) throw new InvalidArgumentException('Choose an image no larger than 5 MB.');
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
            if (!$ext || !getimagesize($file['tmp_name'])) throw new InvalidArgumentException('Use a valid JPG, PNG, or WebP image.');
            $dir = dirname(__DIR__).'/assets/uploads/posts';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $imagePath = 'assets/uploads/posts/'.bin2hex(random_bytes(16)).'.'.$ext;
            if (!move_uploaded_file($file['tmp_name'], dirname(__DIR__).'/'.$imagePath)) throw new RuntimeException('Image upload failed.');
        }
        if ($action === 'create') $this->db->prepare("INSERT INTO tbl_posts(user_id,event_id,category,content,image_path,status,is_official,created_at,updated_at) VALUES(?,?,'general',?,?,'approved',0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$userId,$eventId,$content,$imagePath]);
        else {
            $sql = 'UPDATE tbl_posts SET content=?,event_id=?,updated_at=CURRENT_TIMESTAMP'.($imagePath ? ',image_path=?' : '').' WHERE id=? AND user_id=?';
            $params = [$content,$eventId]; if ($imagePath) $params[]=$imagePath; array_push($params,$id,$userId);
            $this->db->prepare($sql)->execute($params);
        }
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
        if ($this->rows('SELECT id FROM tbl_users WHERE email=? AND id<>?', [$email,$userId])) throw new InvalidArgumentException('Email is already in use.');
        $path = null; $file=$files['photo'] ?? null;
        if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
            if ($file['error']!==UPLOAD_ERR_OK || $file['size']>5*1024*1024) throw new InvalidArgumentException('Choose a photo no larger than 5 MB.');
            $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
            if (!$ext || !getimagesize($file['tmp_name'])) throw new InvalidArgumentException('Use a valid JPG, PNG, or WebP photo.');
            $dir=dirname(__DIR__).'/assets/uploads/profiles'; if (!is_dir($dir)) mkdir($dir,0775,true);
            $path='assets/uploads/profiles/'.bin2hex(random_bytes(16)).'.'.$ext;
            if (!move_uploaded_file($file['tmp_name'],dirname(__DIR__).'/'.$path)) throw new RuntimeException('Photo upload failed.');
        }
        $sql='UPDATE tbl_users SET first_name=?,middle_name=?,last_name=?,email=?,bio=?,updated_at=CURRENT_TIMESTAMP'.($path?',profile_photo_path=?':'').' WHERE id=?';
        $params=[$first,$middle ?: null,$last,$email,$bio ?: null]; if ($path) $params[]=$path; $params[]=$userId;
        $this->db->prepare($sql)->execute($params);
        $_SESSION['user']['first_name']=$first; $_SESSION['user']['middle_name']=$middle; $_SESSION['user']['last_name']=$last; $_SESSION['user']['full_name']=trim("$first $middle $last"); $_SESSION['user']['email']=$email;
        return $this->profile($userId);
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;
$actor=AuthGuard::requireRole('Faculty'); $repo=new FacultyRepository((new Database())->connection()); $page=(string)($_GET['page'] ?? $_POST['page'] ?? 'students');
try {
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        $data=match($page) {
            'students'=>$repo->students((int)$actor['id']), 'team'=>$repo->team((int)$actor['id']),
            'leaderboard'=>$repo->leaderboard((int)$actor['id'],$_GET), 'attendance'=>$repo->attendance((int)$actor['id']),
            'posts'=>$repo->posts((int)$actor['id']), 'profile'=>$repo->profile((int)$actor['id']), default=>null,
        };
        if ($data===null) JsonResponse::send(['success'=>false,'message'=>'Unknown Faculty page.'],404);
        JsonResponse::send(['success'=>true,'data'=>$data]);
    }
    if ($_SERVER['REQUEST_METHOD']!=='POST') JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
    if ($page==='scan') {
        $input=json_decode(file_get_contents('php://input'),true);
        $data=$repo->scan((int)$actor['id'],is_array($input)?$input:[]);
    }
    elseif ($page==='posts') { $repo->savePost((int)$actor['id'],$_POST,$_FILES); $data=[]; }
    elseif ($page==='profile') $data=$repo->saveProfile((int)$actor['id'],$_POST,$_FILES);
    else JsonResponse::send(['success'=>false,'message'=>'This page is view only.'],405);
    JsonResponse::send(['success'=>true,'data'=>$data]);
} catch (ScanRateLimitException $e) { JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],429); }
catch (DomainException $e) { JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],403); }
catch (InvalidArgumentException $e) { JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],422); }
catch (LogicException $e) { JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],409); }
catch (Throwable $e) { error_log($e->getMessage()); JsonResponse::send(['success'=>false,'message'=>'Faculty request failed.'],500); }
