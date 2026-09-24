<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/ApiSupport.php';
require_once __DIR__ . '/SboAuthorization.php';
require_once __DIR__ . '/AcademicPeriodScope.php';

final class SboScoresRepository
{
    public function __construct(private readonly PDO $db, private readonly SboAuthorization $auth) {}

    public function show(int $userId, int $assignmentId, int $activityId = 0): array
    {
        $allAssignments = $this->auth->assignments($userId, 'scoring', false);
        foreach ($allAssignments as &$assignment) {
            if ($assignment['activity_schedule_id'] === null) $assignment['schedule_date'] = null;
        }
        unset($assignment);
        $selected = null;
        foreach ($allAssignments as $assignment) {
            if ($assignment['id'] === $assignmentId && (!$activityId || $assignment['activity_id'] === $activityId)) {
                $selected = $assignment;
                break;
            }
        }
        if (!$selected && $allAssignments) $selected = $allAssignments[0];
        $assignments = [];
        foreach ($allAssignments as $assignment) {
            $key = $assignment['event_id'].':'.$assignment['activity_id'];
            $assignment['selection_id'] = $assignment['id'].':'.$assignment['activity_id'];
            if (!isset($assignments[$key]) || ($assignment['id'] === $selected['id'] && $assignment['activity_id'] === $selected['activity_id'])) $assignments[$key] = $assignment;
        }
        $assignments = array_values($assignments);
        if (!$selected) return ['assignments' => $assignments, 'selected' => null, 'teams' => [], 'raw_scores' => [], 'finalized' => false];
        $selected['selection_id'] = $selected['id'].':'.$selected['activity_id'];

        $eventId = (int) $selected['event_id'];
        $activityId = (int) $selected['activity_id'];
        $teamIds = (new AcademicPeriodScope($this->db))->teamIdsForEvent($eventId);
        $teams = [];
        if ($teamIds) {
            $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
            $statement = $this->db->prepare("SELECT id,name,color FROM tbl_teams WHERE id IN ($placeholders) ORDER BY name");
            $statement->execute($teamIds);
            $teams = $statement->fetchAll();
            foreach ($teams as &$team) $team['id'] = (int) $team['id'];
            unset($team);
        }
        $rawScores = [];
        $rawStatement = $this->db->prepare('SELECT team_id,raw_score FROM tbl_activity_raw_scores WHERE event_id=? AND activity_id=?');
        $rawStatement->execute([$eventId, $activityId]);
        foreach ($rawStatement as $raw) $rawScores[(int) $raw['team_id']] = (int) $raw['raw_score'];
        $finalized = (bool) $this->value(
            'SELECT COUNT(*) FROM tbl_activity_score_results WHERE event_id=? AND activity_id=?',
            [$eventId, $activityId]
        );

        return [
            'assignments' => $assignments,
            'selected' => $selected,
            'teams' => $teams,
            'raw_scores' => $rawScores,
            'finalized' => $finalized,
        ];
    }

    public function save(int $userId, array $input): string
    {
        $assignmentId = (int) ($input['assignment_id'] ?? 0);
        $activityId = (int) ($input['activity_id'] ?? 0);
        if (!$activityId) {
            $activityId = (int) $this->value('SELECT activity_id FROM tbl_sbo_event_assignments WHERE id=?', [$assignmentId]);
        }
        $assignment = null;
        foreach ($this->auth->assignments($userId, 'scoring', false) as $candidate) {
            if ($candidate['id'] === $assignmentId && $candidate['activity_id'] === $activityId) {
                $assignment = $candidate;
                break;
            }
        }
        if (!$assignment) throw new DomainException('Unauthorized officer assignment.');
        if (($assignment['assignment_state'] ?? '') === 'upcoming') {
            throw new InvalidArgumentException('Scoring is not available until this upcoming event begins.');
        }
        if (($assignment['assignment_state'] ?? '') === 'ended') {
            throw new InvalidArgumentException('Scoring is closed for this ended event.');
        }

        $eventId = (int) $assignment['event_id'];
        $activityId = (int) $assignment['activity_id'];
        $scores = $input['scores'] ?? null;
        if (!is_array($scores)) throw new InvalidArgumentException('Enter a raw score for every eligible tribe.');
        $teamIds = (new AcademicPeriodScope($this->db))->teamIdsForEvent($eventId);
        if (!$teamIds) throw new InvalidArgumentException('There are no eligible tribes for this event.');
        foreach ($teamIds as $teamId) {
            $rawScore = $scores[$teamId] ?? null;
            if (!is_string($rawScore) && !is_int($rawScore) && !is_float($rawScore)) {
                throw new InvalidArgumentException('Enter a raw score for every eligible tribe.');
            }
            if (!preg_match('/^\d+$/', (string) $rawScore) || (int) $rawScore > 200) {
                throw new InvalidArgumentException('Raw scores must be whole numbers from 0 to 200.');
            }
        }
        foreach (array_keys($scores) as $teamId) {
            if (!in_array((int) $teamId, $teamIds, true)) throw new InvalidArgumentException('Scores can only be recorded for eligible tribes.');
        }
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM tbl_events WHERE id=? FOR UPDATE');
            $lock->execute([$eventId]);
            $lock->fetchColumn();
            if ((int) $this->value('SELECT COUNT(*) FROM tbl_activity_score_results WHERE event_id=? AND activity_id=? FOR UPDATE', [$eventId, $activityId])) {
                throw new InvalidArgumentException('This competition is finalized and raw scores can no longer be changed.');
            }

            $upsert = $this->db->prepare('INSERT INTO tbl_activity_raw_scores(event_id,activity_id,team_id,raw_score,recorded_by,created_at,updated_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE raw_score=VALUES(raw_score),recorded_by=VALUES(recorded_by),updated_at=CURRENT_TIMESTAMP');
            foreach ($teamIds as $teamId) $upsert->execute([$eventId, $activityId, $teamId, (int) $scores[$teamId], $userId]);
            $this->db->commit();
            return 'Raw scores saved. The adviser will assign placement points when the competition is finalized.';
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function value(string $query, array $parameters = []): mixed
    {
        $statement = $this->db->prepare($query);
        $statement->execute($parameters);
        return $statement->fetchColumn();
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

$actor = AuthGuard::requireRole('SBO Officer');
$db = (new Database())->connection();
$repository = new SboScoresRepository($db, new SboAuthorization($db));
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        JsonResponse::send(['success' => true, 'data' => $repository->show((int) $actor['id'], (int) ($_GET['assignment_id'] ?? 0), (int) ($_GET['activity_id'] ?? 0))]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) JsonResponse::send(['success' => false, 'message' => 'Your session expired.'], 403);
    $input = json_decode(file_get_contents('php://input'), true);
    JsonResponse::send(['success' => true, 'message' => $repository->save((int) $actor['id'], is_array($input) ? $input : [])]);
} catch (DomainException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 403);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => $exception instanceof InvalidArgumentException ? $exception->getMessage() : 'Unable to save the raw score.'], 422);
}
