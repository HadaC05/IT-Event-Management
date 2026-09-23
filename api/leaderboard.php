<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/AcademicPeriodScope.php';

final class LeaderboardRepository
{
    public function __construct(private readonly PDO $db) {}

    public function view(array $filters): array
    {
        $eventId = $this->optionalId($filters['event_id'] ?? null, 'event');
        $schoolYearId = $this->optionalId($filters['school_year_id'] ?? null, 'school year');
        $academicPeriodId = $this->optionalId($filters['academic_period_id'] ?? null, 'academic period');
        $activityId = $this->optionalId($filters['activity_id'] ?? null, 'competition');
        $search = trim((string) ($filters['search'] ?? ''));

        if (mb_strlen($search) > 100) {
            throw new InvalidArgumentException('Search may not exceed 100 characters.');
        }

        $selectedEvent = $eventId ? $this->find('SELECT id,title,start_at,audience_type FROM tbl_events WHERE id=? AND deleted_at IS NULL', [$eventId], 'Event not found.') : null;
        $selectedYear = $schoolYearId ? $this->find('SELECT id,label FROM tbl_school_years WHERE id=?', [$schoolYearId], 'School year not found.') : null;
        $selectedPeriod = $academicPeriodId ? (new AcademicPeriodScope($this->db))->period($academicPeriodId) : null;
        if ($selectedPeriod) $schoolYearId = (int) $selectedPeriod['school_year_id'];
        $selectedActivity = $activityId ? $this->find('SELECT ea.id,ea.event_id,ea.name FROM tbl_event_activities ea WHERE ea.id=?', [$activityId], 'Competition not found.') : null;

        if ($selectedActivity && (!$selectedEvent || (int) $selectedActivity['event_id'] !== (int) $selectedEvent['id'])) {
            throw new InvalidArgumentException('Choose a competition from the selected event.');
        }

        $rankings = $this->rankings($selectedEvent, $schoolYearId, $activityId, $academicPeriodId);
        $competitionStandings = $this->competitionStandings($selectedEvent, $schoolYearId, $activityId, $academicPeriodId, $search);
        $ranked = array_values(array_filter($rankings, fn (array $team): bool => $team['has_score']));
        $visible = $search === '' ? $ranked : array_values(array_filter(
            $ranked,
            fn (array $team): bool => str_contains(mb_strtolower($team['name']), mb_strtolower($search)),
        ));
        $events = $this->rows("SELECT e.id,e.title,e.start_at FROM tbl_events e WHERE e.deleted_at IS NULL AND (EXISTS(SELECT 1 FROM tbl_score_categories c WHERE c.event_id=e.id) OR EXISTS(SELECT 1 FROM tbl_activity_score_results r WHERE r.event_id=e.id)) ORDER BY e.start_at DESC");
        $years = $this->rows('SELECT id,label FROM tbl_school_years ORDER BY label DESC');
        $activities = $eventId ? $this->rows('SELECT id,name FROM tbl_event_activities WHERE event_id=? ORDER BY name,id', [$eventId]) : [];

        foreach ($events as &$event) $event['id'] = (int) $event['id'];
        foreach ($years as &$year) $year['id'] = (int) $year['id'];
        foreach ($activities as &$activity) $activity['id'] = (int) $activity['id'];
        unset($event, $year, $activity);

        $leader = $ranked[0] ?? null;
        $lead = count($ranked) > 1 ? (float) max(0, $ranked[0]['total_score'] - $ranked[1]['total_score']) : null;
        $eventCount = [];
        foreach ($ranked as $team) foreach ($team['event_ids'] as $id) $eventCount[$id] = true;

        return [
            'rankings' => $visible,
            'podium' => array_slice($ranked, 0, 3),
            'events' => $events,
            'school_years' => $years,
            'activities' => $activities,
            'selected_event' => $selectedEvent,
            'selected_school_year' => $selectedYear,
            'selected_academic_period' => $selectedPeriod,
            'selected_activity' => $selectedActivity,
            'competition_standings' => $competitionStandings,
            'summary' => [
                'ranked_teams' => count($ranked),
                'eligible_teams' => count($rankings),
                'points' => (float) array_sum(array_column($ranked, 'total_score')),
                'events' => count($eventCount),
                'leader' => $leader,
                'lead' => $lead,
            ],
        ];
    }

    private function rankings(?array $event, ?int $schoolYearId, ?int $activityId, ?int $academicPeriodId = null): array
    {
        $scoreConditions = [];
        $scoreParams = [];
        if ($event) {$scoreConditions[] = 's.event_id=?'; $scoreParams[] = (int) $event['id'];}
        if ($academicPeriodId) {$scoreConditions[] = 'EXISTS(SELECT 1 FROM tbl_events period_event WHERE period_event.id=s.event_id AND period_event.academic_period_id=?)'; $scoreParams[] = $academicPeriodId;}
        if ($activityId) {$scoreConditions[] = 's.activity_id=?'; $scoreParams[] = $activityId;}
        $scoreJoin = $scoreConditions ? ' AND '.implode(' AND ', $scoreConditions) : '';

        $where = [];
        $whereParams = [];
        if ($schoolYearId) {$where[] = 't.school_year_id=?'; $whereParams[] = $schoolYearId;}
        if ($event) {
            $eventId = (int) $event['id'];
            $allowed=(new AcademicPeriodScope($this->db))->teamIdsForEvent($eventId,true);
            $where[]=$allowed?'t.id IN ('.implode(',',array_fill(0,count($allowed),'?')).')':'1=0';
            array_push($whereParams,...$allowed);
        } else {
            $where[] = '(t.is_active=1 OR EXISTS(SELECT 1 FROM vw_finalized_scores sx WHERE sx.team_id=t.id) OR EXISTS(SELECT 1 FROM tbl_activity_score_results rx WHERE rx.team_id=t.id))';
        }

        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';
        $memberCount=$event?'(SELECT COUNT(*) FROM tbl_event_membership_snapshots ms WHERE ms.event_id='.(int)$event['id'].' AND ms.team_id=t.id)':'(SELECT COUNT(*) FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name=\'Student\' WHERE tu.team_id=t.id)';
        $scoreSource = "SELECT legacy.team_id,legacy.event_id,category.activity_id,legacy.points,legacy.updated_at FROM vw_finalized_scores legacy JOIN tbl_score_categories category ON category.id=legacy.score_category_id
            UNION ALL SELECT result.team_id,result.event_id,result.activity_id,result.overall_points,result.finalized_at FROM tbl_activity_score_results result";
        $sql = "SELECT t.id,t.name,t.color,t.is_active,sy.label school_year_label,
            $memberCount members_count,
            COUNT(s.team_id) score_entries_count,COUNT(DISTINCT s.event_id) scored_events_count,
            COALESCE(SUM(s.points),0) total_score,MAX(s.updated_at) last_scored_at,
            GROUP_CONCAT(DISTINCT s.event_id) event_ids
            FROM tbl_teams t LEFT JOIN tbl_school_years sy ON sy.id=t.school_year_id
            LEFT JOIN ($scoreSource) s ON s.team_id=t.id$scoreJoin$whereSql
            GROUP BY t.id ORDER BY CASE WHEN COUNT(s.team_id)>0 THEN 0 ELSE 1 END, total_score DESC, lower(t.name),t.name";
        $statement = $this->db->prepare($sql);
        $params = array_merge($scoreParams, $whereParams);
        foreach ($params as $index => $value) $statement->bindValue($index + 1, $value, PDO::PARAM_INT);
        $statement->execute();
        $teams = $statement->fetchAll();

        $previousPoints = null;
        $previousRank = null;
        foreach ($teams as $index => &$team) {
            $team['id'] = (int) $team['id'];
            $team['is_active'] = (bool) $team['is_active'];
            $team['members_count'] = (int) $team['members_count'];
            $team['score_entries_count'] = (int) $team['score_entries_count'];
            $team['scored_events_count'] = (int) $team['scored_events_count'];
            $team['total_score'] = (float) $team['total_score'];
            $team['has_score'] = $team['score_entries_count'] > 0;
            $team['event_ids'] = $team['event_ids'] === null ? [] : array_map('intval', explode(',', $team['event_ids']));
            if (!$team['has_score']) {$team['rank'] = null; continue;}
            $team['rank'] = $previousPoints !== null && abs($team['total_score'] - $previousPoints) < 0.00001 ? $previousRank : $index + 1;
            $previousPoints = $team['total_score'];
            $previousRank = $team['rank'];
        }
        unset($team);
        return $teams;
    }

    private function competitionStandings(?array $event, ?int $schoolYearId, ?int $activityId, ?int $academicPeriodId, string $search): array
    {
        $source = "SELECT r.event_id,r.activity_id,r.team_id,r.raw_score,r.overall_points,r.finalized_at,1 finalized FROM tbl_activity_score_results r
            UNION ALL SELECT raw.event_id,raw.activity_id,raw.team_id,raw.raw_score,0 overall_points,NULL finalized_at,0 finalized FROM tbl_activity_raw_scores raw WHERE NOT EXISTS(SELECT 1 FROM tbl_activity_score_results finalized WHERE finalized.event_id=raw.event_id AND finalized.activity_id=raw.activity_id)";
        $where = ['e.deleted_at IS NULL']; $params = [];
        if ($event) {$where[] = 'score.event_id=?'; $params[] = (int)$event['id'];}
        if ($activityId) {$where[] = 'score.activity_id=?'; $params[] = $activityId;}
        if ($schoolYearId) {$where[] = 't.school_year_id=?'; $params[] = $schoolYearId;}
        if ($academicPeriodId) {$where[] = 'e.academic_period_id=?'; $params[] = $academicPeriodId;}
        $sql = "SELECT score.event_id,score.activity_id,e.title event_title,e.start_at,ea.name activity_name,score.team_id,t.name team_name,t.color,score.raw_score,score.overall_points,score.finalized
            FROM ($source) score JOIN tbl_events e ON e.id=score.event_id JOIN tbl_event_activities ea ON ea.id=score.activity_id JOIN tbl_teams t ON t.id=score.team_id
            WHERE ".implode(' AND ', $where)." ORDER BY e.start_at DESC,ea.name,score.raw_score DESC,t.name";
        $activityWhere = ['e.deleted_at IS NULL']; $activityParams = [];
        if ($event) {$activityWhere[] = 'ea.event_id=?'; $activityParams[] = (int)$event['id'];}
        if ($activityId) {$activityWhere[] = 'ea.id=?'; $activityParams[] = $activityId;}
        if ($academicPeriodId) {$activityWhere[] = 'e.academic_period_id=?'; $activityParams[] = $academicPeriodId;}
        $activities = $this->rows('SELECT e.id event_id,ea.id activity_id,e.title event_title,e.start_at,ea.name activity_name FROM tbl_event_activities ea JOIN tbl_events e ON e.id=ea.event_id WHERE '.implode(' AND ', $activityWhere).' ORDER BY e.start_at DESC,ea.name', $activityParams);
        $groups = [];
        foreach ($activities as $activity) {$key=$activity['event_id'].'-'.$activity['activity_id'];$groups[$key]=['event_id'=>(int)$activity['event_id'],'activity_id'=>(int)$activity['activity_id'],'event_title'=>$activity['event_title'],'start_at'=>$activity['start_at'],'activity_name'=>$activity['activity_name'],'finalized'=>false,'rankings'=>[]];}
        $rows = $this->rows($sql, $params);
        foreach ($rows as $row) {
            $key = $row['event_id'].'-'.$row['activity_id'];
            if (!isset($groups[$key])) $groups[$key] = ['event_id'=>(int)$row['event_id'],'activity_id'=>(int)$row['activity_id'],'event_title'=>$row['event_title'],'start_at'=>$row['start_at'],'activity_name'=>$row['activity_name'],'finalized'=>(bool)$row['finalized'],'rankings'=>[]];
            $groups[$key]['finalized'] = (bool)$row['finalized'];
            $groups[$key]['rankings'][] = ['team_id'=>(int)$row['team_id'],'name'=>$row['team_name'],'color'=>$row['color'],'raw_score'=>(float)$row['raw_score'],'overall_points'=>(int)$row['overall_points']];
        }
        foreach ($groups as &$group) {
            $previous = null; $rank = 0;
            foreach ($group['rankings'] as $index => &$team) {if ($previous === null || abs($team['raw_score']-$previous)>0.00001) $rank=$index+1;$team['rank']=$rank;$previous=$team['raw_score'];} unset($team);
            if ($search !== '') $group['rankings'] = array_values(array_filter($group['rankings'], fn(array $team): bool => str_contains(mb_strtolower($team['name']), mb_strtolower($search))));
        }
        unset($group);
        return array_values($groups);
    }

    private function optionalId(mixed $value, string $label): ?int
    {
        if ($value === null || $value === '') return null;
        if (!ctype_digit((string) $value) || (int) $value < 1) throw new InvalidArgumentException("Choose a valid $label.");
        return (int) $value;
    }

    private function find(string $sql, array $params, string $message): array
    {
        $rows = $this->rows($sql, $params);
        if (!$rows) throw new InvalidArgumentException($message);
        $rows[0]['id'] = (int) $rows[0]['id'];
        return $rows[0];
    }

    private function rows(string $sql, array $params = []): array
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

AuthGuard::requireRole('SBO Adviser');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    $data = (new LeaderboardRepository((new Database())->connection()))->view($_GET);
    JsonResponse::send(['success' => true, 'data' => $data]);
} catch (InvalidArgumentException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'Leaderboard request failed.'], 500);
}
