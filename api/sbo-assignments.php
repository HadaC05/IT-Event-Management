<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/AcademicPeriodScope.php';

final class SboAssignmentRepository
{
    private const RESPONSIBILITIES = ['attendance', 'scoring', 'media'];

    public function __construct(private readonly PDO $db) {}

    public function index(): array
    {
        $officers = $this->db->query("SELECT oa.id,oa.officer_user_id,u.first_name,u.middle_name,u.last_name
          FROM tbl_sbo_officer_assignments oa
          JOIN tbl_users u ON u.id=oa.officer_user_id
          JOIN tbl_roles r ON r.id=u.role_id AND r.name='SBO Officer'
          JOIN tbl_user_statuses us ON us.id=u.status AND us.label='active'
          WHERE oa.status='Active' ORDER BY u.last_name,u.first_name")->fetchAll();
        foreach ($officers as &$officer) {
            $officer['id'] = (int) $officer['id'];
            $officer['officer_user_id'] = (int) $officer['officer_user_id'];
            $officer['full_name'] = $this->name($officer);
        }
        unset($officer);

        $events = $this->db->query("SELECT e.id,e.title,s.id schedule_id,s.schedule_date
          FROM tbl_events e
          JOIN tbl_event_attendance_schedules s ON s.event_id=e.id
          JOIN tbl_attendance_session_modes m ON m.id=s.attendance_session_mode_id AND m.code='whole_day'
          WHERE e.deleted_at IS NULL ORDER BY s.schedule_date DESC,e.title")->fetchAll();
        foreach ($events as &$event) {
            $event['id'] = (int) $event['id'];
            $event['schedule_id'] = (int) $event['schedule_id'];
        }
        unset($event);

        $tasks = $this->db->query("SELECT sea.id,sea.officer_assignment_id,sea.event_schedule_id,sea.session_code,sea.activity_id,sea.team_id,r.code responsibility,sea.scanner_mode,sea.scanner_team_id,sea.status,
          e.id event_id,e.title event_name,s.schedule_date,a.name activity_name,t.name team_name,scanner_team.name scanner_team_name,
          u.first_name,u.middle_name,u.last_name
          FROM tbl_sbo_event_assignments sea
          JOIN tbl_officer_responsibilities r ON r.id=sea.responsibility_id
          JOIN tbl_sbo_officer_assignments oa ON oa.id=sea.officer_assignment_id AND oa.status='Active'
          JOIN tbl_users u ON u.id=oa.officer_user_id
          JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id
          JOIN tbl_events e ON e.id=s.event_id AND e.deleted_at IS NULL
          JOIN tbl_event_activities a ON a.id=sea.activity_id AND a.status<>'inactive'
          JOIN tbl_teams t ON t.id=sea.team_id AND t.is_active=1
          LEFT JOIN tbl_teams scanner_team ON scanner_team.id=sea.scanner_team_id
          WHERE sea.status='active' ORDER BY s.schedule_date DESC,e.title,u.last_name,r.code,a.name")->fetchAll();
        foreach ($tasks as &$task) {
            foreach (['id','officer_assignment_id','event_schedule_id','activity_id','team_id','event_id'] as $key) $task[$key] = (int) $task[$key];
            $task['scanner_team_id'] = $task['scanner_team_id'] === null ? null : (int) $task['scanner_team_id'];
            $task['officer_name'] = $this->name($task);
        }
        unset($task);

        $groups = [];
        foreach ($tasks as $task) {
            $key = $task['officer_assignment_id'].':'.$task['event_schedule_id'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'id' => $task['id'],
                    'officer_assignment_id' => $task['officer_assignment_id'],
                    'event_schedule_id' => $task['event_schedule_id'],
                    'officer_name' => $task['officer_name'],
                    'event_name' => $task['event_name'],
                    'schedule_date' => $task['schedule_date'],
                    'team_name' => $task['team_name'],
                    'scanner_mode' => 'specific',
                ];
            }
            if ($task['responsibility'] === 'attendance') {
                $groups[$key]['id'] = $task['id'];
                $groups[$key]['scanner_mode'] = $task['scanner_mode'] ?: 'specific';
                $groups[$key]['team_name'] = $task['scanner_team_name'] ?: $task['team_name'];
            }
        }

        return ['officers' => $officers, 'events' => $events, 'tasks' => $tasks, 'assignment_groups' => array_values($groups)];
    }

    public function assign(array $input, int $actorId): int
    {
        $officerId = (int) ($input['officer_assignment_id'] ?? 0);
        $rawScheduleIds = $input['event_schedule_ids'] ?? [$input['event_schedule_id'] ?? 0];
        if (!is_array($rawScheduleIds)) $rawScheduleIds = [$rawScheduleIds];
        $scheduleIds = array_values(array_unique(array_filter(array_map('intval', $rawScheduleIds), fn (int $id): bool => $id > 0)));
        $scannerMode = $this->scannerMode($input);
        if ($officerId < 1 || !$scheduleIds) throw new InvalidArgumentException('Officer and at least one event day are required.');
        if (count($scheduleIds) > 50) throw new InvalidArgumentException('Select no more than 50 event days at once.');

        $this->db->beginTransaction();
        try {
            $anchorId = 0;
            foreach ($scheduleIds as $scheduleId) {
                $result = $this->saveBundle($officerId, $scheduleId, $scannerMode, $actorId);
                if (!$anchorId) $anchorId = $result['anchor_id'];
                $this->log($actorId, $result['event_id'], $officerId, 'sbo_event_assigned', 'Assigned attendance, scoring, and media event access.');
            }
            $this->db->commit();
            return $anchorId;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function update(array $input, int $actorId): void
    {
        $id = (int) ($input['id'] ?? 0);
        $scannerMode = $this->scannerMode($input);
        if ($id < 1) throw new InvalidArgumentException('That event access was not found.');

        $this->db->beginTransaction();
        try {
            $task = $this->row("SELECT sea.officer_assignment_id,sea.event_schedule_id,s.event_id
                FROM tbl_sbo_event_assignments sea
                JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id
                WHERE sea.id=? AND sea.status='active' FOR UPDATE", [$id]);
            if (!$task) throw new InvalidArgumentException('That active event access was not found.');
            $result = $this->saveBundle((int) $task['officer_assignment_id'], (int) $task['event_schedule_id'], $scannerMode, $actorId);
            $this->log($actorId, (int) $task['event_id'], (int) $task['officer_assignment_id'], 'sbo_event_updated', 'Updated attendance scanner access.');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function end(int $id, int $actorId): void
    {
        $this->db->beginTransaction();
        try {
            $task = $this->row("SELECT sea.officer_assignment_id,sea.event_schedule_id,sea.status,s.event_id
                FROM tbl_sbo_event_assignments sea
                JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id
                WHERE sea.id=? FOR UPDATE", [$id]);
            if (!$task) throw new InvalidArgumentException('That event access was not found.');
            $statement = $this->db->prepare("UPDATE tbl_sbo_event_assignments
                SET status='inactive',ended_by=?,ended_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP
                WHERE officer_assignment_id=? AND event_schedule_id=? AND status='active'");
            $statement->execute([$actorId, (int) $task['officer_assignment_id'], (int) $task['event_schedule_id']]);
            if ($statement->rowCount()) $this->log($actorId, (int) $task['event_id'], (int) $task['officer_assignment_id'], 'sbo_event_unassigned', 'Ended attendance, scoring, and media event access.');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function addCriterion(array $input): void
    {
        $taskId = (int) ($input['task_id'] ?? 0);
        $name = trim((string) ($input['name'] ?? ''));
        $minimum = $input['min_points'] ?? null;
        $maximum = $input['max_points'] ?? null;
        if ($name === '' || mb_strlen($name) > 80) throw new InvalidArgumentException('Criterion name is required and may not exceed 80 characters.');
        if (!is_numeric($minimum) || !is_numeric($maximum) || (float) $minimum < 0 || (float) $maximum <= (float) $minimum) throw new InvalidArgumentException('Maximum score must be greater than the minimum score.');
        $this->db->beginTransaction();
        try {
            $task = $this->row("SELECT sea.activity_id,s.event_id FROM tbl_sbo_event_assignments sea JOIN tbl_officer_responsibilities r ON r.id=sea.responsibility_id JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id WHERE sea.id=? AND r.code='scoring' AND sea.status='active' FOR UPDATE", [$taskId]);
            if (!$task) throw new InvalidArgumentException('Scoring responsibility not found.');
            $sort = 1 + (int) $this->scalar('SELECT COALESCE(MAX(sort_order),0) FROM tbl_score_categories WHERE event_id=? AND activity_id=?', [(int) $task['event_id'], (int) $task['activity_id']]);
            $this->db->prepare('INSERT INTO tbl_score_categories(event_id,activity_id,name,min_points,max_points,sort_order,created_at,updated_at) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([(int) $task['event_id'], (int) $task['activity_id'], $name, (float) $minimum, (float) $maximum, $sort]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function reopen(int $taskId, int $actorId): void
    {
        $this->db->beginTransaction();
        try {
            $task = $this->row("SELECT sea.activity_id,sea.team_id,s.event_id FROM tbl_sbo_event_assignments sea JOIN tbl_officer_responsibilities r ON r.id=sea.responsibility_id JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id WHERE sea.id=? AND r.code='scoring' FOR UPDATE", [$taskId]);
            if (!$task) throw new InvalidArgumentException('Scoring responsibility not found.');
            $sheet = $this->db->prepare('SELECT status FROM tbl_score_sheets WHERE event_id=? AND activity_id=? AND team_id=? FOR UPDATE');
            $sheet->execute([(int) $task['event_id'], (int) $task['activity_id'], (int) $task['team_id']]);
            $status = $sheet->fetchColumn();
            if ($status === false) throw new InvalidArgumentException('This score sheet does not exist.');
            if ($status === 'draft') {
                $this->db->commit();
                return;
            }
            $statement = $this->db->prepare("UPDATE tbl_score_sheets SET status='draft',reopened_by=?,reopened_at=CURRENT_TIMESTAMP,finalized_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE event_id=? AND activity_id=? AND team_id=? AND status='finalized'");
            $statement->execute([$actorId, (int) $task['event_id'], (int) $task['activity_id'], (int) $task['team_id']]);
            if (!$statement->rowCount()) throw new InvalidArgumentException('This score sheet is not finalized.');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function saveBundle(int $officerId, int $scheduleId, string $scannerMode, int $actorId): array
    {
        $officer = $this->row("SELECT oa.id,student.id student_user_id
            FROM tbl_sbo_officer_assignments oa
            JOIN tbl_users officer ON officer.id=oa.officer_user_id
            JOIN tbl_roles officer_role ON officer_role.id=officer.role_id AND officer_role.name='SBO Officer'
            JOIN tbl_user_statuses officer_status ON officer_status.id=officer.status AND officer_status.label='active'
            JOIN tbl_roles student_role ON student_role.name='Student'
            JOIN tbl_users student ON student.id_number=oa.student_id AND student.role_id=student_role.id
            WHERE oa.id=? AND oa.status='Active' FOR UPDATE", [$officerId]);
        if (!$officer) throw new InvalidArgumentException('Select an active SBO Officer.');

        $schedule = $this->row("SELECT s.id,s.event_id,m.code session_mode,e.event_type_id,e.deleted_at,ap.school_year_id
            FROM tbl_event_attendance_schedules s
            JOIN tbl_attendance_session_modes m ON m.id=s.attendance_session_mode_id
            JOIN tbl_events e ON e.id=s.event_id
            JOIN tbl_academic_periods ap ON ap.id=e.academic_period_id
            WHERE s.id=? FOR UPDATE", [$scheduleId]);
        if (!$schedule || $schedule['deleted_at'] !== null) throw new InvalidArgumentException('The selected event day no longer exists.');
        if ($schedule['session_mode'] !== 'whole_day') throw new InvalidArgumentException('This event day must use the whole-day attendance session.');

        $team = $this->row("SELECT t.id,t.name FROM tbl_team_user tu
            JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1
            WHERE tu.user_id=? AND t.school_year_id=? ORDER BY t.id LIMIT 1 FOR UPDATE", [(int) $officer['student_user_id'], (int) $schedule['school_year_id']]);
        if (!$team) throw new InvalidArgumentException('This SBO Officer does not have an active tribe for the event school year.');
        if (!(new AcademicPeriodScope($this->db))->teamIsInEvent((int) $schedule['event_id'], (int) $team['id'])) throw new InvalidArgumentException("This SBO Officer's tribe is not eligible for the selected event.");

        $activitiesStatement = $this->db->prepare("SELECT id FROM tbl_event_activities
            WHERE event_id=? AND status<>'inactive' AND (event_schedule_id IS NULL OR event_schedule_id=?)
            ORDER BY event_schedule_id IS NULL,id FOR UPDATE");
        $activitiesStatement->execute([(int) $schedule['event_id'], $scheduleId]);
        $activityIds = array_map('intval', $activitiesStatement->fetchAll(PDO::FETCH_COLUMN));
        if (!$activityIds) $activityIds[] = $this->createDefaultActivity($schedule, $actorId);

        $responsibilityStatement = $this->db->query("SELECT id,code FROM tbl_officer_responsibilities WHERE code IN ('attendance','scoring','media')");
        $responsibilities = [];
        foreach ($responsibilityStatement->fetchAll() as $row) $responsibilities[$row['code']] = (int) $row['id'];
        foreach (self::RESPONSIBILITIES as $code) if (!isset($responsibilities[$code])) throw new DomainException('The required SBO responsibilities are not configured.');

        $scannerTeamId = $scannerMode === 'specific' ? (int) $team['id'] : null;
        $anchorId = 0;
        foreach (self::RESPONSIBILITIES as $code) {
            $targets = $code === 'attendance' ? [$activityIds[0]] : $activityIds;
            foreach ($targets as $activityId) {
                $existing = $code === 'attendance'
                    ? $this->row("SELECT id FROM tbl_sbo_event_assignments
                        WHERE officer_assignment_id=? AND event_schedule_id=? AND responsibility_id=? AND status='active'
                        LIMIT 1 FOR UPDATE", [$officerId, $scheduleId, $responsibilities[$code]])
                    : $this->row("SELECT id FROM tbl_sbo_event_assignments
                        WHERE officer_assignment_id=? AND event_schedule_id=? AND activity_id=? AND responsibility_id=? AND status='active'
                        LIMIT 1 FOR UPDATE", [$officerId, $scheduleId, $activityId, $responsibilities[$code]]);
                if ($existing) {
                    $taskId = (int) $existing['id'];
                    $this->db->prepare('UPDATE tbl_sbo_event_assignments SET session_code=?,team_id=?,scanner_mode=?,scanner_team_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([
                        'whole_day', (int) $team['id'], $code === 'attendance' ? $scannerMode : null, $code === 'attendance' ? $scannerTeamId : null, $taskId,
                    ]);
                } else {
                    $this->db->prepare("INSERT INTO tbl_sbo_event_assignments(officer_assignment_id,event_schedule_id,session_code,activity_id,team_id,responsibility_id,scanner_mode,scanner_team_id,status,assigned_by,created_at,updated_at)
                        VALUES(?,?,'whole_day',?,?,?,?,?,'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([
                        $officerId, $scheduleId, $activityId, (int) $team['id'], $responsibilities[$code], $code === 'attendance' ? $scannerMode : null, $code === 'attendance' ? $scannerTeamId : null, $actorId,
                    ]);
                    $taskId = (int) $this->db->lastInsertId();
                }
                if ($code === 'attendance' && !$anchorId) $anchorId = $taskId;
            }
        }

        $this->db->prepare("UPDATE tbl_sbo_event_assignments sea
            JOIN tbl_officer_responsibilities r ON r.id=sea.responsibility_id AND r.code='attendance'
            SET sea.session_code='whole_day',sea.team_id=?,sea.scanner_mode=?,sea.scanner_team_id=?,sea.updated_at=CURRENT_TIMESTAMP
            WHERE sea.officer_assignment_id=? AND sea.event_schedule_id=? AND sea.status='active'")->execute([(int) $team['id'], $scannerMode, $scannerTeamId, $officerId, $scheduleId]);

        return ['anchor_id' => $anchorId, 'event_id' => (int) $schedule['event_id']];
    }

    private function createDefaultActivity(array $schedule, int $actorId): int
    {
        if ($schedule['event_type_id'] === null) throw new InvalidArgumentException('Assign an event type before granting SBO event access.');
        $label = 'General Event Operations';
        $existing = $this->row('SELECT id,status FROM tbl_event_activities WHERE event_id=? AND lower(name)=lower(?) LIMIT 1 FOR UPDATE', [(int) $schedule['event_id'], $label]);
        if ($existing) {
            if ($existing['status'] !== 'active') $this->db->prepare("UPDATE tbl_event_activities SET status='active',updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([(int) $existing['id']]);
            return (int) $existing['id'];
        }
        $this->db->prepare("INSERT INTO tbl_activities(label,event_type_id,status,created_at,updated_at)
            VALUES(?,?,'active',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE status='active',updated_at=CURRENT_TIMESTAMP")->execute([$label, (int) $schedule['event_type_id']]);
        $catalogId = (int) $this->scalar('SELECT id FROM tbl_activities WHERE event_type_id=? AND label=?', [(int) $schedule['event_type_id'], $label]);
        $this->db->prepare("INSERT INTO tbl_event_activities(event_id,activity_id,name,event_schedule_id,status,created_by,created_at,updated_at)
            VALUES(?,?,?,?,'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([(int) $schedule['event_id'], $catalogId, $label, (int) $schedule['id'], $actorId]);
        return (int) $this->db->lastInsertId();
    }

    private function scannerMode(array $input): string
    {
        $mode = trim((string) ($input['scanner_mode'] ?? 'specific'));
        if (!in_array($mode, ['specific', 'general'], true)) throw new InvalidArgumentException('Choose a valid attendance scanner access.');
        return $mode;
    }

    private function log(int $actorId, int $eventId, int $officerId, string $action, string $description): void
    {
        $this->db->prepare("INSERT INTO tbl_activity_logs(actor_id,event_id,officer_assignment_id,action,acting_role,description,created_at,updated_at)
            VALUES(?,?,?,?,'SBO Adviser',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([$actorId, $eventId, $officerId, $action, $description]);
    }

    private function row(string $sql, array $params = []): array|false
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetch();
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }

    private function name(array $row): string
    {
        return trim(implode(' ', array_filter([$row['first_name'] ?? null, $row['middle_name'] ?? null, $row['last_name'] ?? null])));
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;
$actor = AuthGuard::requireRole('SBO Adviser');
$repository = new SboAssignmentRepository((new Database())->connection());
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') JsonResponse::send(['success' => true, 'data' => $repository->index()]);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) JsonResponse::send(['success' => false, 'message' => 'Your session expired.'], 403);
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $action = (string) ($input['action'] ?? 'assign');
    if ($action === 'assign') {
        $id = $repository->assign($input, (int) $actor['id']);
        $dayCount = is_array($input['event_schedule_ids'] ?? null) ? count(array_unique(array_filter(array_map('intval', $input['event_schedule_ids'])))) : 1;
        JsonResponse::send(['success' => true, 'message' => $dayCount === 1 ? 'Event-day access saved.' : "Access saved for $dayCount event days.", 'id' => $id]);
    }
    if ($action === 'update') {
        $repository->update($input, (int) $actor['id']);
        JsonResponse::send(['success' => true, 'message' => 'Attendance scanner access updated.']);
    }
    if ($action === 'end') {
        $repository->end((int) ($input['id'] ?? 0), (int) $actor['id']);
        JsonResponse::send(['success' => true, 'message' => 'Event access ended.']);
    }
    if ($action === 'criterion') {
        $repository->addCriterion($input);
        JsonResponse::send(['success' => true, 'message' => 'Scoring criterion added.']);
    }
    if ($action === 'reopen') {
        $repository->reopen((int) ($input['task_id'] ?? 0), (int) $actor['id']);
        JsonResponse::send(['success' => true, 'message' => 'Score sheet reopened for editing.']);
    }
    JsonResponse::send(['success' => false, 'message' => 'Unknown assignment action.'], 422);
} catch (InvalidArgumentException|DomainException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'SBO assignment request failed.'], 500);
}
