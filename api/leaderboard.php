<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class LeaderboardRepository
{
    public function __construct(private readonly PDO $db) {}

    public function view(array $filters): array
    {
        $eventId = $this->optionalId($filters['event_id'] ?? null, 'event');
        $schoolYearId = $this->optionalId($filters['school_year_id'] ?? null, 'school year');
        $categoryId = $this->optionalId($filters['category_id'] ?? null, 'criterion');
        $search = trim((string) ($filters['search'] ?? ''));

        if (mb_strlen($search) > 100) {
            throw new InvalidArgumentException('Search may not exceed 100 characters.');
        }

        $selectedEvent = $eventId ? $this->find('SELECT id,title,start_at,audience_type FROM events WHERE id=? AND deleted_at IS NULL', [$eventId], 'Event not found.') : null;
        $selectedYear = $schoolYearId ? $this->find('SELECT id,label FROM school_years WHERE id=?', [$schoolYearId], 'School year not found.') : null;
        $selectedCategory = $categoryId ? $this->find('SELECT id,event_id,name,max_points FROM score_categories WHERE id=?', [$categoryId], 'Scoring criterion not found.') : null;

        if ($selectedCategory && (!$selectedEvent || (int) $selectedCategory['event_id'] !== (int) $selectedEvent['id'])) {
            throw new InvalidArgumentException('Choose a scoring category from the selected event.');
        }

        $rankings = $this->rankings($selectedEvent, $schoolYearId, $categoryId);
        $ranked = array_values(array_filter($rankings, fn (array $team): bool => $team['has_score']));
        $visible = $search === '' ? $rankings : array_values(array_filter(
            $rankings,
            fn (array $team): bool => str_contains(mb_strtolower($team['name']), mb_strtolower($search)),
        ));
        $events = $this->rows("SELECT e.id,e.title,e.start_at FROM events e WHERE e.deleted_at IS NULL AND EXISTS(SELECT 1 FROM score_categories c WHERE c.event_id=e.id) ORDER BY e.start_at DESC");
        $years = $this->rows('SELECT id,label FROM school_years ORDER BY label DESC');
        $categories = $eventId ? $this->rows('SELECT id,name,max_points FROM score_categories WHERE event_id=? ORDER BY sort_order,id', [$eventId]) : [];

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

    private function rankings(?array $event, ?int $schoolYearId, ?int $categoryId): array
    {
        $scoreConditions = [];
        $scoreParams = [];
        if ($event) {$scoreConditions[] = 's.event_id=?'; $scoreParams[] = (int) $event['id'];}
        if ($categoryId) {$scoreConditions[] = 's.score_category_id=?'; $scoreParams[] = $categoryId;}
        $scoreJoin = $scoreConditions ? ' AND '.implode(' AND ', $scoreConditions) : '';

        $where = [];
        $whereParams = [];
        if ($schoolYearId) {$where[] = 't.school_year_id=?'; $whereParams[] = $schoolYearId;}
        if ($event) {
            $audience = '';
            $eventId = (int) $event['id'];
            if ($event['audience_type'] === 'selected_tribes') {
                $audience = ' AND EXISTS(SELECT 1 FROM event_team et WHERE et.event_id=? AND et.team_id=t.id)';
                $whereParams[] = $eventId;
            } elseif ($event['audience_type'] === 'selected_year_levels') {
                $audience = ' AND EXISTS(SELECT 1 FROM team_user tu JOIN users u ON u.id=tu.user_id JOIN event_year_level eyl ON eyl.year_level_id=u.year_level WHERE tu.team_id=t.id AND eyl.event_id=?)';
                $whereParams[] = $eventId;
            } elseif ($event['audience_type'] === 'specific_students') {
                $audience = ' AND EXISTS(SELECT 1 FROM team_user tu JOIN event_participants ep ON ep.user_id=tu.user_id WHERE tu.team_id=t.id AND ep.event_id=?)';
                $whereParams[] = $eventId;
            }
            $where[] = "((t.is_active=1$audience) OR EXISTS(SELECT 1 FROM scores sx WHERE sx.team_id=t.id AND sx.event_id=?))";
            $whereParams[] = $eventId;
        } else {
            $where[] = '(t.is_active=1 OR EXISTS(SELECT 1 FROM scores sx WHERE sx.team_id=t.id))';
        }

        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';
        $sql = "SELECT t.id,t.name,t.color,t.is_active,sy.label school_year_label,
            (SELECT COUNT(*) FROM team_user tu WHERE tu.team_id=t.id) members_count,
            COUNT(s.id) score_entries_count,COUNT(DISTINCT s.event_id) scored_events_count,
            COALESCE(SUM(s.points),0) total_score,MAX(s.updated_at) last_scored_at,
            GROUP_CONCAT(DISTINCT s.event_id) event_ids
            FROM teams t LEFT JOIN school_years sy ON sy.id=t.school_year_id
            LEFT JOIN scores s ON s.team_id=t.id$scoreJoin$whereSql
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
