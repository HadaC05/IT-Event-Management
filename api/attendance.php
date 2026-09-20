<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class AttendanceManagementRepository
{
    private const EVENT_PAGE_SIZE = 6;
    private const ROSTER_PAGE_SIZE = 10;
    private const STATUSES = ['present', 'absent'];

    public function __construct(private readonly PDO $db) {}

    public function index(array $filters): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $timing = trim((string)($filters['timing'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = PageSize::from($filters, self::EVENT_PAGE_SIZE);
        if (mb_strlen($search) > 100) throw new InvalidArgumentException('Search may not exceed 100 characters.');
        if ($timing !== '' && !in_array($timing, ['upcoming', 'ongoing', 'completed'], true)) throw new InvalidArgumentException('Invalid timing filter.');

        $where = ['e.deleted_at IS NULL']; $params = [];
        if ($search !== '') {
            $where[] = "(e.title LIKE :search_title ESCAPE '\\\\' OR e.location LIKE :search_location ESCAPE '\\\\')";
            $term = '%'.addcslashes($search, '%_\\').'%';
            $params['search_title'] = $term;
            $params['search_location'] = $term;
        }
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
        if ($timing === 'upcoming') {$where[]='e.start_at > :now';$params['now']=$now;}
        if ($timing === 'ongoing') {$where[]='e.start_at <= :now AND e.end_at >= :now';$params['now']=$now;}
        if ($timing === 'completed') {$where[]='e.end_at < :now';$params['now']=$now;}
        $whereSql = ' WHERE '.implode(' AND ', $where);

        $count = $this->db->prepare('SELECT COUNT(*) FROM tbl_events e'.$whereSql); $count->execute($params);
        $total = (int)$count->fetchColumn(); $lastPage=max(1,(int)ceil($total/$perPage)); $page=min($page,$lastPage);
        $sql = "SELECT e.id,e.title,e.location,e.start_at,e.end_at,e.audience_type,
            COUNT(a.id) attendances_count,
            SUM(CASE WHEN a.effective_status IN ('present','late') THEN 1 ELSE 0 END) attended_count,
            SUM(CASE WHEN a.effective_status IN ('present','late') THEN 1 ELSE 0 END) present_count,
            SUM(CASE WHEN a.effective_status IN ('absent','excused') THEN 1 ELSE 0 END) absent_count
          FROM tbl_events e LEFT JOIN vw_attendance_effective a ON a.event_id=e.id $whereSql
          GROUP BY e.id ORDER BY e.start_at DESC LIMIT :limit OFFSET :offset";
        $statement=$this->db->prepare($sql); foreach($params as $key=>$value)$statement->bindValue(':'.$key,$value);
        $statement->bindValue(':limit',$perPage,PDO::PARAM_INT);$statement->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);$statement->execute();
        $events=$statement->fetchAll();
        foreach($events as &$event){
            foreach(['id','attendances_count','attended_count','present_count','absent_count'] as $key)$event[$key]=(int)$event[$key];
            $event['expected_count']=count($this->expectedIds($event));
            $event['coverage_rate']=$event['expected_count']?min(100,round($event['attendances_count']/$event['expected_count']*100,1)):null;
            $event['attendance_rate']=$event['attendances_count']?round($event['attended_count']/$event['attendances_count']*100,1):null;
            $event['schedule_state']=$this->scheduleState($event,$now);
        } unset($event);

        $summary=$this->db->query("SELECT
          (SELECT COUNT(*) FROM tbl_events WHERE deleted_at IS NULL) events,
          (SELECT COUNT(*) FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id JOIN tbl_user_statuses us ON us.id=u.status WHERE r.name='Student' AND us.label='active') students,
          (SELECT COUNT(DISTINCT a.event_id) FROM vw_attendance_effective a JOIN tbl_events e ON e.id=a.event_id WHERE e.deleted_at IS NULL) tracked,
          (SELECT COUNT(*) FROM vw_attendance_effective a JOIN tbl_events e ON e.id=a.event_id WHERE e.deleted_at IS NULL) records,
          (SELECT COUNT(*) FROM vw_attendance_effective a JOIN tbl_events e ON e.id=a.event_id WHERE e.deleted_at IS NULL AND a.effective_status IN ('present','late')) attended")->fetch();
        foreach($summary as $key=>$value)$summary[$key]=(int)$value;
        $summary['untracked']=max(0,$summary['events']-$summary['tracked']);
        $summary['attendance_rate']=$summary['records']?round($summary['attended']/$summary['records']*100,1):null;
        return ['events'=>$events,'summary'=>$summary,'pagination'=>['current_page'=>$page,'last_page'=>$lastPage,'per_page'=>$perPage,'total'=>$total,'from'=>$total?($page-1)*$perPage+1:null,'to'=>$total?min($page*$perPage,$total):null]];
    }

    public function roster(int $eventId,array $filters): array
    {
        $event=$this->event($eventId);$search=trim((string)($filters['search']??''));$status=trim((string)($filters['status']??''));$requestedDate=trim((string)($filters['date']??''));$page=max(1,(int)($filters['page']??1));$perPage=PageSize::from($filters,self::ROSTER_PAGE_SIZE);
        if(mb_strlen($search)>100)throw new InvalidArgumentException('Search may not exceed 100 characters.');
        if($status!==''&&!in_array($status,array_merge(self::STATUSES,['unrecorded']),true))throw new InvalidArgumentException('Invalid attendance status filter.');
        if($requestedDate!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$requestedDate))throw new InvalidArgumentException('Choose a valid attendance date.');
        $dates=$this->scheduleDates($event);$date=in_array($requestedDate,$dates,true)?$requestedDate:$dates[0];
        $expectedIds=$this->expectedIds($event);$recorded=$this->column('SELECT user_id FROM tbl_attendances WHERE event_id=? AND attendance_date=?',[$eventId,$date]);
        $participantIds=array_values(array_unique(array_merge($expectedIds,array_map('intval',$recorded))));
        $where=[];$params=[];
        if($participantIds){$where[]='u.id IN ('.implode(',',array_fill(0,count($participantIds),'?')).')';$params=$participantIds;}else{$where[]='0=1';}
        if($search!==''){$where[]="(u.first_name LIKE ? ESCAPE '\\\\' OR u.middle_name LIKE ? ESCAPE '\\\\' OR u.last_name LIKE ? ESCAPE '\\\\' OR u.id_number LIKE ? ESCAPE '\\\\')";$term='%'.addcslashes($search,'%_\\').'%';array_push($params,$term,$term,$term,$term);}
        if($status==='unrecorded'){$where[]='NOT EXISTS(SELECT 1 FROM vw_attendance_effective ax WHERE ax.user_id=u.id AND ax.event_id=? AND ax.attendance_date=?)';array_push($params,$eventId,$date);}
        elseif(in_array($status,self::STATUSES,true)){$where[]="EXISTS(SELECT 1 FROM vw_attendance_effective ax WHERE ax.user_id=u.id AND ax.event_id=? AND ax.attendance_date=? AND ax.effective_status IN (".($status==='present'?"'present','late'":"'absent','excused'")."))";array_push($params,$eventId,$date);}
        $whereSql=' WHERE '.implode(' AND ',$where);$count=$this->db->prepare('SELECT COUNT(*) FROM tbl_users u'.$whereSql);$count->execute($params);$total=(int)$count->fetchColumn();$lastPage=max(1,(int)ceil($total/$perPage));$page=min($page,$lastPage);
        $sql="SELECT u.id,u.id_number,u.first_name,u.middle_name,u.last_name,yl.label year_level_label,
             CASE WHEN a.effective_status='late' THEN 'present' WHEN a.effective_status='excused' THEN 'absent' ELSE a.effective_status END attendance_status,
             a.status evidence_status,a.manual_status,a.manual_corrected_at,a.checked_in_at,
             EXISTS(SELECT 1 FROM tbl_attendance_entries ae WHERE ae.attendance_id=a.id) has_scan_evidence,
             (SELECT GROUP_CONCAT(t.name SEPARATOR ', ') FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id WHERE tu.user_id=u.id) team_names
             FROM tbl_users u LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level
             LEFT JOIN vw_attendance_effective a ON a.user_id=u.id AND a.event_id=? AND a.attendance_date=?
             $whereSql ORDER BY u.last_name,u.first_name LIMIT ? OFFSET ?";
        $statement=$this->db->prepare($sql);$allParams=array_merge([$eventId,$date],$params,[$perPage,($page-1)*$perPage]);
        foreach($allParams as $index=>$value)$statement->bindValue($index+1,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);$statement->execute();$participants=$statement->fetchAll();
        foreach($participants as &$participant){$participant['id']=(int)$participant['id'];$participant['full_name']=$this->fullName($participant);$participant['is_expected']=in_array($participant['id'],$expectedIds,true);$participant['has_scan_evidence']=(bool)$participant['has_scan_evidence'];}unset($participant);
        $counts=array_fill_keys(self::STATUSES,0);$countsStatement=$this->db->prepare('SELECT effective_status,COUNT(*) total FROM vw_attendance_effective WHERE event_id=? AND attendance_date=? GROUP BY effective_status');$countsStatement->execute([$eventId,$date]);foreach($countsStatement as $row){$group=in_array($row['effective_status'],['present','late'],true)?'present':'absent';$counts[$group]+=(int)$row['total'];}
        $recordedCount=array_sum($counts);$attended=$counts['present'];$expected=count($expectedIds);
        $options=$this->db->query('SELECT id,title,start_at FROM tbl_events WHERE deleted_at IS NULL ORDER BY start_at DESC')->fetchAll();foreach($options as &$option)$option['id']=(int)$option['id'];unset($option);
        return ['event'=>$event,'event_options'=>$options,'attendance_dates'=>$dates,'attendance_date'=>$date,'participants'=>$participants,'participant_total'=>count($participantIds),'counts'=>$counts,'summary'=>['expected'=>$expected,'recorded'=>$recordedCount,'unrecorded'=>max(0,$expected-$recordedCount),'completion'=>$expected?min(100,round($recordedCount/$expected*100,1)):null,'attended'=>$attended,'rate'=>$recordedCount?round($attended/$recordedCount*100,1):null],'pagination'=>['current_page'=>$page,'last_page'=>$lastPage,'per_page'=>$perPage,'total'=>$total,'from'=>$total?($page-1)*$perPage+1:null,'to'=>$total?min($page*$perPage,$total):null]];
    }

    public function update(int $eventId,array $input,int $actorId): int
    {
        $date=trim((string)($input['attendance_date']??''));$records=$input['records']??null;
        if(!is_array($records))throw new InvalidArgumentException('Attendance records are required.');
        $changed=0;$this->db->beginTransaction();try{
            $eventLock=$this->db->prepare('SELECT id,title,location,start_at,end_at,audience_type FROM tbl_events WHERE id=? AND deleted_at IS NULL FOR UPDATE');$eventLock->execute([$eventId]);$event=$eventLock->fetch();if(!$event)throw new InvalidArgumentException('Event not found.');$event['id']=(int)$event['id'];
            if(!in_array($date,$this->scheduleDates($event),true))throw new InvalidArgumentException('Choose a valid date from this event schedule.');
            $recordedIds=array_map('intval',$this->column('SELECT user_id FROM tbl_attendances WHERE event_id=?',[$eventId]));
            $allowed=array_values(array_unique(array_merge($this->expectedIds($event),$recordedIds)));
            foreach($records as $userId=>$record){if(!ctype_digit((string)$userId)||!in_array((int)$userId,$allowed,true))throw new InvalidArgumentException("Attendance can only be recorded for this event's expected participants.");if(!is_array($record))throw new InvalidArgumentException('Each attendance record must contain a status.');$value=$record['status']??null;if($value!==null&&$value!==''&&!in_array($value,self::STATUSES,true))throw new InvalidArgumentException('Choose a valid attendance status.');}
            $find=$this->db->prepare('SELECT a.id,a.status,a.manual_status,a.checked_in_at,EXISTS(SELECT 1 FROM tbl_attendance_entries ae WHERE ae.attendance_id=a.id) has_scan_evidence FROM tbl_attendances a WHERE a.event_id=? AND a.user_id=? AND a.attendance_date=? FOR UPDATE');
            $delete=$this->db->prepare('DELETE FROM tbl_attendances WHERE id=?');
            $insert=$this->db->prepare('INSERT INTO tbl_attendances(event_id,user_id,attendance_date,status,manual_status,checked_in_at,recorded_by,manual_corrected_by,manual_corrected_at,manual_reason,created_at,updated_at) VALUES(?,?,?,?,?,NULL,?,?,CURRENT_TIMESTAMP,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
            $override=$this->db->prepare('UPDATE tbl_attendances SET manual_status=?,manual_corrected_by=?,manual_corrected_at=CURRENT_TIMESTAMP,manual_reason=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $clearOverride=$this->db->prepare('UPDATE tbl_attendances SET manual_status=NULL,manual_corrected_by=NULL,manual_corrected_at=NULL,manual_reason=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            foreach($records as $userId=>$record){
                $new=$record['status']??'';$reason=trim((string)($record['reason']??''));if(mb_strlen($reason)>255)throw new InvalidArgumentException('A correction reason may not exceed 255 characters.');
                $find->execute([$eventId,(int)$userId,$date]);$current=$find->fetch();
                if($new===''){
                    if(!$current)continue;
                    if((bool)$current['has_scan_evidence']){if($current['manual_status']!==null){$clearOverride->execute([$current['id']]);$changed++;}}
                    else{$delete->execute([$current['id']]);$changed++;}
                    continue;
                }
                if(!$current){$insert->execute([$eventId,(int)$userId,$date,$new,$new,$actorId,$actorId,$reason?:null]);$changed++;continue;}
                if($current['manual_status']!==$new){$override->execute([$new,$actorId,$reason?:null,$current['id']]);$changed++;}
            }
            if($changed){$log=$this->db->prepare('INSERT INTO tbl_activity_logs(actor_id,event_id,action,description,created_at,updated_at) VALUES(?,?,?, ?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');$log->execute([$actorId,$eventId,'attendance_updated',"Attendance for {$event['title']} was updated ($changed records changed)."]);}$this->db->commit();return $changed;
        }catch(Throwable $exception){if($this->db->inTransaction())$this->db->rollBack();throw $exception;}
    }

    private function event(int $id): array{$statement=$this->db->prepare('SELECT id,title,location,start_at,end_at,audience_type FROM tbl_events WHERE id=? AND deleted_at IS NULL');$statement->execute([$id]);$event=$statement->fetch();if(!$event)throw new InvalidArgumentException('Event not found.');$event['id']=(int)$event['id'];return $event;}
    private function scheduleDates(array $event): array{$dates=array_map('strval',$this->column('SELECT schedule_date FROM tbl_event_attendance_schedules WHERE event_id=? ORDER BY schedule_date',[$event['id']]));return $dates?:[substr((string)$event['start_at'],0,10)];}
    private function expectedIds(array $event): array
    {
        return array_map('intval',$this->column('SELECT user_id FROM tbl_event_membership_snapshots WHERE event_id=?',[(int)$event['id']]));
    }
    private function scheduleState(array $event,string $now):string{return $event['start_at']>$now?'upcoming':($event['end_at']<$now?'completed':'ongoing');}
    private function column(string $sql,array $params=[]):array{$statement=$this->db->prepare($sql);$statement->execute($params);return $statement->fetchAll(PDO::FETCH_COLUMN);}
    private function fullName(array $person):string{return trim(implode(' ',array_filter([$person['first_name'],$person['middle_name'],$person['last_name']])));}
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

$actor=AuthGuard::requireRole('SBO Adviser');$repository=new AttendanceManagementRepository((new Database())->connection());
try{
    if($_SERVER['REQUEST_METHOD']==='GET'){$eventId=(int)($_GET['event_id']??0);JsonResponse::send(['success'=>true,'data'=>$eventId?$repository->roster($eventId,$_GET):$repository->index($_GET)]);}
    if($_SERVER['REQUEST_METHOD']!=='POST')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
    if(!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null))JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
    $input=json_decode(file_get_contents('php://input'),true);if(!is_array($input))$input=$_POST;$changed=$repository->update((int)($input['event_id']??0),$input,(int)$actor['id']);
    JsonResponse::send(['success'=>true,'message'=>$changed?"Attendance saved. $changed records changed.":'Attendance is already up to date.','changed'=>$changed]);
}catch(InvalidArgumentException $exception){JsonResponse::send(['success'=>false,'message'=>$exception->getMessage()],422);}catch(Throwable $exception){error_log($exception->getMessage());JsonResponse::send(['success'=>false,'message'=>'Attendance request failed.'],500);}
