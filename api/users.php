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
        $perPage = PageSize::from($filters, 10);

        if (mb_strlen($search) > 100) {
            throw new InvalidArgumentException('Search may not exceed 100 characters.');
        }
        if ($search !== '') {
            $where[] = '(u.first_name LIKE :q_first ESCAPE \'\\\\\' OR u.middle_name LIKE :q_middle ESCAPE \'\\\\\' OR u.last_name LIKE :q_last ESCAPE \'\\\\\' OR u.email LIKE :q_email ESCAPE \'\\\\\' OR u.username LIKE :q_username ESCAPE \'\\\\\' OR u.id_number LIKE :q_id ESCAPE \'\\\\\')';
            $term = '%'.addcslashes($search, '%_\\').'%';
            foreach (['q_first', 'q_middle', 'q_last', 'q_email', 'q_username', 'q_id'] as $key) $params[$key] = $term;
        }
        if ($role !== '') {
            if (!$this->value('SELECT id FROM tbl_roles WHERE name = ?', [$role])) {
                throw new InvalidArgumentException('The selected role is invalid.');
            }
            $where[] = 'r.name = :role';
            $params['role'] = $role;
        }
        if ($status !== '') {
            if (!$this->value('SELECT id FROM tbl_user_statuses WHERE label = ?', [$status])) {
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
        if ($page > $lastPage) $page = $lastPage;

        $sql = 'SELECT u.id, u.id_number, u.first_name, u.middle_name, u.last_name,
                    u.username, u.email, u.role_id, u.year_level, u.status AS status_id,
                    (SELECT tu.team_id FROM tbl_team_user tu WHERE tu.user_id=u.id ORDER BY tu.id DESC LIMIT 1) faculty_team_id,
                    (SELECT t.name FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id WHERE tu.user_id=u.id ORDER BY tu.id DESC LIMIT 1) faculty_team_name,
                    (SELECT oa.id FROM tbl_sbo_officer_assignments oa
                        WHERE oa.officer_user_id=u.id AND oa.status=\'Active\'
                        ORDER BY oa.id DESC LIMIT 1) officer_assignment_id,
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
            $user['officer_assignment_id'] = $user['officer_assignment_id'] === null ? null : (int) $user['officer_assignment_id'];
            $user['full_name'] = trim(implode(' ', array_filter([
                $user['first_name'],
                $user['middle_name'],
                $user['last_name'],
            ])));
            $assigned = $this->db->prepare('SELECT e.id, e.title, e.start_at FROM tbl_events e JOIN tbl_event_user eu ON eu.event_id = e.id WHERE eu.user_id = ? ORDER BY e.start_at');
            $assigned->execute([$user['id']]);
            $user['assigned_events'] = $assigned->fetchAll();
            $user['responsibility_events_count'] = 0;
            if ($user['role'] === 'SBO Officer') {
                $responsibilities = $this->db->prepare("SELECT COUNT(DISTINCT schedule.event_id)
                    FROM tbl_sbo_event_assignments responsibility
                    JOIN tbl_sbo_officer_assignments officer ON officer.id = responsibility.officer_assignment_id
                    JOIN tbl_event_attendance_schedules schedule ON schedule.id = responsibility.event_schedule_id
                    WHERE officer.officer_user_id = ? AND officer.status = 'Active' AND responsibility.status = 'active'");
                $responsibilities->execute([$user['id']]);
                $user['responsibility_events_count'] = (int) $responsibilities->fetchColumn();
            }
        }
        unset($user);

        return [
            'users' => $users,
            'summary' => $this->summary(),
            'roles' => $this->db->query('SELECT id, name FROM tbl_roles ORDER BY name')->fetchAll(),
            'filter_roles' => $this->db->query('SELECT id,name FROM tbl_roles ORDER BY name')->fetchAll(),
            'filter_statuses' => $this->db->query('SELECT id,label FROM tbl_user_statuses ORDER BY label')->fetchAll(),
            'year_levels' => $this->db->query('SELECT id, label FROM tbl_year_levels ORDER BY id')->fetchAll(),
            'teams' => $this->db->query('SELECT id,name FROM tbl_teams WHERE is_active=1 ORDER BY name')->fetchAll(),
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
        $role = $this->value('SELECT name FROM tbl_roles WHERE id = ?', [(int) ($data['role_id'] ?? 0)]);
        $previousRole = $id ? $this->value('SELECT r.name FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id WHERE u.id=?', [$id]) : null;
        $allowedRoles = $id ? self::MANAGEABLE_ROLES : self::CREATABLE_ROLES;
        if (!in_array($role, $allowedRoles, true)) {
            throw new InvalidArgumentException('That role cannot be assigned here.');
        }
        if ($role === 'Faculty') {
            $data['id_number'] = mb_strtoupper(trim((string) ($data['id_number'] ?? '')));
            $data['username'] = $data['id_number'];
        }
        if ($role !== 'Student') $data['year_level'] = '';

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
        if ($id && (($data['password'] ?? '') !== '' || ($data['password_confirmation'] ?? '') !== '')) {
            throw new InvalidArgumentException('Use the dedicated password reset action to change an existing account password.');
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

        if ($role === 'SBO Officer' && $previousRole !== 'SBO Officer') {
            throw new InvalidArgumentException('Create separate Officer access from Officer Management instead of changing the student role.');
        }
        if ($previousRole === 'SBO Officer' && $role !== 'SBO Officer') {
            $hasAssignment = (bool) $this->value('SELECT id FROM tbl_sbo_officer_assignments WHERE officer_user_id=? LIMIT 1', [$id]);
            if ($hasAssignment) {
                throw new InvalidArgumentException('Manage this linked Officer account from Officer Management.');
            }
        }
        if ($id) {
            $this->requireManageableUser($id);
        }
        if ($role === 'Student' && !StudentId::isValid((string) ($data['id_number'] ?? ''))) {
            throw new InvalidArgumentException(StudentId::FORMAT_MESSAGE);
        }
        if ($role === 'Faculty' && !FacultyId::isValid((string) ($data['id_number'] ?? ''))) {
            throw new InvalidArgumentException(FacultyId::FORMAT_MESSAGE);
        }
        if (($data['year_level'] ?? '') !== '' && !$this->value('SELECT id FROM tbl_year_levels WHERE id = ?', [(int) $data['year_level']])) {
            throw new InvalidArgumentException('The selected year level is invalid.');
        }
        $facultyTeamId = $role === 'Faculty' && ($data['faculty_team_id'] ?? '') !== '' ? (int)$data['faculty_team_id'] : null;
        if ($facultyTeamId && !$this->value('SELECT id FROM tbl_teams WHERE id=? AND is_active=1', [$facultyTeamId])) throw new InvalidArgumentException('Select an active team.');

        $duplicateSql = 'SELECT id FROM tbl_users
                         WHERE (username = :username OR email = :email
                            OR (:id_number_present IS NOT NULL AND id_number = :id_number))'
            .($id ? ' AND id <> :id' : '');
        $duplicate = $this->db->prepare($duplicateSql);
        $params = [
            'username' => trim((string) $data['username']),
            'email' => trim((string) $data['email']),
            'id_number_present' => trim((string) ($data['id_number'] ?? '')) ?: null,
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
            // Serialize account writes before repeating the uniqueness check. The
            // earlier check provides fast feedback, while this lock closes the
            // race where two simultaneous requests validate the same values.
            $this->db->query('SELECT id FROM tbl_roles ORDER BY id LIMIT 1 FOR UPDATE')->fetchColumn();
            if ($id) {
                $targetLock = $this->db->prepare('SELECT id FROM tbl_users WHERE id = ? FOR UPDATE');
                $targetLock->execute([$id]);
                if (!$targetLock->fetchColumn()) {
                    throw new InvalidArgumentException('The selected user no longer exists.');
                }
            }
            $duplicate->execute($params);
            if ($duplicate->fetch()) {
                throw new InvalidArgumentException('Username, email, or ID number is already in use.');
            }

            if ($id) {
                $set = [];
                foreach ($values as $key => $unused) $set[] = "$key = :$key";
                $values['id'] = $id;
                $statement = $this->db->prepare('UPDATE tbl_users SET '.implode(', ', $set).', updated_at = CURRENT_TIMESTAMP WHERE id = :id');
                $statement->execute($values);
            } else {
                $values['password'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
                $values['status'] = $this->value("SELECT id FROM tbl_user_statuses WHERE label = 'active'");
                $statement = $this->db->prepare("INSERT INTO tbl_users
                    (first_name, middle_name, last_name, id_number, username, email, password, role_id, year_level, status, must_change_password, created_at, updated_at)
                    VALUES (:first_name, :middle_name, :last_name, :id_number, :username, :email, :password, :role_id, :year_level, :status, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
                $statement->execute($values);
                $id = (int) $this->db->lastInsertId();
                $fullName = trim(implode(' ', array_filter([$values['first_name'], $values['middle_name'], $values['last_name']])));
                $this->log($actorId, $id, 'user_created', "$fullName was added as $role.");
            }
            if ($role === 'Faculty' || $previousRole === 'Faculty') {
                $previousFacultyTeamId = $this->value('SELECT team_id FROM tbl_team_user WHERE user_id=? ORDER BY id DESC LIMIT 1', [$id]);
                $this->db->prepare('DELETE FROM tbl_team_user WHERE user_id=?')->execute([$id]);
                if ($role === 'Faculty' && $facultyTeamId) {
                    $this->db->prepare('INSERT INTO tbl_team_user(team_id,user_id,created_at,updated_at) VALUES(?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([$facultyTeamId,$id]);
                    if ($previousRole !== 'Faculty' || (int) $previousFacultyTeamId !== $facultyTeamId) {
                        $teamName = (string) $this->value('SELECT name FROM tbl_teams WHERE id=?', [$facultyTeamId]);
                        $this->log($actorId, $id, 'faculty_attendance_assigned', "Attendance responsibility was assigned for $teamName.");
                    }
                }
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }
    }

    public function toggle(int $id, int $actorId, ?bool $desiredActive = null): void
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM tbl_users WHERE id=? FOR UPDATE');
            $lock->execute([$id]);
            if (!$lock->fetchColumn()) throw new InvalidArgumentException('User not found.');
            $user = $this->requireManageableUser($id);
            $next = $desiredActive === null ? ($user['status'] === 'active' ? 'inactive' : 'active') : ($desiredActive ? 'active' : 'inactive');
            if ($user['status'] === $next) { $this->db->commit(); return; }
            $statement = $this->db->prepare("UPDATE tbl_users
                SET status = (SELECT id FROM tbl_user_statuses WHERE label = :status), updated_at = CURRENT_TIMESTAMP
                WHERE id = :id");
            $statement->execute(['status' => $next, 'id' => $id]);
            $verb = $next === 'active' ? 'activated' : 'deactivated';
            $this->log($actorId, $id, 'user_status_changed', $user['full_name']." was $verb.");
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    /** Reset credentials without changing the account profile or relationships. */
    public function resetPassword(int $id, string $password, string $confirmation, int $actorId): void
    {
        if ($id < 1) throw new InvalidArgumentException('Select an account.');
        if (strlen($password) < 8) throw new InvalidArgumentException('Password must contain at least 8 characters.');
        if ($confirmation !== $password) throw new InvalidArgumentException('The password confirmation does not match.');

        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare("SELECT u.id, r.name AS role,
                    TRIM(CONCAT_WS(' ', u.first_name, NULLIF(u.middle_name, ''), u.last_name)) AS full_name
                FROM tbl_users u
                JOIN tbl_roles r ON r.id = u.role_id
                WHERE u.id = ?
                FOR UPDATE");
            $statement->execute([$id]);
            $user = $statement->fetch();
            if (!$user || !in_array($user['role'], self::MANAGEABLE_ROLES, true)) {
                throw new InvalidArgumentException('That account cannot be managed here.');
            }

            $update = $this->db->prepare('UPDATE tbl_users SET password=?, must_change_password=1, updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $update->execute([password_hash($password, PASSWORD_BCRYPT), $id]);
            $this->log($actorId, $id, 'user_password_reset', $user['full_name']."'s password was reset by the SBO Adviser.");
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function assignEvent(int $userId, int $eventId, int $actorId): void
    {
        $this->db->beginTransaction();
        try {
            $userLock=$this->db->prepare('SELECT id FROM tbl_users WHERE id=? FOR UPDATE');$userLock->execute([$userId]);
            if(!$userLock->fetchColumn())throw new InvalidArgumentException('User not found.');
            $user = $this->requireAssignableUser($userId);
            $statement = $this->db->prepare("SELECT e.id,e.title,s.label status FROM tbl_events e LEFT JOIN tbl_event_statuses s ON s.id=e.event_status_id WHERE e.id=? AND e.deleted_at IS NULL FOR UPDATE");
            $statement->execute([$eventId]);$event=$statement->fetch();
            if(!$event)throw new InvalidArgumentException('Event not found.');
            if($event['status']==='inactive')throw new InvalidArgumentException('Inactive events cannot receive new assignments.');
            $existing=$this->db->prepare('SELECT 1 FROM tbl_event_user WHERE user_id=? AND event_id=? FOR UPDATE');$existing->execute([$userId,$eventId]);
            if($existing->fetchColumn()){$this->db->commit();return;}
            $statement = $this->db->prepare('INSERT INTO tbl_event_user (user_id, event_id, created_at, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
            $statement->execute([$userId, $eventId]);
            $this->log($actorId, $userId, 'event_assigned', $user['full_name'].' was assigned to '.$event['title'].'.', $eventId);
            $this->db->commit();
        } catch (Throwable $error) {
            if($this->db->inTransaction())$this->db->rollBack();
            throw $error;
        }
    }

    public function unassignEvent(int $userId, int $eventId, int $actorId): void
    {
        $this->db->beginTransaction();
        try {
            $userLock=$this->db->prepare('SELECT id FROM tbl_users WHERE id=? FOR UPDATE');$userLock->execute([$userId]);
            if(!$userLock->fetchColumn())throw new InvalidArgumentException('User not found.');
            $user=$this->requireAssignableUser($userId);
            $eventLock=$this->db->prepare('SELECT title FROM tbl_events WHERE id=? FOR UPDATE');$eventLock->execute([$eventId]);$title=$eventLock->fetchColumn();
            if(!$title)throw new InvalidArgumentException('Event not found.');
            $statement = $this->db->prepare('DELETE FROM tbl_event_user WHERE user_id = ? AND event_id = ?');
            $statement->execute([$userId, $eventId]);
            if($statement->rowCount())$this->log($actorId, $userId, 'event_unassigned', $user['full_name']." was unassigned from $title.", $eventId);
            $this->db->commit();
        } catch (Throwable $error) {
            if($this->db->inTransaction())$this->db->rollBack();
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

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

$actor=AuthGuard::requireRole('SBO Adviser'); $repo=new UserManagementRepository((new Database())->connection());
try {
    if($_SERVER['REQUEST_METHOD']==='GET') JsonResponse::send(['success'=>true,'data'=>$repo->index($_GET)]);
    if($_SERVER['REQUEST_METHOD']!=='POST') JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
    if(!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null)) JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
    $input=json_decode(file_get_contents('php://input'),true); if(!is_array($input))$input=$_POST; $action=$input['action']??'create';
    if($action==='toggle'){ $desired=array_key_exists('active',$input)?filter_var($input['active'],FILTER_VALIDATE_BOOL,FILTER_NULL_ON_FAILURE):null;if(array_key_exists('active',$input)&&$desired===null)throw new InvalidArgumentException('Choose a valid account status.');$repo->toggle((int)($input['id']??0),(int)$actor['id'],$desired);JsonResponse::send(['success'=>true,'message'=>'Account status updated.']); }
    if($action==='reset_password'){ $repo->resetPassword((int)($input['id']??0),(string)($input['password']??''),(string)($input['password_confirmation']??''),(int)$actor['id']); JsonResponse::send(['success'=>true,'message'=>'Password reset. The user must create a new password after signing in.']); }
    if($action==='assign_event'){ $repo->assignEvent((int)($input['id']??0),(int)($input['event_id']??0),(int)$actor['id']); JsonResponse::send(['success'=>true,'message'=>'Event assigned successfully.']); }
    if($action==='unassign_event'){ $repo->unassignEvent((int)($input['id']??0),(int)($input['event_id']??0),(int)$actor['id']); JsonResponse::send(['success'=>true,'message'=>'Event assignment removed.']); }
    if(!in_array($action,['create','update'],true)) JsonResponse::send(['success'=>false,'message'=>'Unknown user-management action.'],422);
    $id=$repo->save($input,(int)$actor['id'],$action==='update'?(int)($input['id']??0):null); JsonResponse::send(['success'=>true,'id'=>$id,'message'=>$action==='update'?'User updated successfully.':'User added. Share the temporary credentials securely; they must create a new password after signing in.']);
} catch(InvalidArgumentException $e){JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],422);} catch(Throwable $e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>'User management request failed.'],500);}
