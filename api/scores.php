<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/ApiSupport.php';

final class ScoreManagementRepository
{
    private const PAGE_SIZE = 10;

    public function __construct(private readonly PDO $db) {}

    public function index(array $filters): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $timing = trim((string)($filters['timing'] ?? ''));
        $page = max(1, (int)($filters['page'] ?? 1));
        if (mb_strlen($search) > 100) throw new InvalidArgumentException('Search may not exceed 100 characters.');
        if ($timing !== '' && !in_array($timing, ['upcoming', 'ongoing', 'completed'], true)) throw new InvalidArgumentException('Invalid timing filter.');

        $where = ['e.deleted_at IS NULL']; $params = [];
        if ($search !== '') {$where[] = "(e.title LIKE :search ESCAPE '\\' OR e.location LIKE :search ESCAPE '\\')"; $params['search'] = '%'.addcslashes($search, '%_\\').'%';}
        $now = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
        if ($timing === 'upcoming') {$where[] = 'e.start_at > :now'; $params['now'] = $now;}
        if ($timing === 'ongoing') {$where[] = 'e.start_at <= :now AND e.end_at >= :now'; $params['now'] = $now;}
        if ($timing === 'completed') {$where[] = 'e.end_at < :now'; $params['now'] = $now;}
        $whereSql = ' WHERE '.implode(' AND ', $where);

        $count = $this->db->prepare('SELECT COUNT(*) FROM events e'.$whereSql); $count->execute($params);
        $total = (int)$count->fetchColumn(); $lastPage = max(1, (int)ceil($total / self::PAGE_SIZE)); $page = min($page, $lastPage);
        $sql = "SELECT e.id,e.title,e.location,e.start_at,e.end_at,
            (SELECT COUNT(*) FROM score_categories c WHERE c.event_id=e.id) score_categories_count,
            (SELECT COUNT(*) FROM scores s WHERE s.event_id=e.id AND s.score_category_id IS NOT NULL) scores_count,
            (SELECT COUNT(DISTINCT s.team_id) FROM scores s WHERE s.event_id=e.id AND s.score_category_id IS NOT NULL) scored_teams_count,
            COALESCE((SELECT SUM(s.points) FROM scores s WHERE s.event_id=e.id),0) scores_sum_points
            FROM events e $whereSql ORDER BY e.start_at DESC LIMIT :limit OFFSET :offset";
        $statement = $this->db->prepare($sql); foreach ($params as $key => $value) $statement->bindValue(':'.$key, $value);
        $statement->bindValue(':limit', self::PAGE_SIZE, PDO::PARAM_INT); $statement->bindValue(':offset', ($page - 1) * self::PAGE_SIZE, PDO::PARAM_INT); $statement->execute();
        $events = $statement->fetchAll();
        foreach ($events as &$event) {foreach (['id','score_categories_count','scores_count','scored_teams_count'] as $key) $event[$key] = (int)$event[$key]; $event['scores_sum_points'] = (float)$event['scores_sum_points']; $event['schedule_state'] = $this->scheduleState($event, $now);} unset($event);
        $summary = $this->db->query("SELECT
            (SELECT COUNT(*) FROM events WHERE deleted_at IS NULL) events,
            (SELECT COUNT(DISTINCT event_id) FROM score_categories) configured,
            (SELECT COUNT(*) FROM (SELECT DISTINCT event_id,team_id FROM scores WHERE score_category_id IS NOT NULL)) results,
            COALESCE((SELECT SUM(points) FROM scores),0) points")->fetch();
        foreach (['events','configured','results'] as $key) $summary[$key] = (int)$summary[$key]; $summary['points'] = (float)$summary['points'];
        return ['events'=>$events,'summary'=>$summary,'pagination'=>['current_page'=>$page,'last_page'=>$lastPage,'total'=>$total,'from'=>$total?($page-1)*self::PAGE_SIZE+1:null,'to'=>$total?min($page*self::PAGE_SIZE,$total):null]];
    }

    public function show(int $eventId): array
    {
        $event = $this->event($eventId);
        $categories = $this->rows('SELECT c.*,COUNT(s.id) scores_count FROM score_categories c LEFT JOIN scores s ON s.score_category_id=c.id WHERE c.event_id=? GROUP BY c.id ORDER BY c.sort_order,c.id', [$eventId]);
        foreach ($categories as &$category) {$category['id']=(int)$category['id'];$category['event_id']=(int)$category['event_id'];$category['max_points']=(float)$category['max_points'];$category['sort_order']=(int)$category['sort_order'];$category['scores_count']=(int)$category['scores_count'];} unset($category);
        $teams = $this->rows("SELECT t.id,t.name,t.color,t.is_active,sy.label school_year_label,COALESCE(SUM(CASE WHEN s.event_id=? THEN s.points ELSE 0 END),0) event_score_total
            FROM teams t LEFT JOIN school_years sy ON sy.id=t.school_year_id LEFT JOIN scores s ON s.team_id=t.id
            WHERE t.is_active=1 OR EXISTS(SELECT 1 FROM scores sx WHERE sx.team_id=t.id AND sx.event_id=?)
            GROUP BY t.id ORDER BY event_score_total DESC,t.name", [$eventId,$eventId]);
        $previous=null;$previousRank=0; foreach($teams as $index=>&$team){$team['id']=(int)$team['id'];$team['is_active']=(bool)$team['is_active'];$team['event_score_total']=(float)$team['event_score_total'];$team['rank']=$previous!==null&&abs($team['event_score_total']-$previous)<0.00001?$previousRank:$index+1;$previous=$team['event_score_total'];$previousRank=$team['rank'];} unset($team);
        $scores = $this->rows('SELECT id,event_id,team_id,score_category_id,points FROM scores WHERE event_id=? AND score_category_id IS NOT NULL', [$eventId]);
        $scoreMap=[]; foreach($scores as $score)$scoreMap[$score['score_category_id'].'-'.$score['team_id']]=(float)$score['points'];
        $possible=count($categories)*count($teams); $entries=count($scores); $total=array_sum(array_column($scores,'points'));
        $options=$this->rows('SELECT id,title,start_at FROM events WHERE deleted_at IS NULL ORDER BY start_at DESC');foreach($options as &$option)$option['id']=(int)$option['id'];unset($option);
        return ['event'=>$event,'categories'=>$categories,'teams'=>$teams,'scores'=>$scoreMap,'event_options'=>$options,'summary'=>['categories'=>count($categories),'teams'=>count($teams),'entries'=>$entries,'completion'=>$possible?round($entries/$possible*100,1):null,'total_points'=>(float)$total,'maximum'=>(float)array_sum(array_column($categories,'max_points'))]];
    }

    public function createCategory(int $eventId, array $input, int $actorId): array
    {
        $event=$this->event($eventId);[$name,$maximum]=$this->validateCategory($eventId,$input);
        $order=(int)$this->scalar('SELECT COALESCE(MAX(sort_order),0)+1 FROM score_categories WHERE event_id=?',[$eventId]);
        $statement=$this->db->prepare("INSERT INTO score_categories(event_id,name,max_points,sort_order,created_at,updated_at) VALUES(?,?,?,?,datetime('now'),datetime('now'))");$statement->execute([$eventId,$name,$maximum,$order]);
        $this->log($actorId,$eventId,'score_category_created',"A scoring category was added to {$event['title']}.");
        return ['id'=>(int)$this->db->lastInsertId(),'message'=>'Scoring category added.'];
    }

    public function updateCategory(int $eventId, int $categoryId, array $input, int $actorId): string
    {
        $event=$this->event($eventId);$category=$this->category($eventId,$categoryId);[$name,$maximum]=$this->validateCategory($eventId,$input,$categoryId);
        $highest=(float)$this->scalar('SELECT COALESCE(MAX(points),0) FROM scores WHERE score_category_id=?',[$categoryId]);
        if($maximum<$highest)throw new InvalidArgumentException("Maximum points cannot be lower than the existing score of {$this->format($highest)}.");
        $statement=$this->db->prepare("UPDATE score_categories SET name=?,max_points=?,updated_at=datetime('now') WHERE id=?");$statement->execute([$name,$maximum,$categoryId]);
        $this->log($actorId,$eventId,'score_category_updated',"The $name scoring category was updated for {$event['title']}.");return 'Scoring category updated.';
    }

    public function deleteCategory(int $eventId, int $categoryId, int $actorId): string
    {
        $event=$this->event($eventId);$category=$this->category($eventId,$categoryId);$this->db->prepare('DELETE FROM score_categories WHERE id=?')->execute([$categoryId]);
        $this->log($actorId,$eventId,'score_category_deleted',"The {$category['name']} scoring category was removed from {$event['title']}.");return "{$category['name']} and its score entries were removed.";
    }

    public function saveScores(int $eventId, array $input, int $actorId): int
    {
        $event=$this->event($eventId);$submitted=$input['scores']??null;if(!is_array($submitted))throw new InvalidArgumentException('Scores are required.');
        $categoryRows=$this->rows('SELECT id,max_points FROM score_categories WHERE event_id=?',[$eventId]);$categories=[];foreach($categoryRows as $row)$categories[(int)$row['id']] = (float)$row['max_points'];
        $allowed=array_map('intval',$this->column('SELECT id FROM teams WHERE is_active=1 OR EXISTS(SELECT 1 FROM scores WHERE scores.team_id=teams.id AND scores.event_id=?)',[$eventId]));
        foreach($submitted as $categoryId=>$teamScores){$categoryId=(int)$categoryId;if(!$categoryId||!isset($categories[$categoryId]))throw new InvalidArgumentException("Scores can only be recorded in this event's categories.");if(!is_array($teamScores))throw new InvalidArgumentException('Each scoring category must contain tribe scores.');foreach($teamScores as $teamId=>$points){$teamId=(int)$teamId;if(!$teamId||!in_array($teamId,$allowed,true))throw new InvalidArgumentException('Scores can only be recorded for available tribes.');if($points===''||$points===null)continue;if(!is_numeric($points)||!preg_match('/^\d+(?:\.\d{1,2})?$/',(string)$points)||(float)$points<0||(float)$points>1000000)throw new InvalidArgumentException('Scores must be numbers with up to two decimal places.');if((float)$points>$categories[$categoryId])throw new InvalidArgumentException("The score may not exceed {$this->format($categories[$categoryId])} points.");}}
        $changed=0;$this->db->beginTransaction();try{$find=$this->db->prepare('SELECT id,points FROM scores WHERE event_id=? AND team_id=? AND score_category_id=?');$delete=$this->db->prepare('DELETE FROM scores WHERE id=?');$insert=$this->db->prepare("INSERT INTO scores(event_id,team_id,score_category_id,points,recorded_by,created_at,updated_at) VALUES(?,?,?,?,?,datetime('now'),datetime('now'))");$update=$this->db->prepare("UPDATE scores SET points=?,recorded_by=?,updated_at=datetime('now') WHERE id=?");foreach($submitted as $categoryId=>$teamScores)foreach($teamScores as $teamId=>$points){$find->execute([$eventId,(int)$teamId,(int)$categoryId]);$current=$find->fetch();if($points===''||$points===null){if($current){$delete->execute([$current['id']]);$changed++;}continue;}if(!$current){$insert->execute([$eventId,(int)$teamId,(int)$categoryId,(float)$points,$actorId]);$changed++;}elseif(abs((float)$current['points']-(float)$points)>0.00001){$update->execute([(float)$points,$actorId,$current['id']]);$changed++;}}if($changed)$this->log($actorId,$eventId,'scores_updated',"Scores for {$event['title']} were updated ($changed entries changed).");$this->db->commit();return $changed;}catch(Throwable $e){$this->db->rollBack();throw $e;}
    }

    private function validateCategory(int $eventId,array $input,?int $ignore=null):array{$name=trim((string)($input['name']??''));$maximum=$input['max_points']??null;if($name===''||mb_strlen($name)>80)throw new InvalidArgumentException('Criterion name is required and may not exceed 80 characters.');if(!is_numeric($maximum)||!preg_match('/^\d+(?:\.\d{1,2})?$/',(string)$maximum)||(float)$maximum<=0||(float)$maximum>1000000)throw new InvalidArgumentException('Maximum points must be greater than zero and use up to two decimal places.');$sql='SELECT COUNT(*) FROM score_categories WHERE event_id=? AND lower(name)=lower(?)';$params=[$eventId,$name];if($ignore){$sql.=' AND id<>?';$params[]=$ignore;}if((int)$this->scalar($sql,$params))throw new InvalidArgumentException('That criterion name is already used for this event.');return[$name,(float)$maximum];}
    private function event(int $id):array{$rows=$this->rows('SELECT id,title,location,start_at,end_at FROM events WHERE id=? AND deleted_at IS NULL',[$id]);if(!$rows)throw new InvalidArgumentException('Event not found.');$event=$rows[0];$event['id']=(int)$event['id'];$now=(new DateTimeImmutable('now',new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');$event['schedule_state']=$this->scheduleState($event,$now);return $event;}
    private function category(int $eventId,int $id):array{$rows=$this->rows('SELECT * FROM score_categories WHERE id=? AND event_id=?',[$id,$eventId]);if(!$rows)throw new InvalidArgumentException('Scoring category not found.');return$rows[0];}
    private function log(int $actor,int $event,string $action,string $description):void{$this->db->prepare("INSERT INTO activity_logs(actor_id,event_id,action,description,created_at,updated_at) VALUES(?,?,?,?,datetime('now'),datetime('now'))")->execute([$actor,$event,$action,$description]);}
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
    if($method==='GET'){JsonResponse::send(['success'=>true,'data'=>$eventId?$repository->show($eventId):$repository->index($_GET)]);}
    if($method!=='POST')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
    if(!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null))JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
    $input=json_decode(file_get_contents('php://input'),true);if(!is_array($input))$input=$_POST;$eventId=(int)($input['event_id']??$eventId);
    if($action==='category-create'){$result=$repository->createCategory($eventId,$input,(int)$actor['id']);JsonResponse::send(['success'=>true]+$result);}
    if($action==='category-update'){JsonResponse::send(['success'=>true,'message'=>$repository->updateCategory($eventId,(int)($input['category_id']??0),$input,(int)$actor['id'])]);}
    if($action==='category-delete'){JsonResponse::send(['success'=>true,'message'=>$repository->deleteCategory($eventId,(int)($input['category_id']??0),(int)$actor['id'])]);}
    if($action==='save'){$changed=$repository->saveScores($eventId,$input,(int)$actor['id']);JsonResponse::send(['success'=>true,'message'=>$changed?"Scores saved. $changed entries changed.":'Scores are already up to date.','changed'=>$changed]);}
    JsonResponse::send(['success'=>false,'message'=>'Invalid scoring action.'],422);
}catch(InvalidArgumentException $e){JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],422);}catch(Throwable $e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>'Scoring request failed.'],500);}
