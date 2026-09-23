<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/AcademicPeriodScope.php';

final class ScoreManagementRepository
{
    private const PAGE_SIZE = 10;

    public function __construct(private readonly PDO $db) {}

    public function index(array $filters): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $timing = trim((string)($filters['timing'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        $perPage = PageSize::from($filters, self::PAGE_SIZE);
        if (mb_strlen($search) > 100) throw new InvalidArgumentException('Search may not exceed 100 characters.');
        if ($timing !== '' && !in_array($timing, ['upcoming', 'ongoing', 'completed'], true)) throw new InvalidArgumentException('Invalid timing filter.');

        $where = ['e.deleted_at IS NULL']; $params = [];
        if ($search !== '') {$where[] = "(e.title LIKE :search_title ESCAPE '\\\\' OR e.location LIKE :search_location ESCAPE '\\\\')"; $term = '%'.addcslashes($search, '%_\\').'%'; $params['search_title'] = $term; $params['search_location'] = $term;}
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
        if ($timing === 'upcoming') {$where[] = 'e.start_at > :now'; $params['now'] = $now;}
        if ($timing === 'ongoing') {$where[] = 'e.start_at <= :now_start AND e.end_at >= :now_end'; $params['now_start'] = $now; $params['now_end'] = $now;}
        if ($timing === 'completed') {$where[] = 'e.end_at < :now'; $params['now'] = $now;}
        $whereSql = ' WHERE '.implode(' AND ', $where);

        $count = $this->db->prepare('SELECT COUNT(*) FROM tbl_events e'.$whereSql); $count->execute($params);
        $total = (int)$count->fetchColumn(); $lastPage = max(1, (int)ceil($total / $perPage)); $page = min($page, $lastPage);
        $sql = "SELECT e.id,e.title,e.location,e.start_at,e.end_at,
            (SELECT COUNT(*) FROM tbl_score_categories c WHERE c.event_id=e.id) score_categories_count,
            (SELECT COUNT(*) FROM tbl_scores s WHERE s.event_id=e.id AND s.score_category_id IS NOT NULL) scores_count,
            (SELECT COUNT(DISTINCT s.team_id) FROM tbl_scores s WHERE s.event_id=e.id AND s.score_category_id IS NOT NULL) scored_teams_count,
            (SELECT COUNT(*) FROM tbl_teams t WHERE t.is_active=1 OR EXISTS(SELECT 1 FROM tbl_scores sx WHERE sx.team_id=t.id AND sx.event_id=e.id)) eligible_teams_count,
            (SELECT COUNT(*) FROM tbl_teams t WHERE (t.is_active=1 OR EXISTS(SELECT 1 FROM tbl_scores sx WHERE sx.team_id=t.id AND sx.event_id=e.id)) AND (SELECT COUNT(DISTINCT st.score_category_id) FROM tbl_scores st WHERE st.event_id=e.id AND st.team_id=t.id)=(SELECT COUNT(*) FROM tbl_score_categories c2 WHERE c2.event_id=e.id) AND EXISTS(SELECT 1 FROM tbl_score_categories c3 WHERE c3.event_id=e.id)) completed_teams_count,
            (SELECT COUNT(*) FROM tbl_score_sheets sh WHERE sh.event_id=e.id) score_sheets_count,
            (SELECT COUNT(*) FROM tbl_score_sheets sh WHERE sh.event_id=e.id AND sh.status='finalized') finalized_score_sheets_count,
            (SELECT COUNT(*) FROM tbl_event_activities ea WHERE ea.event_id=e.id) activity_count,
            (SELECT COUNT(*) FROM tbl_activity_raw_scores ars WHERE ars.event_id=e.id) raw_score_count,
            (SELECT COUNT(DISTINCT ar.activity_id) FROM tbl_activity_score_results ar WHERE ar.event_id=e.id) finalized_activity_count,
            COALESCE((SELECT SUM(s.points) FROM tbl_scores s WHERE s.event_id=e.id),0) scores_sum_points
            FROM tbl_events e $whereSql ORDER BY e.start_at DESC LIMIT :limit OFFSET :offset";
        $statement = $this->db->prepare($sql); foreach ($params as $key => $value) $statement->bindValue(':'.$key, $value);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT); $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT); $statement->execute();
        $events = $statement->fetchAll();
        foreach ($events as &$event) {
            foreach (['id','score_categories_count','scores_count','scored_teams_count','eligible_teams_count','completed_teams_count','score_sheets_count','finalized_score_sheets_count','activity_count','raw_score_count','finalized_activity_count'] as $key) $event[$key] = (int)$event[$key];
            $event['scores_sum_points'] = (float)$event['scores_sum_points'];
            $event['schedule_state'] = $this->scheduleState($event, $now);
            $allowedTeamIds=(new AcademicPeriodScope($this->db))->teamIdsForEvent((int)$event['id'],true);
            $event['eligible_teams_count']=count($allowedTeamIds);
            $event['competitions'] = $this->rows('SELECT ea.id,ea.name,ea.status,(SELECT COUNT(*) FROM tbl_activity_raw_scores ars WHERE ars.event_id=ea.event_id AND ars.activity_id=ea.id) raw_score_count,(SELECT COUNT(*) FROM tbl_activity_score_results ar WHERE ar.event_id=ea.event_id AND ar.activity_id=ea.id) result_count FROM tbl_event_activities ea WHERE ea.event_id=? ORDER BY ea.id', [(int)$event['id']]);
            foreach ($event['competitions'] as &$competition) {foreach(['id','raw_score_count','result_count'] as $key)$competition[$key]=(int)$competition[$key];} unset($competition);
            $teamMarks=$allowedTeamIds?implode(',',array_fill(0,count($allowedTeamIds),'?')):'0';
            $teamProgress = $allowedTeamIds?$this->rows("SELECT t.id,t.name,t.color,COUNT(DISTINCT s.activity_id) scored_categories
                FROM tbl_teams t LEFT JOIN tbl_activity_raw_scores s ON s.team_id=t.id AND s.event_id=?
                WHERE t.id IN ($teamMarks)
                GROUP BY t.id ORDER BY lower(t.name),t.name", array_merge([(int)$event['id']],$allowedTeamIds)):[];
            foreach ($teamProgress as &$team) {
                $team['id'] = (int)$team['id'];
                $team['scored_categories'] = (int)$team['scored_categories'];
                $team['complete'] = $event['activity_count'] > 0 && $team['scored_categories'] >= $event['activity_count'];
            }
            unset($team);
            $event['team_progress'] = $teamProgress;
            $event['completed_teams_count']=count(array_filter($teamProgress,static fn(array$team):bool=>$team['complete']));
            $event['scoring_state'] = $event['activity_count'] === 0
                ? 'not_setup'
                : ($event['finalized_activity_count'] === $event['activity_count']
                    ? 'finalized'
                    : ($event['raw_score_count'] > 0
                        ? ($event['eligible_teams_count'] > 0 && $event['completed_teams_count'] === $event['eligible_teams_count'] ? 'complete' : 'scoring')
                        : 'ready'));
        }
        unset($event);
        $summary = $this->db->query("SELECT
            (SELECT COUNT(*) FROM tbl_events WHERE deleted_at IS NULL) events,
            (SELECT COUNT(DISTINCT event_id) FROM tbl_event_activities) configured,
            (SELECT COUNT(*) FROM tbl_events e WHERE e.deleted_at IS NULL AND EXISTS(SELECT 1 FROM tbl_event_activities ea WHERE ea.event_id=e.id) AND NOT EXISTS(SELECT 1 FROM tbl_activity_raw_scores ars WHERE ars.event_id=e.id)) ready,
            (SELECT COUNT(*) FROM tbl_events e WHERE e.deleted_at IS NULL AND EXISTS(SELECT 1 FROM tbl_event_activities ea WHERE ea.event_id=e.id) AND (SELECT COUNT(*) FROM tbl_event_activities ea WHERE ea.event_id=e.id)=(SELECT COUNT(DISTINCT ar.activity_id) FROM tbl_activity_score_results ar WHERE ar.event_id=e.id)) finalized,
            (SELECT COUNT(*) FROM (SELECT DISTINCT event_id,team_id FROM tbl_activity_score_results) AS scored_pairs) AS results,
            COALESCE((SELECT SUM(overall_points) FROM tbl_activity_score_results),0) points")->fetch();
        foreach (['events','configured','ready','finalized','results'] as $key) $summary[$key] = (int)$summary[$key]; $summary['points'] = (float)$summary['points'];
        return ['events'=>$events,'summary'=>$summary,'pagination'=>['current_page'=>$page,'last_page'=>$lastPage,'per_page'=>$perPage,'total'=>$total,'from'=>$total?($page-1)*$perPage+1:null,'to'=>$total?min($page*$perPage,$total):null]];
    }

    public function show(int $eventId, ?int $activityId = null): array
    {
        $event = $this->event($eventId);
        $activities = $this->rows('SELECT ea.id,ea.event_id,ea.name,a.description,ea.status FROM tbl_event_activities ea INNER JOIN tbl_activities a ON a.id=ea.activity_id WHERE ea.event_id=? ORDER BY ea.status="active" DESC,ea.id', [$eventId]);
        foreach ($activities as &$activity) {$activity['id']=(int)$activity['id'];$activity['event_id']=(int)$activity['event_id'];} unset($activity);
        if ($activityId === null && $activities) $activityId = (int)$activities[0]['id'];
        $selectedActivity = $activityId === null ? null : $this->activity($eventId, $activityId);
        $categories = $selectedActivity === null ? [] : $this->rows('SELECT c.*,COUNT(s.id) scores_count FROM tbl_score_categories c LEFT JOIN tbl_scores s ON s.score_category_id=c.id WHERE c.event_id=? AND c.activity_id=? GROUP BY c.id ORDER BY c.sort_order,c.id', [$eventId,$activityId]);
        foreach ($categories as &$category) {$category['id']=(int)$category['id'];$category['event_id']=(int)$category['event_id'];$category['activity_id']=$category['activity_id']===null?null:(int)$category['activity_id'];$category['max_points']=(float)$category['max_points'];$category['sort_order']=(int)$category['sort_order'];$category['scores_count']=(int)$category['scores_count'];} unset($category);
        $categoryIds = array_column($categories, 'id');
        $allowedTeamIds=(new AcademicPeriodScope($this->db))->teamIdsForEvent($eventId,true);
        $teamMarks=$allowedTeamIds?implode(',',array_fill(0,count($allowedTeamIds),'?')):'0';
        $teams = $allowedTeamIds?$this->rows("SELECT t.id,t.name,t.color,t.is_active,sy.label school_year_label,COALESCE(SUM(CASE WHEN s.event_id=? AND s.score_category_id IN (SELECT c.id FROM tbl_score_categories c WHERE c.event_id=? AND c.activity_id=?) THEN s.points ELSE 0 END),0) event_score_total
            FROM tbl_teams t LEFT JOIN tbl_school_years sy ON sy.id=t.school_year_id LEFT JOIN tbl_scores s ON s.team_id=t.id
            WHERE t.id IN ($teamMarks)
            GROUP BY t.id ORDER BY event_score_total DESC,t.name", array_merge([$eventId,$eventId,$activityId ?? 0],$allowedTeamIds)):[];
        $previous=null;$previousRank=0; foreach($teams as $index=>&$team){$team['id']=(int)$team['id'];$team['is_active']=(bool)$team['is_active'];$team['event_score_total']=(float)$team['event_score_total'];$team['rank']=$previous!==null&&abs($team['event_score_total']-$previous)<0.00001?$previousRank:$index+1;$previous=$team['event_score_total'];$previousRank=$team['rank'];} unset($team);
        $scores = $categoryIds ? $this->rows('SELECT id,event_id,team_id,score_category_id,points FROM tbl_scores WHERE event_id=? AND score_category_id IN ('.implode(',',array_fill(0,count($categoryIds),'?')).')', array_merge([$eventId],$categoryIds)) : [];
        $scoreMap=[]; foreach($scores as $score)$scoreMap[$score['score_category_id'].'-'.$score['team_id']]=(float)$score['points'];
        $sheets=$selectedActivity===null?[]:$this->rows('SELECT id,team_id,status,submitted_by,finalized_at,reopened_by,reopened_at,updated_at FROM tbl_score_sheets WHERE event_id=? AND activity_id=?',[$eventId,$activityId]);
        $finalized=count(array_filter($sheets,static fn(array$row):bool=>$row['status']==='finalized'));
        $sheet=$sheets?['id'=>null,'status'=>count($teams)>0&&$finalized===count($teams)?'finalized':'draft','submitted_by'=>null,'finalized_at'=>$finalized===count($teams)?max(array_column($sheets,'finalized_at')):null,'reopened_by'=>null,'reopened_at'=>null,'updated_at'=>max(array_column($sheets,'updated_at'))]:null;
        if($sheet){$sheet['id']=(int)$sheet['id'];$sheet['submitted_by']=$sheet['submitted_by']===null?null:(int)$sheet['submitted_by'];$sheet['reopened_by']=$sheet['reopened_by']===null?null:(int)$sheet['reopened_by'];}
        $possible=count($categories)*count($teams); $entries=count($scores); $total=array_sum(array_column($scores,'points'));
        $options=$this->rows('SELECT id,title,start_at FROM tbl_events WHERE deleted_at IS NULL ORDER BY start_at DESC');foreach($options as &$option)$option['id']=(int)$option['id'];unset($option);
        return ['event'=>$event,'activities'=>$activities,'selected_activity'=>$selectedActivity,'categories'=>$categories,'teams'=>$teams,'scores'=>$scoreMap,'sheet'=>$sheet,'event_options'=>$options,'summary'=>['categories'=>count($categories),'teams'=>count($teams),'entries'=>$entries,'completion'=>$possible?round($entries/$possible*100,1):null,'total_points'=>(float)$total,'maximum'=>(float)array_sum(array_column($categories,'max_points'))]];
    }

    public function createCategory(int $eventId, array $input, int $actorId): array
    {
        $this->db->beginTransaction();try{
            $lock=$this->db->prepare('SELECT id,title FROM tbl_events WHERE id=? AND deleted_at IS NULL FOR UPDATE');$lock->execute([$eventId]);$event=$lock->fetch();if(!$event)throw new InvalidArgumentException('Event not found.');
            $activity=$this->activity($eventId,(int)($input['activity_id']??0));
            $this->assertSheetEditable($eventId,$activity['id']);
            $categoryLocks=$this->db->prepare('SELECT id FROM tbl_score_categories WHERE event_id=? AND activity_id=? FOR UPDATE');$categoryLocks->execute([$eventId,$activity['id']]);$categoryLocks->fetchAll();
            [$name,$maximum]=$this->validateCategory($eventId,$activity['id'],$input);
            $order=(int)$this->scalar('SELECT COALESCE(MAX(sort_order),0)+1 FROM tbl_score_categories WHERE event_id=? AND activity_id=?',[$eventId,$activity['id']]);
            $statement=$this->db->prepare('INSERT INTO tbl_score_categories(event_id,activity_id,name,max_points,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');$statement->execute([$eventId,$activity['id'],$name,$maximum,$order]);$categoryId=(int)$this->db->lastInsertId();
            $this->log($actorId,$eventId,'score_category_created',"A scoring category was added to {$event['title']}.");
            $this->db->commit();return ['id'=>$categoryId,'message'=>'Scoring category added.'];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function updateCategory(int $eventId, int $categoryId, array $input, int $actorId): string
    {
        $this->db->beginTransaction();try{
            $eventLock=$this->db->prepare('SELECT id,title FROM tbl_events WHERE id=? AND deleted_at IS NULL FOR UPDATE');$eventLock->execute([$eventId]);$event=$eventLock->fetch();if(!$event)throw new InvalidArgumentException('Event not found.');
            $activity=$this->activity($eventId,(int)($input['activity_id']??0));
            $this->assertSheetEditable($eventId,$activity['id']);
            $lock=$this->db->prepare('SELECT * FROM tbl_score_categories WHERE id=? AND event_id=? AND activity_id=? FOR UPDATE');$lock->execute([$categoryId,$eventId,$activity['id']]);$category=$lock->fetch();if(!$category)throw new InvalidArgumentException('Scoring category not found.');
            [$name,$maximum]=$this->validateCategory($eventId,$activity['id'],$input,$categoryId);
            $scoreLock=$this->db->prepare('SELECT points FROM tbl_scores WHERE score_category_id=? FOR UPDATE');$scoreLock->execute([$categoryId]);$highest=0.0;foreach($scoreLock->fetchAll(PDO::FETCH_COLUMN) as $points)$highest=max($highest,(float)$points);
            if($maximum<$highest)throw new InvalidArgumentException("Maximum points cannot be lower than the existing score of {$this->format($highest)}.");
            $statement=$this->db->prepare('UPDATE tbl_score_categories SET name=?,max_points=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');$statement->execute([$name,$maximum,$categoryId]);
            $this->log($actorId,$eventId,'score_category_updated',"The $name scoring category was updated for {$event['title']}.");$this->db->commit();return 'Scoring category updated.';
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function deleteCategory(int $eventId, int $categoryId, array $input, int $actorId): string
    {
        $this->db->beginTransaction();try{
            $eventLock=$this->db->prepare('SELECT id,title FROM tbl_events WHERE id=? AND deleted_at IS NULL FOR UPDATE');$eventLock->execute([$eventId]);$event=$eventLock->fetch();if(!$event)throw new InvalidArgumentException('Event not found.');
            $activity=$this->activity($eventId,(int)($input['activity_id']??0));
            $this->assertSheetEditable($eventId,$activity['id']);
            $lock=$this->db->prepare('SELECT * FROM tbl_score_categories WHERE id=? AND event_id=? AND activity_id=? FOR UPDATE');$lock->execute([$categoryId,$eventId,$activity['id']]);$category=$lock->fetch();if(!$category)throw new InvalidArgumentException('Scoring category not found.');
            $this->db->prepare('DELETE FROM tbl_score_categories WHERE id=?')->execute([$categoryId]);
            $this->log($actorId,$eventId,'score_category_deleted',"The {$category['name']} scoring category was removed from {$event['title']}.");$this->db->commit();return "{$category['name']} and its score entries were removed.";
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function saveScores(int $eventId, array $input, int $actorId, bool $finalize = false): array
    {
        $activityId=(int)($input['activity_id']??0);$submitted=$input['scores']??null;if(!is_array($submitted))throw new InvalidArgumentException('Scores are required.');
        $changed=0;$this->db->beginTransaction();try{
            $eventLock=$this->db->prepare('SELECT id,title FROM tbl_events WHERE id=? AND deleted_at IS NULL FOR UPDATE');$eventLock->execute([$eventId]);$event=$eventLock->fetch();if(!$event)throw new InvalidArgumentException('Event not found.');
            $activityLock=$this->db->prepare('SELECT id FROM tbl_event_activities WHERE id=? AND event_id=? FOR UPDATE');$activityLock->execute([$activityId,$eventId]);if(!$activityLock->fetchColumn())throw new InvalidArgumentException('Select a valid activity for this event.');
            $allowed=(new AcademicPeriodScope($this->db))->teamIdsForEvent($eventId,true);
            $sheetStatement=$this->db->prepare('SELECT * FROM tbl_score_sheets WHERE event_id=? AND activity_id=? FOR UPDATE');$sheetStatement->execute([$eventId,$activityId]);$sheets=$sheetStatement->fetchAll();
            $finalizedSheets=array_filter($sheets,static fn(array$row):bool=>$row['status']==='finalized');
            if($finalizedSheets){
                if($finalize&&count($allowed)>0&&count($finalizedSheets)===count($allowed)){$this->db->commit();return ['changed'=>0,'status'=>'finalized','message'=>'Results are already finalized.'];}
                throw new InvalidArgumentException('This score sheet is finalized. Reopen scoring before making changes.');
            }
            $categoryStatement=$this->db->prepare('SELECT id,max_points FROM tbl_score_categories WHERE event_id=? AND activity_id=? FOR UPDATE');$categoryStatement->execute([$eventId,$activityId]);$categories=[];foreach($categoryStatement->fetchAll() as $row)$categories[(int)$row['id']] = (float)$row['max_points'];
            if($allowed){$teamStatement=$this->db->prepare('SELECT id FROM tbl_teams WHERE id IN ('.implode(',',array_fill(0,count($allowed),'?')).') FOR UPDATE');$teamStatement->execute($allowed);$teamStatement->fetchAll();}
            foreach($submitted as $categoryId=>$teamScores){$categoryId=(int)$categoryId;if(!$categoryId||!isset($categories[$categoryId]))throw new InvalidArgumentException("Scores can only be recorded in this event's categories.");if(!is_array($teamScores))throw new InvalidArgumentException('Each scoring category must contain tribe scores.');foreach($teamScores as $teamId=>$points){$teamId=(int)$teamId;if(!$teamId||!in_array($teamId,$allowed,true))throw new InvalidArgumentException('Scores can only be recorded for available tribes.');if($points===''||$points===null)continue;if(!is_numeric($points)||!preg_match('/^\d+(?:\.\d{1,2})?$/',(string)$points)||(float)$points<0||(float)$points>1000000)throw new InvalidArgumentException('Scores must be numbers with up to two decimal places.');if((float)$points>$categories[$categoryId])throw new InvalidArgumentException("The score may not exceed {$this->format($categories[$categoryId])} points.");}}
            $find=$this->db->prepare('SELECT id,points FROM tbl_scores WHERE event_id=? AND team_id=? AND score_category_id=? FOR UPDATE');
            $delete=$this->db->prepare('DELETE FROM tbl_scores WHERE id=?');
            $insert=$this->db->prepare('INSERT INTO tbl_scores(event_id,team_id,score_category_id,points,recorded_by,created_at,updated_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
            $update=$this->db->prepare('UPDATE tbl_scores SET points=?,recorded_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            foreach($submitted as $categoryId=>$teamScores)foreach($teamScores as $teamId=>$points){$find->execute([$eventId,(int)$teamId,(int)$categoryId]);$current=$find->fetch();if($points===''||$points===null){if($current){$delete->execute([$current['id']]);$changed++;}continue;}if(!$current){$insert->execute([$eventId,(int)$teamId,(int)$categoryId,(float)$points,$actorId]);$changed++;}elseif(abs((float)$current['points']-(float)$points)>0.00001){$update->execute([(float)$points,$actorId,$current['id']]);$changed++;}}
            if($finalize){
                if(!$categories||!$allowed)throw new InvalidArgumentException('Configure criteria and eligible tribes before finalizing results.');
                $expected=count($categories)*count($allowed);$placeholders=implode(',',array_fill(0,count($categories),'?'));
                $recorded=(int)$this->scalar("SELECT COUNT(*) FROM tbl_scores WHERE event_id=? AND score_category_id IN ($placeholders) AND team_id IN (".implode(',',array_fill(0,count($allowed),'?')).')',array_merge([$eventId],array_keys($categories),$allowed));
                if($recorded!==$expected)throw new InvalidArgumentException("Complete every score before finalizing ($recorded of $expected entries recorded).");
            }
            $status=$finalize?'finalized':'draft';
            $upsertSheet=$this->db->prepare("INSERT INTO tbl_score_sheets(event_id,activity_id,team_id,sbo_event_assignment_id,status,submitted_by,finalized_at,created_at,updated_at) VALUES(?,?,?,NULL,?,?,IF(?='finalized',CURRENT_TIMESTAMP,NULL),CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE status=VALUES(status),submitted_by=VALUES(submitted_by),finalized_at=IF(VALUES(status)='finalized',CURRENT_TIMESTAMP,NULL),updated_at=CURRENT_TIMESTAMP");
            foreach($allowed as $teamId)$upsertSheet->execute([$eventId,$activityId,$teamId,$status,$actorId,$status]);
            if($changed)$this->log($actorId,$eventId,'scores_updated',"Scores for {$event['title']} were updated ($changed entries changed).");
            if($finalize)$this->log($actorId,$eventId,'scores_finalized',"Results for {$event['title']} were finalized.");
            $this->db->commit();return ['changed'=>$changed,'status'=>$status,'message'=>$finalize?'Results finalized and published to Leaderboard and Reports.':($changed?"Draft saved. $changed entries changed.":'Draft is already up to date.')];
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function reopenScores(int $eventId,array $input,int $actorId): string
    {
        $activityId=(int)($input['activity_id']??0);$this->db->beginTransaction();try{
            $event=$this->event($eventId);$this->activity($eventId,$activityId);
            $statement=$this->db->prepare("UPDATE tbl_score_sheets SET status='draft',reopened_by=?,reopened_at=CURRENT_TIMESTAMP,finalized_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE event_id=? AND activity_id=? AND status='finalized'");$statement->execute([$actorId,$eventId,$activityId]);
            if(!$statement->rowCount())throw new InvalidArgumentException('This score sheet is not finalized.');
            $this->log($actorId,$eventId,'scores_reopened',"Results for {$event['title']} were reopened as a draft.");$this->db->commit();return 'Scoring reopened. Draft scores are now hidden from Leaderboard and Reports.';
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    private function validateCategory(int $eventId,int $activityId,array $input,?int $ignore=null):array{$name=trim((string)($input['name']??''));$maximum=$input['max_points']??null;if($name===''||mb_strlen($name)>80)throw new InvalidArgumentException('Criterion name is required and may not exceed 80 characters.');if(!is_numeric($maximum)||!preg_match('/^\d+(?:\.\d{1,2})?$/',(string)$maximum)||(float)$maximum<=0||(float)$maximum>1000000)throw new InvalidArgumentException('Maximum points must be greater than zero and use up to two decimal places.');$sql='SELECT COUNT(*) FROM tbl_score_categories WHERE event_id=? AND activity_id=? AND lower(name)=lower(?)';$params=[$eventId,$activityId,$name];if($ignore){$sql.=' AND id<>?';$params[]=$ignore;}if((int)$this->scalar($sql,$params))throw new InvalidArgumentException('That criterion name is already used for this activity.');return[$name,(float)$maximum];}
    private function assertSheetEditable(int $eventId,int $activityId):void{$statement=$this->db->prepare("SELECT COUNT(*) FROM tbl_score_sheets WHERE event_id=? AND activity_id=? AND status='finalized' FOR UPDATE");$statement->execute([$eventId,$activityId]);if((int)$statement->fetchColumn()>0)throw new InvalidArgumentException('This score sheet is finalized. Reopen scoring before changing criteria.');}
    private function event(int $id):array{$rows=$this->rows('SELECT id,title,location,start_at,end_at FROM tbl_events WHERE id=? AND deleted_at IS NULL',[$id]);if(!$rows)throw new InvalidArgumentException('Event not found.');$event=$rows[0];$event['id']=(int)$event['id'];$now=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');$event['schedule_state']=$this->scheduleState($event,$now);return $event;}
    private function activity(int $eventId,int $id):array{$rows=$this->rows('SELECT ea.id,ea.event_id,ea.name,a.description,ea.status FROM tbl_event_activities ea INNER JOIN tbl_activities a ON a.id=ea.activity_id WHERE ea.id=? AND ea.event_id=?',[$id,$eventId]);if(!$rows)throw new InvalidArgumentException('Select a valid activity for this event.');$rows[0]['id']=(int)$rows[0]['id'];$rows[0]['event_id']=(int)$rows[0]['event_id'];return$rows[0];}
    private function category(int $eventId,int $id,int $activityId):array{$rows=$this->rows('SELECT * FROM tbl_score_categories WHERE id=? AND event_id=? AND activity_id=?',[$id,$eventId,$activityId]);if(!$rows)throw new InvalidArgumentException('Scoring category not found for this activity.');return$rows[0];}
    private function log(int $actor,int $event,string $action,string $description):void{$this->db->prepare('INSERT INTO tbl_activity_logs(actor_id,event_id,action,description,created_at,updated_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([$actor,$event,$action,$description]);}
    private function rows(string $sql,array $params=[]):array{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchAll();}
    private function column(string $sql,array $params=[]):array{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchAll(PDO::FETCH_COLUMN);}
    private function scalar(string $sql,array $params=[]):mixed{$s=$this->db->prepare($sql);$s->execute($params);return$s->fetchColumn();}
    private function scheduleState(array $event,string $now):string{return$event['start_at']>$now?'upcoming':($event['end_at']<$now?'completed':'ongoing');}
    private function format(float $value):string{return rtrim(rtrim(number_format($value,2,'.',''),'0'),'.');}
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;
$actor=AuthGuard::requireRole('SBO Adviser');$repository=new ScoreManagementRepository((new Database())->connection());
try{
    $method=$_SERVER['REQUEST_METHOD'];$action=(string)($_GET['action']??'index');$eventId=(int)($_GET['event_id']??0);
    if($method==='GET'){JsonResponse::send(['success'=>true,'data'=>$eventId?$repository->show($eventId,isset($_GET['activity_id'])?(int)$_GET['activity_id']:null):$repository->index($_GET)]);}
    if($method!=='POST')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
    if(!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null))JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
    $input=json_decode(file_get_contents('php://input'),true);if(!is_array($input))$input=$_POST;$eventId=(int)($input['event_id']??$eventId);
    if($action==='category-create'){$result=$repository->createCategory($eventId,$input,(int)$actor['id']);JsonResponse::send(['success'=>true]+$result);}
    if($action==='category-update'){JsonResponse::send(['success'=>true,'message'=>$repository->updateCategory($eventId,(int)($input['category_id']??0),$input,(int)$actor['id'])]);}
    if($action==='category-delete'){JsonResponse::send(['success'=>true,'message'=>$repository->deleteCategory($eventId,(int)($input['category_id']??0),$input,(int)$actor['id'])]);}
    if($action==='save'||$action==='finalize'){$result=$repository->saveScores($eventId,$input,(int)$actor['id'],$action==='finalize');JsonResponse::send(['success'=>true]+$result);}
    if($action==='reopen'){JsonResponse::send(['success'=>true,'message'=>$repository->reopenScores($eventId,$input,(int)$actor['id'])]);}
    JsonResponse::send(['success'=>false,'message'=>'Invalid scoring action.'],422);
}catch(InvalidArgumentException $e){JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],422);}catch(Throwable $e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>'Scoring request failed.'],500);}
