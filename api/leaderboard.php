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
        $categoryId = $this->optionalId($filters['category_id'] ?? null, 'criterion');
        $search = trim((string) ($filters['search'] ?? ''));

        if (mb_strlen($search) > 100) {
            throw new InvalidArgumentException('Search may not exceed 100 characters.');
        }

        $selectedEvent = $eventId ? $this->find('SELECT id,title,start_at,audience_type FROM tbl_events WHERE id=? AND deleted_at IS NULL', [$eventId], 'Event not found.') : null;
        $selectedYear = $schoolYearId ? $this->find('SELECT id,label FROM tbl_school_years WHERE id=?', [$schoolYearId], 'School year not found.') : null;
        $selectedPeriod = $academicPeriodId ? (new AcademicPeriodScope($this->db))->period($academicPeriodId) : null;
        if ($selectedPeriod) $schoolYearId = (int) $selectedPeriod['school_year_id'];
        $selectedCategory = $categoryId ? $this->find('SELECT id,event_id,name,max_points FROM tbl_score_categories WHERE id=?', [$categoryId], 'Scoring criterion not found.') : null;

        if ($selectedCategory && (!$selectedEvent || (int) $selectedCategory['event_id'] !== (int) $selectedEvent['id'])) {
            throw new InvalidArgumentException('Choose a scoring category from the selected event.');
        }

        $rankings = $this->rankings($selectedEvent, $schoolYearId, $categoryId, $academicPeriodId);
        $ranked = array_values(array_filter($rankings, fn (array $team): bool => $team['has_score']));
        $visible = $search === '' ? $ranked : array_values(array_filter(
            $ranked,
            fn (array $team): bool => str_contains(mb_strtolower($team['name']), mb_strtolower($search)),
        ));
        $events = $this->rows("SELECT e.id,e.title,e.start_at FROM tbl_events e WHERE e.deleted_at IS NULL AND EXISTS(SELECT 1 FROM tbl_score_categories c WHERE c.event_id=e.id) ORDER BY e.start_at DESC");
        $years = $this->rows('SELECT id,label FROM tbl_school_years ORDER BY label DESC');
        $categories = $eventId ? $this->rows('SELECT id,name,max_points FROM tbl_score_categories WHERE event_id=? ORDER BY sort_order,id', [$eventId]) : [];

        foreach ($events as &$event) $event['id'] = (int) $event['id'];
        foreach ($years as &$year) $year['id'] = (int) $year['id'];
        foreach ($categories as &$category) {$category['id'] = (int) $category['id']; $category['max_points'] = (float) $category['max_points'];}
        unset($event, $year, $category);

        $leader = $ranked[0] ?? null;
        $lead = count($ranked) > 1 ? (float) max(0, $ranked[0]['total_score'] - $ranked[1]['total_score']) : null;
        $eventCount = [];
        foreach ($ranked as $team) foreach ($team['event_ids'] as $id) $eventCount[$id] = true;

        return [
            'rankings' => $visible,
            'podium' => array_slice($ranked, 0, 3),
            'events' => $events,
            'school_years' => $years,
            'categories' => $categories,
            'selected_event' => $selectedEvent,
            'selected_school_year' => $selectedYear,
            'selected_academic_period' => $selectedPeriod,
            'selected_category' => $selectedCategory,
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

    private function rankings(?array $event, ?int $schoolYearId, ?int $categoryId, ?int $academicPeriodId = null): array
    {
        $scoreConditions = [];
        $scoreParams = [];
        if ($event) {$scoreConditions[] = 's.event_id=?'; $scoreParams[] = (int) $event['id'];}
        if ($academicPeriodId) {$scoreConditions[] = 'EXISTS(SELECT 1 FROM tbl_events period_event WHERE period_event.id=s.event_id AND period_event.academic_period_id=?)'; $scoreParams[] = $academicPeriodId;}
        if ($categoryId) {$scoreConditions[] = 's.score_category_id=?'; $scoreParams[] = $categoryId;}
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
            $where[] = '(t.is_active=1 OR EXISTS(SELECT 1 FROM vw_finalized_scores sx WHERE sx.team_id=t.id))';
        }

        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';
        $memberCount=$event?'(SELECT COUNT(*) FROM tbl_event_membership_snapshots ms WHERE ms.event_id='.(int)$event['id'].' AND ms.team_id=t.id)':'(SELECT COUNT(*) FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name=\'Student\' WHERE tu.team_id=t.id)';
        $sql = "SELECT t.id,t.name,t.color,t.is_active,sy.label school_year_label,
            $memberCount members_count,
            COUNT(s.id) score_entries_count,COUNT(DISTINCT s.event_id) scored_events_count,
            COALESCE(SUM(s.points),0) total_score,MAX(s.updated_at) last_scored_at,
            GROUP_CONCAT(DISTINCT s.event_id) event_ids
            FROM tbl_teams t LEFT JOIN tbl_school_years sy ON sy.id=t.school_year_id
            LEFT JOIN vw_finalized_scores s ON s.team_id=t.id$scoreJoin$whereSql
            GROUP BY t.id ORDER BY CASE WHEN COUNT(s.id)>0 THEN 0 ELSE 1 END, total_score DESC, lower(t.name),t.name";
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
