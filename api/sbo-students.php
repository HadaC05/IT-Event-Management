<?php
declare(strict_types=1);
require_once __DIR__.'/db_connect.php';require_once __DIR__.'/ApiSupport.php';require_once __DIR__.'/SboAuthorization.php';
final class SboStudentsRepository{
 public function __construct(private readonly PDO $db,private readonly SboAuthorization $auth){}
 private function assignments(int $user):array{$all=[];foreach(['attendance','scoring','media'] as $type)foreach($this->auth->assignments($user,$type) as $row)$all[$row['id']]=$row;return array_values($all);}
 public function index(int $user,array $filters):array{$assignments=$this->assignments($user);$id=(int)($filters['assignment_id']??0);$selected=null;foreach($assignments as $row)if($row['id']===$id)$selected=$row;if(!$selected&&$assignments)$selected=$assignments[0];if(!$selected)return compact('assignments','selected')+['students'=>[]];
  $search=trim((string)($filters['search']??''));$status=trim((string)($filters['status']??''));if(mb_strlen($search)>100)throw new InvalidArgumentException('Search is too long.');if(!in_array($status,['','present','not_recorded'],true))throw new InvalidArgumentException('Invalid attendance filter.');
  $sql="SELECT DISTINCT u.id,u.id_number,u.first_name,u.middle_name,u.last_name,u.email,yl.label year_level,t.name team_name,
   CASE WHEN ae.id IS NULL THEN 'not_recorded' ELSE ae.status END attendance_status,ae.scanned_at
   FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' JOIN tbl_user_statuses us ON us.id=u.status AND us.label='active'
   JOIN tbl_team_user tu ON tu.user_id=u.id AND tu.team_id=? JOIN tbl_teams t ON t.id=tu.team_id LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level
   LEFT JOIN tbl_attendances ad ON ad.user_id=u.id AND ad.event_id=? AND ad.attendance_date=?
   LEFT JOIN tbl_attendance_entries ae ON ae.attendance_id=ad.id AND ae.event_schedule_id=? AND ae.session_code=? AND ae.activity_id=? AND ae.team_id=? WHERE 1=1";
  $params=[$selected['team_id'],$selected['event_id'],$selected['schedule_date'],$selected['event_schedule_id'],$selected['session_code'],$selected['activity_id'],$selected['team_id']];
  if($search!==''){$sql.=" AND (u.id_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR CONCAT(u.first_name,' ',u.last_name) LIKE ?)";$term="%$search%";array_push($params,$term,$term,$term,$term);}if($status==='present')$sql.=' AND ae.id IS NOT NULL';if($status==='not_recorded')$sql.=' AND ae.id IS NULL';$sql.=' ORDER BY u.last_name,u.first_name';$s=$this->db->prepare($sql);$s->execute($params);$rows=$s->fetchAll();$students=[];foreach($rows as $row)if($this->auth->studentIsEligible($selected,(int)$row['id'])){$row['id']=(int)$row['id'];$row['full_name']=trim(implode(' ',array_filter([$row['first_name'],$row['middle_name'],$row['last_name']])));$students[]=$row;}return compact('assignments','selected','students');}
}
$actor=AuthGuard::requireRole('SBO Officer');try{if($_SERVER['REQUEST_METHOD']!=='GET')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);$db=(new Database())->connection();$repo=new SboStudentsRepository($db,new SboAuthorization($db));JsonResponse::send(['success'=>true,'data'=>$repo->index((int)$actor['id'],$_GET)]);}catch(Throwable $e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Unable to load assigned students.'],422);}
