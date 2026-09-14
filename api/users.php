<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class UserManagementRepository
{
    private const MANAGEABLE_ROLES = ['SBO Adviser', 'SBO', 'SBO Officer', 'Faculty', 'Student'];
    private const CREATABLE_ROLES = ['SBO Adviser', 'Faculty', 'Student'];

    public function __construct(private readonly PDO $db) {}

    public function index(array $filters = []): array
    {
        $where = [];
        $params = [];
        $search = trim((string) ($filters['search'] ?? ''));
        $role = trim((string) ($filters['role'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = 10;

        if (mb_strlen($search) > 100) {
            throw new InvalidArgumentException('Search may not exceed 100 characters.');
        }
        if ($search !== '') {
            $where[] = '(u.first_name LIKE :q ESCAPE \'\\\\\' OR u.middle_name LIKE :q ESCAPE \'\\\\\' OR u.last_name LIKE :q ESCAPE \'\\\\\' OR u.email LIKE :q ESCAPE \'\\\\\' OR u.username LIKE :q ESCAPE \'\\\\\' OR u.id_number LIKE :q ESCAPE \'\\\\\')';
            $params['q'] = '%'.addcslashes($search, '%_\\').'%';
        }
        if ($role !== '') {
            if (!$this->value('SELECT id FROM tbl_roles WHERE name = ?', [$role])) {
                throw new InvalidArgumentException('The selected role is invalid.');
            }
            $where[] = 'r.name = :role';
            $params['role'] = $role;
        }
        if ($status !== '') {
            if (!in_array($status, ['active', 'inactive'], true)) {
                throw new InvalidArgumentException('The selected status is invalid.');
            }
            $where[] = 's.label = :status';
            $params['status'] = $status;
        }

        $from = ' FROM tbl_users u
                LEFT JOIN tbl_roles r ON r.id = u.role_id
                LEFT JOIN tbl_user_statuses s ON s.id = u.status
                LEFT JOIN tbl_year_levels yl ON yl.id = u.year_level'
            .($where ? ' WHERE '.implode(' AND ', $where) : '');
        $countStatement = $this->db->prepare('SELECT COUNT(*)'.$from);
        $countStatement->execute($params);
        $total = (int) $countStatement->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $sql = 'SELECT u.id, u.id_number, u.first_name, u.middle_name, u.last_name,
                    u.username, u.email, u.role_id, u.year_level, u.status AS status_id,
                    r.name AS role, s.label AS status, yl.label AS year_level_label'
            .$from.' ORDER BY u.last_name, u.first_name LIMIT :limit OFFSET :offset';
        $statement = $this->db->prepare($sql);
        foreach ($params as $key => $value) $statement->bindValue(':'.$key, $value);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $users = $statement->fetchAll();

        foreach ($users as &$user) {
            $user['id'] = (int) $user['id'];
            $user['role_id'] = $user['role_id'] === null ? null : (int) $user['role_id'];
            $user['year_level'] = $user['year_level'] === null ? null : (int) $user['year_level'];
            $user['full_name'] = trim(implode(' ', array_filter([
                $user['first_name'],
                $user['middle_name'],
                $user['last_name'],
            ])));
            $assigned = $this->db->prepare('SELECT e.id, e.title, e.start_at FROM tbl_events e JOIN tbl_event_user eu ON eu.event_id = e.id WHERE eu.user_id = ? ORDER BY e.start_at');
            $assigned->execute([$user['id']]);
            $user['assigned_events'] = $assigned->fetchAll();
        }
        unset($user);

        return [
            'users' => $users,
            'summary' => $this->summary(),
            'roles' => $this->db->query('SELECT id, name FROM tbl_roles ORDER BY name')->fetchAll(),
            'filter_roles' => $this->db->query('SELECT r.id, r.name FROM tbl_roles r WHERE EXISTS (SELECT 1 FROM tbl_users u WHERE u.role_id = r.id) ORDER BY r.name')->fetchAll(),
            'year_levels' => $this->db->query('SELECT id, label FROM tbl_year_levels ORDER BY id')->fetchAll(),
            'events' => $this->db->query("SELECT e.id, e.title, e.start_at
                FROM tbl_events e
                LEFT JOIN tbl_event_statuses s ON s.id = e.event_status_id
                WHERE s.label IS NULL OR s.label <> 'inactive'
                ORDER BY e.start_at")->fetchAll(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total === 0 ? null : (($page - 1) * $perPage) + 1,
                'to' => $total === 0 ? null : min($page * $perPage, $total),
            ],
        ];
    }

    public function save(array $data, int $actorId, ?int $id = null): int
    {
        foreach (['first_name', 'last_name', 'username', 'email', 'role_id'] as $field) {
            if (trim((string) ($data[$field] ?? '')) === '') {
                throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $field)).' is required.');
            }
        }
        foreach (['first_name', 'middle_name', 'last_name', 'username', 'email', 'id_number'] as $field) {
            if (mb_strlen(trim((string) ($data[$field] ?? ''))) > 255) {
                throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $field)).' may not exceed 255 characters.');
            }
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid email address.');
        }
        if (!preg_match('/^[A-Za-z0-9._-]+$/', (string) $data['username'])) {
            throw new InvalidArgumentException('Username may only contain letters, numbers, dots, underscores, and hyphens.');
        }
        if (!$id && strlen((string) ($data['password'] ?? '')) < 8) {
            throw new InvalidArgumentException('Password must contain at least 8 characters.');
        }
        if (!empty($data['password']) && strlen((string) $data['password']) < 8) {
            throw new InvalidArgumentException('Password must contain at least 8 characters.');
        }
        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            throw new InvalidArgumentException('The password confirmation does not match.');
        }

        $role = $this->value('SELECT name FROM tbl_roles WHERE id = ?', [(int) $data['role_id']]);
        $allowedRoles = $id ? self::MANAGEABLE_ROLES : self::CREATABLE_ROLES;
        if (!in_array($role, $allowedRoles, true)) {
            throw new InvalidArgumentException('That role cannot be assigned here.');
        }
        if ($id) {
            $this->requireManageableUser($id);
        }
        if ($role === 'Student' && !preg_match('/^02-\d{4}-\d{6}$/', (string) ($data['id_number'] ?? ''))) {
            throw new InvalidArgumentException('Student ID numbers must use 02-xxxx-xxxxxx.');
        }
        if (($data['year_level'] ?? '') !== '' && !$this->value('SELECT id FROM tbl_year_levels WHERE id = ?', [(int) $data['year_level']])) {
            throw new InvalidArgumentException('The selected year level is invalid.');
        }

        $duplicateSql = 'SELECT id FROM tbl_users
                         WHERE (username = :username OR email = :email
                            OR (:id_number IS NOT NULL AND id_number = :id_number))'
            .($id ? ' AND id <> :id' : '');
        $duplicate = $this->db->prepare($duplicateSql);
        $params = [
            'username' => trim((string) $data['username']),
            'email' => trim((string) $data['email']),
            'id_number' => trim((string) ($data['id_number'] ?? '')) ?: null,
        ];
        if ($id) $params['id'] = $id;
        $duplicate->execute($params);
        if ($duplicate->fetch()) {
            throw new InvalidArgumentException('Username, email, or ID number is already in use.');
        }

        $values = [
            'first_name' => trim((string) $data['first_name']),
            'middle_name' => trim((string) ($data['middle_name'] ?? '')) ?: null,
            'last_name' => trim((string) $data['last_name']),
            'id_number' => trim((string) ($data['id_number'] ?? '')) ?: null,
            'username' => trim((string) $data['username']),
            'email' => trim((string) $data['email']),
            'role_id' => (int) $data['role_id'],
            'year_level' => ($data['year_level'] ?? '') !== '' ? (int) $data['year_level'] : null,
        ];

        $this->db->beginTransaction();
        try {
            if ($id) {
                $set = [];
                foreach ($values as $key => $unused) $set[] = "$key = :$key";
                if (!empty($data['password'])) {
                    $values['password'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
                    $set[] = 'password = :password';
                }
                $values['id'] = $id;
                $statement = $this->db->prepare('UPDATE tbl_users SET '.implode(', ', $set).', updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $statement->execute($values);
                if ($role === 'Student') {
                    $statement = $this->db->prepare('DELETE FROM tbl_event_user WHERE user_id = ?');
                    $statement->execute([$id]);
                }
            } else {
                $values['password'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
                $values['status'] = $this->value("SELECT id FROM tbl_user_statuses WHERE label = 'active'");
                $statement = $this->db->prepare("INSERT INTO tbl_users
                    (first_name, middle_name, last_name, id_number, username, email, password, role_id, year_level, status, created_at, updated_at)
                    VALUES (:first_name, :middle_name, :last_name, :id_number, :username, :email, :password, :role_id, :year_level, :status, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $statement->execute($values);
                $id = (int) $this->db->lastInsertId();
                $fullName = trim(implode(' ', array_filter([$values['first_name'], $values['middle_name'], $values['last_name']])));
                $this->log($actorId, $id, 'user_created', "$fullName was added as $role.");
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function toggle(int $id, int $actorId): void
    {
        $user = $this->requireManageableUser($id);
        $next = $user['status'] === 'active' ? 'inactive' : 'active';
        $statement = $this->db->prepare("UPDATE tbl_users
            SET status = (SELECT id FROM tbl_user_statuses WHERE label = :status), updated_at = CURRENT_TIMESTAMP
            WHERE id = :id");
        $statement->execute(['status' => $next, 'id' => $id]);
        $verb = $next === 'active' ? 'activated' : 'deactivated';
        $this->log($actorId, $id, 'user_status_changed', $user['full_name']." was $verb.");
    }

    public function assignEvent(int $userId, int $eventId, int $actorId): void
    {
        $user = $this->requireAssignableUser($userId);
        $statement = $this->db->prepare("SELECT e.id, e.title, s.label AS status
            FROM tbl_events e LEFT JOIN tbl_event_statuses s ON s.id = e.event_status_id WHERE e.id = ?");
        $statement->execute([$eventId]);
        $event = $statement->fetch();
        if (!$event) throw new InvalidArgumentException('Event not found.');
        if ($event['status'] === 'inactive') throw new InvalidArgumentException('Inactive events cannot receive new assignments.');
        if ($this->value('SELECT 1 FROM tbl_event_user WHERE user_id = ? AND event_id = ?', [$userId, $eventId])) {
            throw new InvalidArgumentException('That event is already assigned to this user.');
        }
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('INSERT INTO tbl_event_user (user_id, event_id, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
            $statement->execute([$userId, $eventId]);
            $this->log($actorId, $userId, 'event_assigned', $user['full_name'].' was assigned to '.$event['title'].'.', $eventId);
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function unassignEvent(int $userId, int $eventId, int $actorId): void
    {
        $user = $this->requireAssignableUser($userId);
        $title = $this->value('SELECT title FROM tbl_events WHERE id = ?', [$eventId]);
        if (!$title || !$this->value('SELECT 1 FROM tbl_event_user WHERE user_id = ? AND event_id = ?', [$userId, $eventId])) {
            throw new InvalidArgumentException('That event assignment was already removed.');
        }
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('DELETE FROM tbl_event_user WHERE user_id = ? AND event_id = ?');
            $statement->execute([$userId, $eventId]);
            $this->log($actorId, $userId, 'event_unassigned', $user['full_name']." was unassigned from $title.", $eventId);
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    private function requireManageableUser(int $id): array
    {
        $statement = $this->db->prepare("SELECT u.id, r.name AS role, s.label AS status,
                TRIM(CONCAT_WS(' ', u.first_name, NULLIF(u.middle_name, ''), u.last_name)) AS full_name
            FROM tbl_users u
            LEFT JOIN tbl_roles r ON r.id = u.role_id
            LEFT JOIN tbl_user_statuses s ON s.id = u.status
            WHERE u.id = ?");
        $statement->execute([$id]);
        $user = $statement->fetch();
        if (!$user) throw new InvalidArgumentException('User not found.');
        if (!in_array($user['role'], self::MANAGEABLE_ROLES, true)) {
            throw new InvalidArgumentException('That account cannot be managed here.');
        }
        return $user;
    }

    private function requireAssignableUser(int $id): array
    {
        $user = $this->requireManageableUser($id);
        if (!in_array($user['role'], ['SBO Adviser', 'SBO', 'Faculty'], true)) {
            throw new InvalidArgumentException('Only SBO Adviser, SBO, and Faculty users can be assigned to events.');
        }
        return $user;
    }

    private function summary(): array
    {
        $row = $this->db->query("SELECT COUNT(*) AS total,
                SUM(r.name = 'Student') AS students,
                SUM(r.name = 'Faculty') AS faculty,
                SUM(r.name IN ('SBO', 'SBO Officer')) AS sbo,
                SUM(s.label = 'active') AS active,
                SUM(s.label = 'inactive') AS inactive
            FROM tbl_users u
            LEFT JOIN tbl_roles r ON r.id = u.role_id
            LEFT JOIN tbl_user_statuses s ON s.id = u.status")->fetch();
        return array_map('intval', $row);
    }

    private function log(int $actorId, int $subjectId, string $action, string $description, ?int $eventId = null): void
    {
        $statement = $this->db->prepare("INSERT INTO tbl_activity_logs
            (actor_id, subject_user_id, event_id, action, description, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $statement->execute([$actorId, $subjectId, $eventId, $action, $description]);
    }

    private function value(string $sql, array $params = []): mixed
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }
}

$actor=AuthGuard::requireRole('SBO Adviser'); $repo=new UserManagementRepository((new Database())->connection());
try {
    if($_SERVER['REQUEST_METHOD']==='GET') JsonResponse::send(['success'=>true,'data'=>$repo->index($_GET)]);
    if($_SERVER['REQUEST_METHOD']!=='POST') JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
    if(!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null)) JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
    $input=json_decode(file_get_contents('php://input'),true); if(!is_array($input))$input=$_POST; $action=$input['action']??'create';
    if($action==='toggle'){ $repo->toggle((int)($input['id']??0), (int)$actor['id']); JsonResponse::send(['success'=>true,'message'=>'Account status updated.']); }
    if($action==='assign_event'){ $repo->assignEvent((int)($input['id']??0),(int)($input['event_id']??0),(int)$actor['id']); JsonResponse::send(['success'=>true,'message'=>'Event assigned successfully.']); }
    if($action==='unassign_event'){ $repo->unassignEvent((int)($input['id']??0),(int)($input['event_id']??0),(int)$actor['id']); JsonResponse::send(['success'=>true,'message'=>'Event assignment removed.']); }
    if(!in_array($action,['create','update'],true)) JsonResponse::send(['success'=>false,'message'=>'Unknown user-management action.'],422);
    $id=$repo->save($input,(int)$actor['id'],$action==='update'?(int)($input['id']??0):null); JsonResponse::send(['success'=>true,'id'=>$id,'message'=>$action==='update'?'User updated successfully.':'User added successfully.']);
} catch(InvalidArgumentException $e){JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],422);} catch(Throwable $e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>'User management request failed.'],500);}
