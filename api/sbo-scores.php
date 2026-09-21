<?php
declare(strict_types=1);
require_once __DIR__.'/db_connect.php';require_once __DIR__.'/ApiSupport.php';require_once __DIR__.'/SboAuthorization.php';
final class SboScoresRepository{
 public function __construct(private readonly PDO $db,private readonly SboAuthorization $auth){}
 public function show(int $user,int $id):array{$assignments=$this->auth->assignments($user,'scoring');$selected=null;foreach($assignments as $row)if($row['id']===$id)$selected=$row;if(!$selected&&$assignments)$selected=$assignments[0];if(!$selected)return compact('assignments','selected')+['categories'=>[],'teams'=>[],'scores'=>[],'sheet'=>null];
  $c=$this->db->prepare('SELECT id,name,min_points,max_points,sort_order FROM tbl_score_categories WHERE event_id=? AND activity_id=? ORDER BY sort_order,id');$c->execute([$selected['event_id'],$selected['activity_id']]);$categories=$c->fetchAll();foreach($categories as &$v){$v['id']=(int)$v['id'];$v['min_points']=(float)$v['min_points'];$v['max_points']=(float)$v['max_points'];}unset($v);
  $t=$this->db->prepare('SELECT id,name,color FROM tbl_teams WHERE id=? AND is_active=1');$t->execute([$selected['team_id']]);$teams=$t->fetchAll();foreach($teams as &$v)$v['id']=(int)$v['id'];unset($v);
  $s=$this->db->prepare('SELECT team_id,score_category_id,points FROM tbl_scores WHERE event_id=? AND team_id=? AND score_category_id IN(SELECT id FROM tbl_score_categories WHERE activity_id=?)');$s->execute([$selected['event_id'],$selected['team_id'],$selected['activity_id']]);$scores=[];foreach($s as $v)$scores[$v['team_id']][$v['score_category_id']]=(float)$v['points'];$q=$this->db->prepare('SELECT * FROM tbl_score_sheets WHERE event_id=? AND activity_id=? AND team_id=?');$q->execute([$selected['event_id'],$selected['activity_id'],$selected['team_id']]);$sheet=$q->fetch()?:null;return compact('assignments','selected','categories','teams','scores','sheet');}
 public function save(int $user,array $input):string{
 $assignment=$this->auth->assignment($user,(int)($input['assignment_id']??0),'scoring');
  if(($assignment['assignment_state']??'')==='upcoming')throw new InvalidArgumentException('Scoring is not available until this upcoming event begins.');
  $finalize=!empty($input['finalize']);$rows=$input['scores']??null;
  if(!is_array($rows))throw new InvalidArgumentException('Score entries are required.');
  $categories=$this->map('SELECT id,min_points,max_points FROM tbl_score_categories WHERE event_id=? AND activity_id=?',[$assignment['event_id'],$assignment['activity_id']]);
  $teams=[(int)$assignment['team_id']=>['id'=>(int)$assignment['team_id']]];
  if(!$categories)throw new InvalidArgumentException('The adviser has not created scoring criteria for this activity.');
  foreach($rows as $team=>$values){if(!isset($teams[(int)$team])||!is_array($values))throw new InvalidArgumentException('Invalid participating team.');foreach($values as $category=>$points){$category=(int)$category;if(!isset($categories[$category])||!is_numeric($points))throw new InvalidArgumentException('Every submitted score must be numeric.');if((float)$points<(float)$categories[$category]['min_points']||(float)$points>(float)$categories[$category]['max_points'])throw new InvalidArgumentException('A score is outside its allowed minimum or maximum.');}}
  if($finalize)foreach($teams as$team)foreach($categories as$category)if(!isset($rows[(int)$team['id']][(int)$category['id']])||$rows[(int)$team['id']][(int)$category['id']]===''||$rows[(int)$team['id']][(int)$category['id']]===null)throw new InvalidArgumentException('Complete every team and criterion score before finalizing.');
  $this->db->beginTransaction();
  try{
   $eventLock=$this->db->prepare('SELECT id FROM tbl_events WHERE id=? FOR UPDATE');$eventLock->execute([$assignment['event_id']]);$eventLock->fetchColumn();
   $sheet=$this->row('SELECT * FROM tbl_score_sheets WHERE event_id=? AND activity_id=? AND team_id=? FOR UPDATE',[$assignment['event_id'],$assignment['activity_id'],$assignment['team_id']]);
   if($sheet&&$sheet['status']==='finalized'){
    if($finalize&&$this->scoresMatch((int)$assignment['event_id'],(int)$assignment['activity_id'],(int)$assignment['team_id'],$rows)){$this->db->commit();return'Scores were already finalized.';}
    throw new InvalidArgumentException('This score sheet is finalized. Ask the adviser to reopen it.');
   }
   $up=$this->db->prepare('INSERT INTO tbl_scores(event_id,team_id,score_category_id,points,recorded_by,created_at,updated_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE points=VALUES(points),recorded_by=VALUES(recorded_by),updated_at=CURRENT_TIMESTAMP');
   foreach($rows as $team=>$values)foreach($values as $category=>$points)$up->execute([$assignment['event_id'],(int)$team,(int)$category,(float)$points,$user]);
   if(!$sheet){
    $this->db->prepare("INSERT INTO tbl_score_sheets(event_id,activity_id,team_id,event_schedule_id,session_code,sbo_event_assignment_id,status,submitted_by,finalized_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,IF(?='finalized',CURRENT_TIMESTAMP,NULL),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$assignment['event_id'],$assignment['activity_id'],$assignment['team_id'],$assignment['event_schedule_id'],$assignment['session_code'],$assignment['id'],$finalize?'finalized':'draft',$user,$finalize?'finalized':'draft']);
   }else{
    $this->db->prepare("UPDATE tbl_score_sheets SET sbo_event_assignment_id=COALESCE(sbo_event_assignment_id,?),status=?,submitted_by=?,finalized_at=IF(?='finalized',CURRENT_TIMESTAMP,NULL),updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$assignment['id'],$finalize?'finalized':'draft',$user,$finalize?'finalized':'draft',$sheet['id']]);
   }
   $this->db->commit();return$finalize?'Scores finalized successfully.':'Score draft saved.';
  }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}
 }
 private function scoresMatch(int$eventId,int$activityId,int$teamId,array$submitted):bool{
  $q=$this->db->prepare('SELECT team_id,score_category_id,points FROM tbl_scores WHERE event_id=? AND team_id=? AND score_category_id IN(SELECT id FROM tbl_score_categories WHERE event_id=? AND activity_id=?) ORDER BY team_id,score_category_id');
  $q->execute([$eventId,$teamId,$eventId,$activityId]);$saved=[];
  foreach($q as$row)$saved[(int)$row['team_id'].':'.(int)$row['score_category_id']]=round((float)$row['points'],2);
  $incoming=[];foreach($submitted as$team=>$values)foreach($values as$category=>$points)$incoming[(int)$team.':'.(int)$category]=round((float)$points,2);
  ksort($saved);ksort($incoming);return$saved===$incoming;
 }
 private function row(string $q,array$p=[]):array|false{$s=$this->db->prepare($q);$s->execute($p);return$s->fetch();}private function map(string$q,array$p=[]):array{$s=$this->db->prepare($q);$s->execute($p);$out=[];foreach($s as$r)$out[(int)$r['id']]=$r;return$out;}
}
if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))!==__FILE__)return;
$actor=AuthGuard::requireRole('SBO Officer');$db=(new Database())->connection();$repo=new SboScoresRepository($db,new SboAuthorization($db));try{if($_SERVER['REQUEST_METHOD']==='GET')JsonResponse::send(['success'=>true,'data'=>$repo->show((int)$actor['id'],(int)($_GET['assignment_id']??0))]);if($_SERVER['REQUEST_METHOD']!=='POST')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);if(!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null))JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);$in=json_decode(file_get_contents('php://input'),true);JsonResponse::send(['success'=>true,'message'=>$repo->save((int)$actor['id'],is_array($in)?$in:[])]);}catch(DomainException$e){JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],403);}catch(Throwable$e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Unable to save scores.'],422);}
