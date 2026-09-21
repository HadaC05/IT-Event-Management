<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class AdviserActivityRepository
{
    public function __construct(private readonly PDO $db) {}

    public function index(array $filters): array
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $eventTypeId = filter_var($filters['event_type_id'] ?? null, FILTER_VALIDATE_INT) ?: 0;
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = PageSize::from($filters, 10);
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = "(a.label LIKE ? ESCAPE '\\\\' OR a.description LIKE ? ESCAPE '\\\\')";
            $escaped = '%'.addcslashes($search, '%_\\').'%';
            $params[] = $escaped;
            $params[] = $escaped;
        }
        if ($eventTypeId > 0) {
            $where[] = 'a.event_type_id=?';
            $params[] = $eventTypeId;
        }
        $whereSql = $where ? ' WHERE '.implode(' AND ', $where) : '';
        $count = $this->db->prepare('SELECT COUNT(*) FROM tbl_activities a'.$whereSql);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $statement = $this->db->prepare(
            "SELECT a.id,a.label,a.description,a.status,a.created_at,a.updated_at,et.id event_type_id,et.label event_type,
                    COUNT(ea.id) event_count
             FROM tbl_activities a
             INNER JOIN tbl_event_types et ON et.id=a.event_type_id
             LEFT JOIN tbl_event_activities ea ON ea.activity_id=a.id
             {$whereSql}
             GROUP BY a.id,a.label,a.description,a.status,a.created_at,a.updated_at,et.id,et.label
             ORDER BY et.label,a.label
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $statement->execute($params);
        $activities = $statement->fetchAll();
        foreach ($activities as &$activity) {
            $activity['id'] = (int) $activity['id'];
            $activity['event_type_id'] = (int) $activity['event_type_id'];
            $activity['event_count'] = (int) $activity['event_count'];
        }
        unset($activity);

        return [
            'activities' => $activities,
            'event_types' => $this->eventTypes(),
            'pagination' => [
                'current_page' => $page, 'last_page' => $lastPage, 'per_page' => $perPage, 'total' => $total,
                'from' => $total ? $offset + 1 : 0, 'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    public function create(array $input): array
    {
        $label = trim((string) ($input['label'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $eventTypeId = (int) ($input['event_type_id'] ?? 0);
        if ($label === '' || mb_strlen($label) > 120) throw new InvalidArgumentException('Activity name is required and may not exceed 120 characters.');
        if (mb_strlen($description) > 2000) throw new InvalidArgumentException('Description may not exceed 2,000 characters.');
        $type = $this->db->prepare('SELECT label FROM tbl_event_types WHERE id=?');
        $type->execute([$eventTypeId]);
        $eventType = $type->fetchColumn();
        if (!$eventType) throw new InvalidArgumentException('Select a valid event type.');
        try {
            $statement = $this->db->prepare("INSERT INTO tbl_activities(label,description,event_type_id,status,created_at,updated_at) VALUES(?,?,?,'active',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
            $statement->execute([$label, $description !== '' ? $description : null, $eventTypeId]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') throw new InvalidArgumentException('An activity with this name already exists for that event type.');
            throw $exception;
        }
        return ['id' => (int) $this->db->lastInsertId(), 'label' => $label, 'description' => $description ?: null, 'event_type_id' => $eventTypeId, 'event_type' => $eventType];
    }

    public function update(array $input): array
    {
        $id = (int) ($input['id'] ?? 0);
        $label = trim((string) ($input['label'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        if ($id < 1) throw new InvalidArgumentException('Select a valid activity to edit.');
        if ($label === '' || mb_strlen($label) > 120) throw new InvalidArgumentException('Activity name is required and may not exceed 120 characters.');
        if (mb_strlen($description) > 2000) throw new InvalidArgumentException('Description may not exceed 2,000 characters.');
        $existing = $this->db->prepare('SELECT id,event_type_id FROM tbl_activities WHERE id=?');
        $existing->execute([$id]);
        $activity = $existing->fetch();
        if (!$activity) throw new InvalidArgumentException('The selected activity was not found.');
        try {
            $statement = $this->db->prepare('UPDATE tbl_activities SET label=?,description=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $statement->execute([$label, $description !== '' ? $description : null, $id]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') throw new InvalidArgumentException('An activity with this name already exists for that event type.');
            throw $exception;
        }
        return ['id' => $id, 'label' => $label, 'description' => $description ?: null];
    }

    private function eventTypes(): array
    {
        $rows = $this->db->query('SELECT id,label FROM tbl_event_types ORDER BY label')->fetchAll();
        foreach ($rows as &$row) $row['id'] = (int) $row['id'];
        unset($row);
        return $rows;
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

$actor = AuthGuard::requireRole('SBO Adviser');
$repository = new AdviserActivityRepository((new Database())->connection());
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') JsonResponse::send(['success' => true, 'data' => $repository->index($_GET)]);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) JsonResponse::send(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 403);
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $action = (string) ($input['action'] ?? 'create');
    if ($action === 'create') {
        $activity = $repository->create($input);
        JsonResponse::send(['success' => true, 'message' => 'Activity created successfully.', 'activity' => $activity], 201);
    }
    if ($action === 'update') {
        $activity = $repository->update($input);
        JsonResponse::send(['success' => true, 'message' => 'Activity details updated.', 'activity' => $activity]);
    }
    throw new InvalidArgumentException('Unknown activity action.');
} catch (InvalidArgumentException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'Activity request failed. Ensure the activity catalog migration has been imported.'], 500);
}
