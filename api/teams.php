<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/ApiSupport.php';

final class TeamManagementRepository
{
    private const PER_PAGE = 9;

    public function __construct(private readonly PDO $db) {}

    public function index(array $filters): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $status = trim((string)($filters['status'] ?? ''));
        $schoolYear = (int)($filters['school_year'] ?? 0);
        $page = max(1, (int)($filters['page'] ?? 1));
        if (mb_strlen($search) > 100) throw new InvalidArgumentException('Search may not exceed 100 characters.');
        if ($status !== '' && !in_array($status, ['active', 'inactive'], true)) throw new InvalidArgumentException('Invalid status filter.');
        if ($schoolYear && !$this->value('SELECT id FROM school_years WHERE id=?', [$schoolYear])) throw new InvalidArgumentException('Invalid school year.');

        $where=[]; $params=[];
        if ($search !== '') {
            $where[]="(t.name LIKE :q ESCAPE '\\' OR EXISTS(SELECT 1 FROM team_user tu2 JOIN users u2 ON u2.id=tu2.user_id WHERE tu2.team_id=t.id AND (u2.first_name LIKE :q ESCAPE '\\' OR u2.last_name LIKE :q ESCAPE '\\')))";
            $params['q']='%'.addcslashes($search, '%_\\').'%';
        }
        if ($status !== '') {$where[]='t.is_active=:active';$params['active']=$status==='active'?1:0;}
        if ($schoolYear) {$where[]='t.school_year_id=:school_year';$params['school_year']=$schoolYear;}
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
        $count=$this->db->prepare('SELECT COUNT(*) FROM teams t'.$whereSql);$count->execute($params);$total=(int)$count->fetchColumn();
        $lastPage=max(1,(int)ceil($total/self::PER_PAGE));
        if($page>$lastPage)$page=$lastPage;
        $sql="SELECT t.id,t.school_year_id,t.name,t.color,t.is_active,sy.label school_year_label,
                (SELECT COUNT(*) FROM team_user tum WHERE tum.team_id=t.id) members_count,
                (SELECT COALESCE(SUM(sc.points),0) FROM scores sc WHERE sc.team_id=t.id) scores_sum_points
              FROM teams t JOIN school_years sy ON sy.id=t.school_year_id
              $whereSql ORDER BY t.is_active DESC,t.name LIMIT :limit OFFSET :offset";
        $st=$this->db->prepare($sql);foreach($params as $k=>$v)$st->bindValue(':'.$k,$v);$st->bindValue(':limit',self::PER_PAGE,PDO::PARAM_INT);$st->bindValue(':offset',($page-1)*self::PER_PAGE,PDO::PARAM_INT);$st->execute();$teams=$st->fetchAll();
        foreach($teams as &$team){$team['id']=(int)$team['id'];$team['school_year_id']=(int)$team['school_year_id'];$team['is_active']=(bool)$team['is_active'];$team['members_count']=(int)$team['members_count'];$team['scores_sum_points']=(float)$team['scores_sum_points'];$m=$this->db->prepare("SELECT u.id,u.first_name,u.middle_name,u.last_name,u.id_number,yl.label year_level_label FROM team_user tu JOIN users u ON u.id=tu.user_id LEFT JOIN year_levels yl ON yl.id=u.year_level WHERE tu.team_id=? ORDER BY u.last_name,u.first_name");$m->execute([$team['id']]);$team['members']=$m->fetchAll();foreach($team['members'] as &$member)$member['full_name']=$this->fullName($member);unset($member);}unset($team);
        $studentRole=(int)$this->value("SELECT id FROM roles WHERE name='Student'");$active=(int)$this->value("SELECT id FROM user_statuses WHERE label='active'");
        $studentsSt=$this->db->prepare("SELECT u.id,u.id_number,u.first_name,u.middle_name,u.last_name,u.year_level,yl.label year_level_label FROM users u LEFT JOIN year_levels yl ON yl.id=u.year_level WHERE u.role_id=? AND u.status=? ORDER BY u.last_name,u.first_name");$studentsSt->execute([$studentRole,$active]);$students=$studentsSt->fetchAll();
        foreach($students as &$student){$student['id']=(int)$student['id'];$student['full_name']=$this->fullName($student);$other=$this->db->prepare('SELECT t.id,t.name,t.school_year_id,sy.label school_year_label FROM team_user tu JOIN teams t ON t.id=tu.team_id JOIN school_years sy ON sy.id=t.school_year_id WHERE tu.user_id=?');$other->execute([$student['id']]);$student['teams']=$other->fetchAll();}unset($student);
        $summary=$this->db->query("SELECT (SELECT COUNT(*) FROM teams) total,(SELECT COUNT(*) FROM teams WHERE is_active=1) active,(SELECT COUNT(*) FROM users WHERE role_id=$studentRole AND status=$active) students,(SELECT COUNT(DISTINCT u.id) FROM users u JOIN team_user tu ON tu.user_id=u.id WHERE u.role_id=$studentRole AND u.status=$active) assigned")->fetch();
        $summary=array_map('intval',$summary);$summary['unassigned']=$summary['students']-$summary['assigned'];
        return ['teams'=>$teams,'students'=>$students,'school_years'=>$this->db->query('SELECT id,label,teams_randomized_at FROM school_years ORDER BY label DESC')->fetchAll(),'summary'=>$summary,'pagination'=>['current_page'=>$page,'last_page'=>$lastPage,'total'=>$total,'from'=>$total?($page-1)*self::PER_PAGE+1:null,'to'=>$total?min($page*self::PER_PAGE,$total):null]];
    }

    public function save(array $data,int $actorId,?int $id=null): int
    {
        $name=trim((string)($data['name']??''));$year=(int)($data['school_year_id']??0);$color=strtoupper(trim((string)($data['color']??'')));
        if($name===''||mb_strlen($name)>100)throw new InvalidArgumentException('Tribe name is required and may not exceed 100 characters.');
        if(!$this->value('SELECT id FROM school_years WHERE id=?',[$year]))throw new InvalidArgumentException('Select a valid school year.');
        if(!preg_match('/^#[0-9A-F]{6}$/',$color))throw new InvalidArgumentException('Choose a valid tribe color.');
        $dupe=$this->db->prepare('SELECT id FROM teams WHERE name=? AND school_year_id=?'.($id?' AND id<>?':''));$dupe->execute($id?[$name,$year,$id]:[$name,$year]);if($dupe->fetch())throw new InvalidArgumentException('That tribe name already exists in this school year.');
        $members=array_values(array_unique(array_map('intval',(array)($data['member_ids']??[]))));
        if($members)$this->validateMembers($members,$year,$id);
        $this->db->beginTransaction();try{
            if($id){if(!$this->value('SELECT id FROM teams WHERE id=?',[$id]))throw new InvalidArgumentException('Tribe not found.');$st=$this->db->prepare("UPDATE teams SET name=?,school_year_id=?,color=?,updated_at=datetime('now') WHERE id=?");$st->execute([$name,$year,$color,$id]);$this->db->prepare('DELETE FROM team_user WHERE team_id=?')->execute([$id]);$attach=$this->db->prepare("INSERT INTO team_user(team_id,user_id,created_at,updated_at) VALUES(?,?,datetime('now'),datetime('now'))");foreach($members as $member)$attach->execute([$id,$member]);$action='team_updated';$description="$name was updated with ".count($members).' members.';
            }else{$st=$this->db->prepare("INSERT INTO teams(school_year_id,name,color,is_active,created_at,updated_at) VALUES(?,?,?,1,datetime('now'),datetime('now'))");$st->execute([$year,$name,$color]);$id=(int)$this->db->lastInsertId();$action='team_created';$description="$name was created without assigned members.";}
            $this->log($actorId,$action,$description);$this->db->commit();return $id;
        }catch(Throwable $e){$this->db->rollBack();throw $e;}
    }

    public function toggle(int $id,int $actorId): void
    {$team=$this->team($id);$active=!$team['is_active'];$this->db->prepare("UPDATE teams SET is_active=?,updated_at=datetime('now') WHERE id=?")->execute([$active?1:0,$id]);$this->log($actorId,'team_status_changed',$team['name'].' was '.($active?'activated':'deactivated').'.');}

    public function randomize(int $year,int $actorId): array
    {
        $sy=$this->db->prepare('SELECT * FROM school_years WHERE id=?');$sy->execute([$year]);$sy=$sy->fetch();if(!$sy)throw new InvalidArgumentException('Select a valid school year.');if($sy['teams_randomized_at'])throw new InvalidArgumentException("Students for SY {$sy['label']} have already been randomized and cannot be randomized again.");
        $teams=$this->db->prepare('SELECT id FROM teams WHERE school_year_id=? AND is_active=1 ORDER BY id');$teams->execute([$year]);$teamIds=array_column($teams->fetchAll(),'id');if(count($teamIds)<2)throw new InvalidArgumentException('Create at least two active tribes for this school year before randomizing students.');
        $students=$this->db->query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id JOIN user_statuses s ON s.id=u.status WHERE r.name='Student' AND s.label='active'")->fetchAll(PDO::FETCH_COLUMN);if(!$students)throw new InvalidArgumentException('There are no active students to distribute.');shuffle($students);
        $this->db->beginTransaction();try{$all=$this->db->prepare('DELETE FROM team_user WHERE team_id IN (SELECT id FROM teams WHERE school_year_id=?) AND user_id=?');foreach($students as $student)$all->execute([$year,$student]);$insert=$this->db->prepare("INSERT INTO team_user(team_id,user_id,created_at,updated_at) VALUES(?,?,datetime('now'),datetime('now'))");foreach($students as $i=>$student)$insert->execute([$teamIds[$i%count($teamIds)],$student]);$this->db->prepare("UPDATE school_years SET teams_randomized_at=datetime('now'),updated_at=datetime('now') WHERE id=?")->execute([$year]);$this->log($actorId,'team_members_randomized',count($students).' active students were distributed across '.count($teamIds)." tribes for SY {$sy['label']}.");$this->db->commit();return ['students'=>count($students),'teams'=>count($teamIds),'school_year'=>$sy['label']];}catch(Throwable $e){$this->db->rollBack();throw $e;}
    }

    private function validateMembers(array $ids,int $year,?int $teamId): void
    {$marks=implode(',',array_fill(0,count($ids),'?'));$st=$this->db->prepare("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id JOIN user_statuses s ON s.id=u.status WHERE u.id IN ($marks) AND r.name='Student' AND s.label='active'");$st->execute($ids);if((int)$st->fetchColumn()!==count($ids))throw new InvalidArgumentException('Only active student accounts can be assigned as tribe members.');$sql="SELECT 1 FROM team_user tu JOIN teams t ON t.id=tu.team_id WHERE tu.user_id IN ($marks) AND t.school_year_id=?".($teamId?' AND t.id<>?':'').' LIMIT 1';$st=$this->db->prepare($sql);$st->execute(array_merge($ids,[$year],$teamId?[$teamId]:[]));if($st->fetch())throw new InvalidArgumentException('A student can belong to only one tribe in the same school year.');}
    private function team(int $id): array{$st=$this->db->prepare('SELECT * FROM teams WHERE id=?');$st->execute([$id]);$row=$st->fetch();if(!$row)throw new InvalidArgumentException('Tribe not found.');return $row;}
    private function log(int $actor,string $action,string $description):void{$this->db->prepare("INSERT INTO activity_logs(actor_id,action,description,created_at,updated_at) VALUES(?,?,?,datetime('now'),datetime('now'))")->execute([$actor,$action,$description]);}
    private function value(string $sql,array $p=[]):mixed{$st=$this->db->prepare($sql);$st->execute($p);return $st->fetchColumn();}
    private function fullName(array $p):string{return trim(implode(' ',array_filter([$p['first_name'],$p['middle_name'],$p['last_name']])));}
}

$actor=AuthGuard::requireRole('SBO Adviser');$repo=new TeamManagementRepository((new Database())->connection());
function validationErrors(InvalidArgumentException $exception): array
{
 $message=$exception->getMessage();
 $field=match(true){
  str_contains(strtolower($message),'tribe name')=>'name',
  str_contains(strtolower($message),'school year')=>'school_year_id',
  str_contains(strtolower($message),'tribe color')=>'color',
  str_contains(strtolower($message),'student')=>'member_ids',
  default=>null,
 };
 return $field?[$field=>[$message]]:[];
}
try{
 if($_SERVER['REQUEST_METHOD']==='GET')JsonResponse::send(['success'=>true,'data'=>$repo->index($_GET)]);
 if($_SERVER['REQUEST_METHOD']!=='POST')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
 if(!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null))JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
 $input=json_decode(file_get_contents('php://input'),true);if(!is_array($input))$input=$_POST;$action=$input['action']??'create';
 if($action==='toggle'){$repo->toggle((int)($input['id']??0),(int)$actor['id']);JsonResponse::send(['success'=>true,'message'=>'Tribe status updated.']);}
 if($action==='randomize'){$result=$repo->randomize((int)($input['school_year_id']??0),(int)$actor['id']);JsonResponse::send(['success'=>true,'message'=>"{$result['students']} students were randomly and evenly distributed across {$result['teams']} tribes. This distribution is now locked."]);}
 if(!in_array($action,['create','update'],true))throw new InvalidArgumentException('Unknown team-management action.');$id=$repo->save($input,(int)$actor['id'],$action==='update'?(int)($input['id']??0):null);JsonResponse::send(['success'=>true,'id'=>$id,'message'=>$action==='update'?'Tribe updated successfully.':'Tribe created successfully.']);
}catch(InvalidArgumentException $e){JsonResponse::send(['success'=>false,'message'=>$e->getMessage(),'errors'=>validationErrors($e)],422);}catch(Throwable $e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>'Team management request failed.'],500);}
