<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/AcademicPeriodScope.php';

final class ActivityScoringRepository
{
    public function __construct(private readonly PDO $db) {}

    public function show(int $eventId, ?int $activityId): array
    {
        $event = $this->one('SELECT id,title,location,start_at FROM tbl_events WHERE id=? AND deleted_at IS NULL', [$eventId], 'Event not found.');
        $activities = $this->rows('SELECT ea.id,ea.name,a.description,ea.status FROM tbl_event_activities ea JOIN tbl_activities a ON a.id=ea.activity_id WHERE ea.event_id=? ORDER BY ea.status="active" DESC,ea.id', [$eventId]);
        foreach ($activities as &$row) $row['id'] = (int) $row['id']; unset($row);
        if ($activityId === null && $activities) $activityId = $activities[0]['id'];
        $selected = $activityId === null ? null : $this->one('SELECT ea.id,ea.name,a.description,ea.status FROM tbl_event_activities ea JOIN tbl_activities a ON a.id=ea.activity_id WHERE ea.id=? AND ea.event_id=?', [$activityId, $eventId], 'Select a valid competition for this event.');
        if ($selected) $selected['id'] = (int) $selected['id'];
        $teamIds = (new AcademicPeriodScope($this->db))->teamIdsForEvent($eventId, true);
        $teams = $teamIds ? $this->rows('SELECT id,name,color,is_active FROM tbl_teams WHERE id IN ('.implode(',', array_fill(0, count($teamIds), '?')).') ORDER BY lower(name),name', $teamIds) : [];
        foreach ($teams as &$team) {$team['id']=(int)$team['id'];$team['is_active']=(bool)$team['is_active'];} unset($team);
        $raw = $selected ? $this->rows('SELECT team_id,raw_score FROM tbl_activity_raw_scores WHERE event_id=? AND activity_id=?', [$eventId, $selected['id']]) : [];
        $rawScores = []; foreach ($raw as $score) $rawScores[(int)$score['team_id']] = (float)$score['raw_score'];
        $placementRules = $selected ? $this->rows('SELECT placement,points FROM tbl_activity_placement_rules WHERE event_id=? AND activity_id=? ORDER BY placement', [$eventId, $selected['id']]) : [];
        foreach ($placementRules as &$rule) {$rule['placement']=(int)$rule['placement'];$rule['points']=(int)$rule['points'];} unset($rule);
        $results = $selected ? $this->rows('SELECT r.team_id,r.raw_score,r.placement,r.overall_points,r.finalized_at,t.name,t.color FROM tbl_activity_score_results r JOIN tbl_teams t ON t.id=r.team_id WHERE r.event_id=? AND r.activity_id=? ORDER BY r.placement,t.name', [$eventId, $selected['id']]) : [];
        foreach ($results as &$result) {$result['team_id']=(int)$result['team_id'];$result['raw_score']=(float)$result['raw_score'];$result['placement']=(int)$result['placement'];$result['overall_points']=(int)$result['overall_points'];} unset($result);
        return ['event'=>$event,'activities'=>$activities,'selected_activity'=>$selected,'teams'=>$teams,'raw_scores'=>$rawScores,'placement_rules'=>$placementRules,'results'=>$results,'finalized'=>count($results)>0];
    }

    public function savePlacementRules(int $eventId, array $input): string
    {
        $activityId=(int)($input['activity_id']??0);$rules=$input['placement_rules']??null;
        if(!is_array($rules))throw new InvalidArgumentException('Add at least one placement rule.');
        $normalized=[];foreach($rules as $placement=>$points){if(!ctype_digit((string)$placement)||(int)$placement<1||(int)$placement>255||!is_numeric($points)||(int)$points<0||(int)$points>255||(string)(int)$points!==(string)$points)throw new InvalidArgumentException('Each placement must be 1–255 and each point value must be a whole number from 0–255.');$normalized[(int)$placement]=(int)$points;}if(!$normalized)throw new InvalidArgumentException('Add at least one placement rule.');ksort($normalized);
        $this->db->beginTransaction();try{$this->assertEventActivity($eventId,$activityId);if((int)$this->scalar('SELECT COUNT(*) FROM tbl_activity_score_results WHERE event_id=? AND activity_id=? FOR UPDATE',[$eventId,$activityId]))throw new InvalidArgumentException('This competition is finalized and its placement rules are locked.');$this->db->prepare('DELETE FROM tbl_activity_placement_rules WHERE event_id=? AND activity_id=?')->execute([$eventId,$activityId]);$insert=$this->db->prepare('INSERT INTO tbl_activity_placement_rules(event_id,activity_id,placement,points,created_at,updated_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');foreach($normalized as $placement=>$points)$insert->execute([$eventId,$activityId,$placement,$points]);$this->db->commit();return 'Placement points saved.';}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function save(int $eventId, array $input, int $actor): string
    {
        $activityId = (int)($input['activity_id'] ?? 0); $scores = $input['scores'] ?? null;
        if (!is_array($scores)) throw new InvalidArgumentException('Raw scores are required.');
        $this->db->beginTransaction();
        try {
            $this->assertEventActivity($eventId, $activityId);
            if ((int)$this->scalar('SELECT COUNT(*) FROM tbl_activity_score_results WHERE event_id=? AND activity_id=? FOR UPDATE', [$eventId,$activityId])) throw new InvalidArgumentException('This competition is finalized and can no longer be edited.');
            $allowed=(new AcademicPeriodScope($this->db))->teamIdsForEvent($eventId,true); $allowedMap=array_flip($allowed);
            $upsert=$this->db->prepare('INSERT INTO tbl_activity_raw_scores(event_id,activity_id,team_id,raw_score,recorded_by,created_at,updated_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE raw_score=VALUES(raw_score),recorded_by=VALUES(recorded_by),updated_at=CURRENT_TIMESTAMP');
            $delete=$this->db->prepare('DELETE FROM tbl_activity_raw_scores WHERE event_id=? AND activity_id=? AND team_id=?'); $changed=0;
            foreach ($scores as $teamId=>$value) { $teamId=(int)$teamId; if (!isset($allowedMap[$teamId])) throw new InvalidArgumentException('Scores can only be recorded for eligible tribes.'); if ($value==='' || $value===null) {$delete->execute([$eventId,$activityId,$teamId]); continue;} if (!is_numeric($value) || !preg_match('/^\d+(?:\.\d{1,2})?$/',(string)$value) || (float)$value>1000000) throw new InvalidArgumentException('Raw scores must be between 0 and 1,000,000 with up to two decimals.'); $upsert->execute([$eventId,$activityId,$teamId,(float)$value,$actor]); $changed++; }
            $this->db->commit(); return $changed ? 'Raw scores saved.' : 'Draft cleared.';
        } catch (Throwable $e) {if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function finalize(int $eventId, array $input, int $actor): string
    {
        $activityId=(int)($input['activity_id']??0); $this->db->beginTransaction();
        try {
            $this->assertEventActivity($eventId,$activityId);
            if ((int)$this->scalar('SELECT COUNT(*) FROM tbl_activity_score_results WHERE event_id=? AND activity_id=? FOR UPDATE',[$eventId,$activityId])) {$this->db->commit();return 'This competition is already finalized.';}
            $allowed=(new AcademicPeriodScope($this->db))->teamIdsForEvent($eventId,true); if(!$allowed)throw new InvalidArgumentException('No eligible tribes are available for this competition.');
            $rows=$this->rows('SELECT team_id,raw_score FROM tbl_activity_raw_scores WHERE event_id=? AND activity_id=? FOR UPDATE',[$eventId,$activityId]); if(count($rows)!==count($allowed))throw new InvalidArgumentException('Enter a raw score for every eligible tribe before finalizing.');
            $scoreMap=[];foreach($rows as $row)$scoreMap[(int)$row['team_id']]=(float)$row['raw_score']; foreach($allowed as $teamId)if(!array_key_exists($teamId,$scoreMap))throw new InvalidArgumentException('Enter a raw score for every eligible tribe before finalizing.');
            $rules=[];foreach($this->rows('SELECT placement,points FROM tbl_activity_placement_rules WHERE event_id=? AND activity_id=? FOR UPDATE',[$eventId,$activityId]) as $rule)$rules[(int)$rule['placement']]=(int)$rule['points'];if(!$rules)throw new InvalidArgumentException('Configure placement points before finalizing this competition.');
            usort($rows,fn($a,$b)=>(float)$b['raw_score'] <=> (float)$a['raw_score'] ?: (int)$a['team_id'] <=> (int)$b['team_id']);
            $insert=$this->db->prepare('INSERT INTO tbl_activity_score_results(event_id,activity_id,team_id,raw_score,placement,overall_points,finalized_by,finalized_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
            $previous=null;$placement=0;foreach($rows as $index=>$row){$raw=(float)$row['raw_score'];if($previous===null||abs($raw-$previous)>0.00001)$placement=$index+1;$points=$rules[$placement]??0;$insert->execute([$eventId,$activityId,(int)$row['team_id'],$raw,$placement,$points,$actor]);$previous=$raw;}
            $this->db->prepare("UPDATE tbl_event_activities SET status=CASE WHEN status='inactive' THEN 'inactive' ELSE 'completed' END,updated_at=CURRENT_TIMESTAMP WHERE id=? AND event_id=?")->execute([$activityId,$eventId]);
            $this->db->commit();return 'Competition finalized. Placement points are now included in the overall tribe standings.';
        } catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function assertEventActivity(int $eventId,int $activityId): void {$this->one('SELECT ea.id FROM tbl_event_activities ea JOIN tbl_events e ON e.id=ea.event_id WHERE ea.id=? AND ea.event_id=? AND e.deleted_at IS NULL FOR UPDATE',[$activityId,$eventId],'Select a valid competition for this event.');}
    private function one(string $sql,array $params,string $message): array {$row=$this->rows($sql,$params)[0]??null;if(!$row)throw new InvalidArgumentException($message);return$row;}
    private function rows(string $sql,array $params=[]):array{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchAll();}
    private function scalar(string $sql,array $params=[]):mixed{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchColumn();}
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

$actor=AuthGuard::requireRole('SBO Adviser');$repo=new ActivityScoringRepository((new Database())->connection());
try {$method=$_SERVER['REQUEST_METHOD'];if($method==='GET')JsonResponse::send(['success'=>true,'data'=>$repo->show((int)($_GET['event_id']??0),isset($_GET['activity_id'])?(int)$_GET['activity_id']:null)]);if($method!=='POST')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);if(!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null))JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);$in=json_decode(file_get_contents('php://input'),true);if(!is_array($in))$in=[];$action=(string)($_GET['action']??'');$event=(int)($in['event_id']??0);if($action==='save')JsonResponse::send(['success'=>true,'message'=>$repo->save($event,$in,(int)$actor['id'])]);if($action==='placement-rules-save')JsonResponse::send(['success'=>true,'message'=>$repo->savePlacementRules($event,$in)]);if($action==='finalize')JsonResponse::send(['success'=>true,'message'=>$repo->finalize($event,$in,(int)$actor['id'])]);JsonResponse::send(['success'=>false,'message'=>'Invalid scoring action.'],422);}catch(InvalidArgumentException $e){JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],422);}catch(Throwable $e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>'Scoring request failed.'],500);}
