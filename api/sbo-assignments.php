<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class SboAssignmentRepository
{
    private const RESPONSIBILITIES = ['attendance', 'scoring', 'media'];
    private const SESSIONS = ['whole_day', 'morning', 'afternoon'];

    public function __construct(private readonly PDO $db) {}

    public function index(): array
    {
        $officers = $this->db->query("SELECT oa.id,oa.officer_user_id,u.username,u.first_name,u.middle_name,u.last_name
          FROM tbl_sbo_officer_assignments oa JOIN tbl_users u ON u.id=oa.officer_user_id
          WHERE oa.status='Active' ORDER BY u.last_name,u.first_name")->fetchAll();
        foreach ($officers as &$officer) {
            $officer['id']=(int)$officer['id']; $officer['officer_user_id']=(int)$officer['officer_user_id'];
            $officer['full_name']=$this->name($officer);
        } unset($officer);

        $events = $this->db->query("SELECT e.id,e.title,e.start_at,e.end_at,s.id schedule_id,s.schedule_date,m.code session_mode,
          s.whole_day_in_time,s.whole_day_out_time,s.morning_in_time,s.morning_out_time,s.afternoon_in_time,s.afternoon_out_time
          FROM tbl_events e JOIN tbl_event_attendance_schedules s ON s.event_id=e.id
          JOIN tbl_attendance_session_modes m ON m.id=s.attendance_session_mode_id
          WHERE e.deleted_at IS NULL AND m.code<>'none' ORDER BY e.start_at DESC,s.schedule_date")->fetchAll();
        foreach ($events as &$event) {$event['id']=(int)$event['id'];$event['schedule_id']=(int)$event['schedule_id'];} unset($event);

        $teams=$this->db->query('SELECT id,name,color FROM tbl_teams WHERE is_active=1 ORDER BY name')->fetchAll();
        foreach($teams as &$team)$team['id']=(int)$team['id'];unset($team);

        $tasks=$this->db->query("SELECT sea.id,sea.officer_assignment_id,sea.event_schedule_id,sea.session_code,sea.responsibility,sea.status,
          e.id event_id,e.title event_name,s.schedule_date,a.name activity_name,t.name team_name,
          u.first_name,u.middle_name,u.last_name,u.username
          FROM tbl_sbo_event_assignments sea
          JOIN tbl_sbo_officer_assignments oa ON oa.id=sea.officer_assignment_id
          JOIN tbl_users u ON u.id=oa.officer_user_id
          JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id
          JOIN tbl_events e ON e.id=s.event_id
          JOIN tbl_event_activities a ON a.id=sea.activity_id
          JOIN tbl_teams t ON t.id=sea.team_id
          WHERE sea.status='active' ORDER BY s.schedule_date DESC,e.title,u.last_name")->fetchAll();
        foreach($tasks as &$task){foreach(['id','officer_assignment_id','event_schedule_id','event_id'] as $key)$task[$key]=(int)$task[$key];$task['officer_name']=$this->name($task);}unset($task);
        return compact('officers','events','teams','tasks');
    }

    public function assign(array $input,int $actorId): int
    {
        $officer=(int)($input['officer_assignment_id']??0);$schedule=(int)($input['event_schedule_id']??0);
        $session=trim((string)($input['session_code']??''));$activityName=trim((string)($input['activity_name']??''));
        $team=(int)($input['team_id']??0);$responsibility=trim((string)($input['responsibility']??''));
        if($officer<1||$schedule<1||$team<1)throw new InvalidArgumentException('Officer, event day, and team are required.');
        if(!in_array($session,self::SESSIONS,true))throw new InvalidArgumentException('Select a valid session.');
        if(!in_array($responsibility,self::RESPONSIBILITIES,true))throw new InvalidArgumentException('Select a valid responsibility.');
        if($activityName===''||mb_strlen($activityName)>120)throw new InvalidArgumentException('Activity name is required and may not exceed 120 characters.');
        if(!$this->scalar("SELECT COUNT(*) FROM tbl_sbo_officer_assignments WHERE id=? AND status='Active'",[$officer]))throw new InvalidArgumentException('Select an active SBO Officer.');
        $scheduleRow=$this->row("SELECT s.*,m.code session_mode,e.audience_type FROM tbl_event_attendance_schedules s JOIN tbl_attendance_session_modes m ON m.id=s.attendance_session_mode_id JOIN tbl_events e ON e.id=s.event_id AND e.deleted_at IS NULL WHERE s.id=?",[$schedule]);
        if(!$scheduleRow)throw new InvalidArgumentException('The selected event day no longer exists.');
        $validSessions=$scheduleRow['session_mode']==='whole_day'?['whole_day']:($scheduleRow['session_mode']==='two_sessions'?['morning','afternoon']:[]);
        if(!in_array($session,$validSessions,true))throw new InvalidArgumentException('That session is not enabled for this event day.');
        if(!$this->scalar('SELECT COUNT(*) FROM tbl_teams WHERE id=? AND is_active=1',[$team]))throw new InvalidArgumentException('Select an active team.');
        if($scheduleRow['audience_type']==='selected_tribes'&&!$this->scalar('SELECT COUNT(*) FROM tbl_event_team WHERE event_id=? AND team_id=?',[(int)$scheduleRow['event_id'],$team]))throw new InvalidArgumentException('That team is not participating in the selected event.');

        $this->db->beginTransaction();
        try {
            $activity=$this->row('SELECT id FROM tbl_event_activities WHERE event_id=? AND lower(name)=lower(?) LIMIT 1',[(int)$scheduleRow['event_id'],$activityName]);
            if(!$activity){$statement=$this->db->prepare("INSERT INTO tbl_event_activities(event_id,name,status,created_by,created_at,updated_at) VALUES(?,?,'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");$statement->execute([(int)$scheduleRow['event_id'],$activityName,$actorId]);$activityId=(int)$this->db->lastInsertId();}
            else $activityId=(int)$activity['id'];
            if($this->scalar("SELECT COUNT(*) FROM tbl_sbo_event_assignments WHERE officer_assignment_id=? AND event_schedule_id=? AND session_code=? AND activity_id=? AND team_id=? AND responsibility=? AND status='active'",[$officer,$schedule,$session,$activityId,$team,$responsibility]))throw new InvalidArgumentException('This exact officer responsibility is already assigned.');
            $statement=$this->db->prepare("INSERT INTO tbl_sbo_event_assignments(officer_assignment_id,event_schedule_id,session_code,activity_id,team_id,responsibility,status,assigned_by,created_at,updated_at) VALUES(?,?,?,?,?,?,'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
            $statement->execute([$officer,$schedule,$session,$activityId,$team,$responsibility,$actorId]);
            $id=(int)$this->db->lastInsertId();
            $this->db->prepare("INSERT INTO tbl_activity_logs(actor_id,event_id,officer_assignment_id,action,acting_role,description,created_at,updated_at) VALUES(?,?,?,'sbo_event_assigned','SBO Adviser',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$actorId,(int)$scheduleRow['event_id'],$officer,"Assigned $responsibility responsibility for $activityName."]);
            $this->db->commit();return$id;
        } catch(Throwable $exception){if($this->db->inTransaction())$this->db->rollBack();throw$exception;}
    }

    public function end(int $id,int $actorId): void
    {
        $task=$this->row("SELECT sea.*,s.event_id FROM tbl_sbo_event_assignments sea JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id WHERE sea.id=? AND sea.status='active'",[$id]);
        if(!$task)throw new InvalidArgumentException('That responsibility is already inactive.');
        $this->db->prepare("UPDATE tbl_sbo_event_assignments SET status='inactive',ended_by=?,ended_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$actorId,$id]);
        $this->db->prepare("INSERT INTO tbl_activity_logs(actor_id,event_id,officer_assignment_id,action,acting_role,description,created_at,updated_at) VALUES(?,?,?,'sbo_event_unassigned','SBO Adviser',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$actorId,(int)$task['event_id'],(int)$task['officer_assignment_id'],'Ended an SBO event responsibility.']);
    }

    public function addCriterion(array $input): void
    {
        $task=$this->row("SELECT sea.activity_id,s.event_id FROM tbl_sbo_event_assignments sea JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id WHERE sea.id=? AND sea.responsibility='scoring' AND sea.status='active'",[(int)($input['task_id']??0)]);
        if(!$task)throw new InvalidArgumentException('Select an active scoring responsibility.');
        $name=trim((string)($input['name']??''));$minimum=$input['min_points']??null;$maximum=$input['max_points']??null;
        if($name===''||mb_strlen($name)>80)throw new InvalidArgumentException('Criterion name is required and may not exceed 80 characters.');
        if(!is_numeric($minimum)||!is_numeric($maximum)||(float)$minimum<0||(float)$maximum<=(float)$minimum)throw new InvalidArgumentException('Maximum score must be greater than the minimum score.');
        if($this->scalar('SELECT COUNT(*) FROM tbl_score_categories WHERE event_id=? AND activity_id=? AND lower(name)=lower(?)',[(int)$task['event_id'],(int)$task['activity_id'],$name]))throw new InvalidArgumentException('That criterion already exists for this activity.');
        $sort=(int)$this->scalar('SELECT COALESCE(MAX(sort_order),0)+1 FROM tbl_score_categories WHERE event_id=? AND activity_id=?',[(int)$task['event_id'],(int)$task['activity_id']]);
        $this->db->prepare('INSERT INTO tbl_score_categories(event_id,activity_id,name,min_points,max_points,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([(int)$task['event_id'],(int)$task['activity_id'],$name,(float)$minimum,(float)$maximum,$sort]);
    }

    public function reopen(int $taskId,int $actorId): void
    {
        $task=$this->row("SELECT sea.activity_id,s.event_id FROM tbl_sbo_event_assignments sea JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id WHERE sea.id=? AND sea.responsibility='scoring'",[$taskId]);
        if(!$task)throw new InvalidArgumentException('Scoring responsibility not found.');
        $statement=$this->db->prepare("UPDATE tbl_score_sheets SET status='draft',reopened_by=?,reopened_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE event_id=? AND activity_id=? AND status='finalized'");
        $statement->execute([$actorId,(int)$task['event_id'],(int)$task['activity_id']]);
        if(!$statement->rowCount())throw new InvalidArgumentException('This score sheet is not finalized.');
    }

    private function row(string $sql,array $params=[]):array|false{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetch();}
    private function scalar(string $sql,array $params=[]):mixed{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchColumn();}
    private function name(array $row):string{return trim(implode(' ',array_filter([$row['first_name']??null,$row['middle_name']??null,$row['last_name']??null])));}
}

$actor=AuthGuard::requireRole('SBO Adviser');$repository=new SboAssignmentRepository((new Database())->connection());
try{
    if($_SERVER['REQUEST_METHOD']==='GET')JsonResponse::send(['success'=>true,'data'=>$repository->index()]);
    if($_SERVER['REQUEST_METHOD']!=='POST')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
    if(!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null))JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
    $input=json_decode(file_get_contents('php://input'),true);if(!is_array($input))$input=$_POST;$action=(string)($input['action']??'assign');
    if($action==='assign'){$id=$repository->assign($input,(int)$actor['id']);JsonResponse::send(['success'=>true,'message'=>'Event responsibility assigned.','id'=>$id]);}
    if($action==='end'){$repository->end((int)($input['id']??0),(int)$actor['id']);JsonResponse::send(['success'=>true,'message'=>'Event responsibility ended.']);}
    if($action==='criterion'){$repository->addCriterion($input);JsonResponse::send(['success'=>true,'message'=>'Scoring criterion added.']);}
    if($action==='reopen'){$repository->reopen((int)($input['task_id']??0),(int)$actor['id']);JsonResponse::send(['success'=>true,'message'=>'Score sheet reopened for editing.']);}
    JsonResponse::send(['success'=>false,'message'=>'Unknown assignment action.'],422);
}catch(InvalidArgumentException|DomainException $exception){JsonResponse::send(['success'=>false,'message'=>$exception->getMessage()],422);}catch(Throwable $exception){error_log($exception->getMessage());JsonResponse::send(['success'=>false,'message'=>'SBO assignment request failed.'],500);}
