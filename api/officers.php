<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class OfficerManagementRepository
{
    private const PER_PAGE = 15;

    public function __construct(private readonly PDO $db) {}

    public function index(int $page = 1): array
    {
        $page = max(1, $page);
        $total = (int) $this->db->query('SELECT COUNT(*) FROM sbo_officer_assignments')->fetchColumn();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $offset = ($page - 1) * self::PER_PAGE;

        $statement = $this->db->prepare("SELECT a.id, a.student_id, a.officer_user_id, a.team_id,
                a.position, a.term, a.assigned_by, a.assigned_at, a.ended_by, a.ended_at, a.status,
                u.first_name, u.middle_name, u.last_name, u.email, u.username,
                us.label AS account_status, t.name AS team_name,
                TRIM(CONCAT_WS(' ', assigner.first_name, NULLIF(assigner.middle_name, ''), assigner.last_name)) AS assigned_by_name
            FROM sbo_officer_assignments a
            JOIN users u ON u.id = a.officer_user_id
            LEFT JOIN user_statuses us ON us.id = u.status
            LEFT JOIN teams t ON t.id = a.team_id
            LEFT JOIN users assigner ON assigner.id = a.assigned_by
            ORDER BY a.assigned_at DESC, a.id DESC
            LIMIT :limit OFFSET :offset");
        $statement->bindValue(':limit', self::PER_PAGE, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);
        $statement->execute();
        $assignments = $statement->fetchAll();
        foreach ($assignments as &$assignment) {
            $assignment['id'] = (int) $assignment['id'];
            $assignment['officer_user_id'] = (int) $assignment['officer_user_id'];
            $assignment['team_id'] = $assignment['team_id'] === null ? null : (int) $assignment['team_id'];
            $assignment['full_name'] = $this->fullName($assignment);
        }
        unset($assignment);

        $students = $this->db->query("SELECT u.id, u.id_number, u.first_name, u.middle_name, u.last_name,
                u.email, u.username, u.year_level, yl.label AS year_level_label
            FROM users u
            JOIN roles r ON r.id = u.role_id
            JOIN user_statuses s ON s.id = u.status
            LEFT JOIN year_levels yl ON yl.id = u.year_level
            WHERE r.name = 'Student' AND s.label = 'active' AND u.id_number IS NOT NULL
            ORDER BY u.first_name, u.middle_name, u.last_name")->fetchAll();
        foreach ($students as &$student) {
            $student['id'] = (int) $student['id'];
            $student['year_level'] = $student['year_level'] === null ? null : (int) $student['year_level'];
            $student['full_name'] = $this->fullName($student);
            $student['suggested_username'] = $this->suggestedUsername($student['first_name'].'.'.$student['last_name']).'.sbo';
        }
        unset($student);
        usort($students, fn (array $left, array $right): int => strcasecmp($left['full_name'], $right['full_name']));

        return [
            'students' => $students,
            'assignments' => $assignments,
            'teams' => $this->db->query('SELECT id, name, color FROM teams WHERE is_active = 1 ORDER BY name')->fetchAll(),
            'year_levels' => $this->db->query('SELECT id, label FROM year_levels ORDER BY id')->fetchAll(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => self::PER_PAGE,
                'total' => $total,
                'from' => $total === 0 ? null : $offset + 1,
                'to' => $total === 0 ? null : min($offset + self::PER_PAGE, $total),
            ],
        ];
    }

    public function assign(array $data, array $actor): int
    {
        foreach (['student_user_id', 'team_id', 'position', 'term', 'username', 'password'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $field)).' is required.');
            }
        }
        foreach (['position', 'term'] as $field) {
            if (mb_strlen(trim((string) $data[$field])) > 100) {
                throw new InvalidArgumentException(ucfirst($field).' may not exceed 100 characters.');
            }
        }
        if (mb_strlen(trim((string) $data['username'])) > 255 || !preg_match('/^[A-Za-z0-9._-]+$/', (string) $data['username'])) {
            throw new InvalidArgumentException('Username may only contain letters, numbers, dots, underscores, and hyphens.');
        }
        if (strlen((string) $data['password']) < 8) {
            throw new InvalidArgumentException('Password must contain at least 8 characters.');
        }
        if (($data['password_confirmation'] ?? '') !== $data['password']) {
            throw new InvalidArgumentException('The password confirmation does not match.');
        }

        $student = $this->student((int) $data['student_user_id']);
        $teamId = (int) $data['team_id'];
        if (!$this->value('SELECT id FROM teams WHERE id = ? AND is_active = 1', [$teamId])) {
            throw new InvalidArgumentException('Select an active tribe.');
        }

        $officerRoleId = (int) $this->value("SELECT id FROM roles WHERE name = 'SBO Officer'");
        $existingOfficerId = $this->value('SELECT id FROM users WHERE id_number = ? AND role_id = ? LIMIT 1', [$student['id_number'], $officerRoleId]);
        $duplicateUsername = $this->db->prepare('SELECT id FROM users WHERE username = ?'.($existingOfficerId ? ' AND id <> ?' : ''));
        $duplicateUsername->execute($existingOfficerId
            ? [trim((string) $data['username']), (int) $existingOfficerId]
            : [trim((string) $data['username'])]);
        if ($duplicateUsername->fetchColumn()) {
            throw new InvalidArgumentException('That username is already in use.');
        }
        if ($this->value("SELECT id FROM sbo_officer_assignments WHERE student_id = ? AND term = ? AND status = 'Active' LIMIT 1", [$student['id_number'], trim((string) $data['term'])])) {
            throw new InvalidArgumentException('This student already has an active SBO Officer assignment for this term.');
        }

        $activeStatusId = (int) $this->value("SELECT id FROM user_statuses WHERE label = 'active'");
        $account = [
            'id_number' => $student['id_number'],
            'first_name' => $student['first_name'],
            'middle_name' => $student['middle_name'],
            'last_name' => $student['last_name'],
            'email' => $student['email'],
            'year_level' => $student['year_level'],
            'role_id' => $officerRoleId,
            'username' => trim((string) $data['username']),
            'password' => password_hash((string) $data['password'], PASSWORD_BCRYPT),
            'status' => $activeStatusId,
            'officer_team_id' => $teamId,
        ];

        $this->db->beginTransaction();
        try {
            if ($existingOfficerId) {
                $account['id'] = (int) $existingOfficerId;
                $statement = $this->db->prepare("UPDATE users SET id_number=:id_number, first_name=:first_name,
                    middle_name=:middle_name, last_name=:last_name, email=:email, year_level=:year_level,
                    role_id=:role_id, username=:username, password=:password, status=:status,
                    officer_team_id=:officer_team_id, must_change_password=1, updated_at=CURRENT_TIMESTAMP WHERE id=:id");
                $statement->execute($account);
                $officerId = (int) $existingOfficerId;
            } else {
                $statement = $this->db->prepare("INSERT INTO users
                    (id_number, first_name, middle_name, last_name, email, year_level, role_id, username,
                     password, status, officer_team_id, must_change_password, created_at, updated_at)
                    VALUES (:id_number,:first_name,:middle_name,:last_name,:email,:year_level,:role_id,:username,
                     :password,:status,:officer_team_id,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
                $statement->execute($account);
                $officerId = (int) $this->db->lastInsertId();
            }

            $statement = $this->db->prepare("INSERT INTO sbo_officer_assignments
                (student_id, officer_user_id, team_id, position, term, assigned_by, assigned_at, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 'Active', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
            $statement->execute([
                $student['id_number'], $officerId, $teamId, trim((string) $data['position']),
                trim((string) $data['term']), (int) $actor['id'],
            ]);
            $assignmentId = (int) $this->db->lastInsertId();
            $description = $student['full_name'].' was assigned as '.trim((string) $data['position']).' for '.trim((string) $data['term']).'.';
            $this->log((int) $actor['id'], $officerId, $student['id_number'], $assignmentId, 'officer_assigned', (string) $actor['role'], $description);
            $this->db->commit();
            return $assignmentId;
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function unassign(array $ids, array $actor): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id): bool => $id > 0)));
        if (!$ids) throw new InvalidArgumentException('Select at least one active officer assignment.');

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->prepare("SELECT * FROM sbo_officer_assignments WHERE id IN ($placeholders) AND status = 'Active'");
        $statement->execute($ids);
        $assignments = $statement->fetchAll();
        if (!$assignments) throw new InvalidArgumentException('The selected assignments are already inactive.');
        $inactiveStatusId = (int) $this->value("SELECT id FROM user_statuses WHERE label = 'inactive'");

        $this->db->beginTransaction();
        try {
            $updateAssignment = $this->db->prepare("UPDATE sbo_officer_assignments SET status='Inactive', ended_at=CURRENT_TIMESTAMP, ended_by=?, updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $updateOfficer = $this->db->prepare('UPDATE users SET status=?, officer_team_id=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=?');
            foreach ($assignments as $assignment) {
                $updateAssignment->execute([(int) $actor['id'], (int) $assignment['id']]);
                $updateOfficer->execute([$inactiveStatusId, (int) $assignment['officer_user_id']]);
                $description = 'Officer assignment '.$assignment['position'].' ('.$assignment['term'].') was ended.';
                $this->log((int) $actor['id'], (int) $assignment['officer_user_id'], $assignment['student_id'], (int) $assignment['id'], 'officer_unassigned', (string) $actor['role'], $description);
            }
            $this->db->commit();
            return count($assignments);
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    private function student(int $id): array
    {
        $statement = $this->db->prepare("SELECT u.*, r.name AS role, s.label AS account_status
            FROM users u JOIN roles r ON r.id=u.role_id JOIN user_statuses s ON s.id=u.status WHERE u.id=?");
        $statement->execute([$id]);
        $student = $statement->fetch();
        if (!$student || $student['role'] !== 'Student' || $student['account_status'] !== 'active' || !$student['id_number']) {
            throw new InvalidArgumentException('Select an existing active student account.');
        }
        $student['full_name'] = $this->fullName($student);
        return $student;
    }

    private function log(int $actorId, int $subjectId, string $studentId, int $assignmentId, string $action, string $role, string $description): void
    {
        $statement = $this->db->prepare("INSERT INTO activity_logs
            (actor_id, subject_user_id, student_id, officer_assignment_id, action, acting_role, description, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $statement->execute([$actorId, $subjectId, $studentId, $assignmentId, $action, $role, $description]);
    }

    private function value(string $sql, array $params = []): mixed
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }

    private function fullName(array $person): string
    {
        return trim(implode(' ', array_filter([$person['first_name'], $person['middle_name'], $person['last_name']])));
    }

    private function suggestedUsername(string $value): string
    {
        $value = strtolower(preg_replace('/[^A-Za-z0-9]+/', '.', $value) ?? '');
        return trim($value, '.');
    }
}

$actor = AuthGuard::requireRole('SBO Adviser');
$repository = new OfficerManagementRepository((new Database())->connection());

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        JsonResponse::send(['success' => true, 'data' => $repository->index((int) ($_GET['page'] ?? 1))]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        JsonResponse::send(['success' => false, 'message' => 'Your session expired.'], 403);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    $action = (string) ($input['action'] ?? 'assign');
    if ($action === 'assign') {
        $id = $repository->assign($input, $actor);
        JsonResponse::send(['success' => true, 'id' => $id, 'message' => 'SBO Officer account created. Share the temporary credentials securely.']);
    }
    if (in_array($action, ['unassign', 'batch_unassign'], true)) {
        $ids = $action === 'unassign' ? [(int) ($input['id'] ?? 0)] : (array) ($input['assignment_ids'] ?? []);
        $count = $repository->unassign($ids, $actor);
        $message = $count === 1
            ? 'The officer was unassigned and their officer login was disabled.'
            : "$count officer accounts were unassigned and disabled.";
        JsonResponse::send(['success' => true, 'count' => $count, 'message' => $message]);
    }
    JsonResponse::send(['success' => false, 'message' => 'Unknown officer-management action.'], 422);
} catch (InvalidArgumentException $error) {
    JsonResponse::send(['success' => false, 'message' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log($error->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'Officer management request failed.'], 500);
}
