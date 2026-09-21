<?php
declare(strict_types=1);
require_once __DIR__.'/db_connect.php';require_once __DIR__.'/ApiSupport.php';require_once __DIR__.'/SboAuthorization.php';
final class SboStudentsRepository{
 private const PER_PAGE=25;
 public function __construct(private readonly PDO $db,private readonly SboAuthorization $auth){}
 private function assignments(int $user):array{$all=[];foreach(['attendance','scoring','media'] as $type)foreach($this->auth->assignments($user,$type) as $row)$all[$row['id']]=$row;return array_values($all);}
 public function index(int $user,array $filters):array{
  $assignments=$this->assignments($user);$id=(int)($filters['assignment_id']??0);$selected=null;
  foreach($assignments as $row)if($row['id']===$id)$selected=$row;
  if(!$selected&&$assignments)$selected=$assignments[0];
  if(!$selected)return compact('assignments','selected')+['students'=>[],'pagination'=>$this->pagination(1,0)];

  $search=trim((string)($filters['search']??''));$status=trim((string)($filters['status']??''));$page=max(1,(int)($filters['page']??1));
  if(mb_strlen($search)>100)throw new InvalidArgumentException('Search is too long.');
  if(!in_array($status,['','present','not_recorded'],true))throw new InvalidArgumentException('Invalid attendance filter.');
  $scope=$selected['responsibility']==='attendance'?$selected['scanner_mode']:'specific';
  $from=" FROM tbl_users u
   JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student'
   JOIN tbl_user_statuses us ON us.id=u.status AND us.label='active'
   JOIN tbl_team_user tu ON tu.user_id=u.id
   JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1
   LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level
   LEFT JOIN tbl_attendances ad ON ad.user_id=u.id AND ad.event_id=? AND ad.attendance_date=?
   LEFT JOIN tbl_attendance_entries ae ON ae.attendance_id=ad.id AND ae.event_schedule_id=? AND ae.session_code=? AND ae.activity_id=? AND ae.team_id=tu.team_id AND ae.phase='in'
   WHERE (?='general' OR tu.team_id=?)
    AND EXISTS(SELECT 1 FROM tbl_event_membership_snapshots ms WHERE ms.event_id=? AND ms.user_id=u.id AND ms.team_id=tu.team_id)";
  $params=[(int)$selected['event_id'],$selected['schedule_date'],(int)$selected['event_schedule_id'],$selected['session_code'],(int)$selected['activity_id'],$scope,(int)$selected['scanner_team_id'],(int)$selected['event_id']];
  if($search!==''){
   $from.=" AND (u.id_number LIKE ? ESCAPE '\\\\' OR u.first_name LIKE ? ESCAPE '\\\\' OR u.middle_name LIKE ? ESCAPE '\\\\' OR u.last_name LIKE ? ESCAPE '\\\\' OR CONCAT_WS(' ',u.first_name,NULLIF(u.middle_name,''),u.last_name) LIKE ? ESCAPE '\\\\')";
   $term='%'.addcslashes($search,'%_\\').'%';array_push($params,$term,$term,$term,$term,$term);
  }
  if($status==='present')$from.=' AND ae.id IS NOT NULL';
  if($status==='not_recorded')$from.=' AND ae.id IS NULL';
  $count=$this->db->prepare('SELECT COUNT(DISTINCT u.id)'.$from);$count->execute($params);$total=(int)$count->fetchColumn();
  $lastPage=max(1,(int)ceil($total/self::PER_PAGE));$page=min($page,$lastPage);$offset=($page-1)*self::PER_PAGE;
  $statement=$this->db->prepare("SELECT DISTINCT u.id,u.id_number,u.first_name,u.middle_name,u.last_name,u.email,yl.label year_level,t.name team_name,tu.team_id,
   CASE WHEN ae.id IS NULL THEN 'not_recorded' ELSE ae.status END attendance_status,ae.scanned_at{$from}
   ORDER BY u.last_name,u.first_name,u.id LIMIT ? OFFSET ?");
  $position=1;foreach($params as $value)$statement->bindValue($position++,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);
  $statement->bindValue($position++,self::PER_PAGE,PDO::PARAM_INT);$statement->bindValue($position,$offset,PDO::PARAM_INT);$statement->execute();
  $students=$statement->fetchAll();foreach($students as &$student){$student['id']=(int)$student['id'];$student['team_id']=(int)$student['team_id'];$student['full_name']=trim(implode(' ',array_filter([$student['first_name'],$student['middle_name'],$student['last_name']])));}unset($student);
  return compact('assignments','selected','students')+['pagination'=>$this->pagination($page,$total)];
 }
 private function pagination(int $page,int $total):array{$offset=($page-1)*self::PER_PAGE;return['current_page'=>$page,'last_page'=>max(1,(int)ceil($total/self::PER_PAGE)),'per_page'=>self::PER_PAGE,'total'=>$total,'from'=>$total?$offset+1:null,'to'=>$total?min($offset+self::PER_PAGE,$total):null];}
}
if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))!==__FILE__)return;
$actor=AuthGuard::requireRole('SBO Officer');try{if($_SERVER['REQUEST_METHOD']!=='GET')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);$db=(new Database())->connection();$repo=new SboStudentsRepository($db,new SboAuthorization($db));JsonResponse::send(['success'=>true,'data'=>$repo->index((int)$actor['id'],$_GET)]);}catch(Throwable $e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Unable to load assigned students.'],422);}
