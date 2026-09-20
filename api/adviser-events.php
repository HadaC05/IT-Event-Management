<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/AcademicPeriodScope.php';

final class EventValidationException extends InvalidArgumentException
{
    public function __construct(private readonly array $errors)
    {
        parent::__construct((string) (reset($errors)[0] ?? 'Please check the event form.'));
    }

    public function errors(): array
    {
        return $this->errors;
    }
}

final class EventManagementRepository
{
    private const PER_PAGE = 20;

    public function __construct(private readonly PDO $db)
    {
    }

    public function index(array $filters): array
    {
        $this->synchronizeStatuses();
        $search = trim((string) ($filters['search'] ?? ''));
        $status = (int) ($filters['status'] ?? 0);
        $type = (int) ($filters['event_type'] ?? 0);
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = PageSize::from($filters, self::PER_PAGE);
        if (mb_strlen($search) > 100) {
            throw new EventValidationException(['search' => ['Search may not exceed 100 characters.']]);
        }
        if ($status && !$this->exists('tbl_event_statuses', $status)) {
            throw new EventValidationException(['status' => ['Select a valid event status.']]);
        }
        if ($type && !$this->exists('tbl_event_types', $type)) {
            throw new EventValidationException(['event_type' => ['Select a valid event type.']]);
        }

        $archivedId = (int) $this->scalar("SELECT id FROM tbl_event_statuses WHERE label='archived'");
        $where = [$status === $archivedId ? 'e.event_status_id=:status' : 'e.deleted_at IS NULL'];
        $params = [];
        if ($status && $status !== $archivedId) {
            $where[] = 'e.event_status_id=:status';
        }
        if ($status) {
            $params['status'] = $status;
        }
        if ($type) {
            $where[] = 'e.event_type_id=:type';
            $params['type'] = $type;
        }
        if ($search !== '') {
            $where[] = "(e.title LIKE :search_title ESCAPE '\\\\' OR e.description LIKE :search_description ESCAPE '\\\\' OR e.location LIKE :search_location ESCAPE '\\\\')";
            $term = '%'.addcslashes($search, '%_\\').'%';
            $params['search_title'] = $term;
            $params['search_description'] = $term;
            $params['search_location'] = $term;
        }
        $whereSql = 'WHERE '.implode(' AND ', $where);
        $count = $this->db->prepare("SELECT COUNT(*) FROM tbl_events e $whereSql");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $query = $this->db->prepare(
            "SELECT e.*,et.label type_label,es.label status_label,ap.term_name academic_term_name,sy.label school_year_label
             FROM tbl_events e
             LEFT JOIN tbl_event_types et ON et.id=e.event_type_id
             LEFT JOIN tbl_event_statuses es ON es.id=e.event_status_id
             LEFT JOIN tbl_academic_periods ap ON ap.id=e.academic_period_id
             LEFT JOIN tbl_school_years sy ON sy.id=ap.school_year_id
             $whereSql
             ORDER BY CASE es.label
                        WHEN 'ongoing' THEN 0
                        WHEN 'upcoming' THEN 1
                        WHEN 'completed' THEN 2
                        ELSE 3
                      END,
                      CASE WHEN e.deleted_at IS NOT NULL THEN 1 ELSE 0 END,
                      e.start_at,e.id
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $value) {
            $query->bindValue(':'.$key, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();
        $events = $query->fetchAll();
        foreach ($events as &$event) {
            $event = $this->normalizeEvent($event);
            $event['event_locations'] = $this->eventLocations((int) $event['id']);
            $event['location_ids'] = array_column($event['event_locations'], 'id');
            $event['assigned_users'] = $this->assignedUsers((int) $event['id']);
        }
        unset($event);

        return [
            'events' => $events,
            'metadata' => $this->metadata(),
            'pagination' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total ? $offset + 1 : null,
                'to' => $total ? min($offset + $perPage, $total) : null,
            ],
        ];
    }

    public function details(int $id): array
    {
        $this->synchronizeStatuses();
        $statement = $this->db->prepare(
            'SELECT e.*,et.label type_label,es.label status_label,ap.term_name academic_term_name,sy.label school_year_label,
                    u.first_name creator_first_name,u.middle_name creator_middle_name,u.last_name creator_last_name
             FROM tbl_events e
             LEFT JOIN tbl_event_types et ON et.id=e.event_type_id
             LEFT JOIN tbl_event_statuses es ON es.id=e.event_status_id
             LEFT JOIN tbl_academic_periods ap ON ap.id=e.academic_period_id
             LEFT JOIN tbl_school_years sy ON sy.id=ap.school_year_id
             LEFT JOIN tbl_users u ON u.id=e.created_by WHERE e.id=?'
        );
        $statement->execute([$id]);
        $event = $statement->fetch();
        if (!$event) {
            throw new EventValidationException(['event' => ['Event not found.']]);
        }
        $event = $this->normalizeEvent($event);
        $event['creator_name'] = $this->fullName($event, 'creator_') ?: 'System';
        $event['assigned_users'] = $this->assignedUsers($id);
        $event['activities'] = $this->activities($id);
        $event['attendance_schedules'] = $this->schedules($id);
        $event['event_locations'] = $this->eventLocations($id);
        $event['location_ids'] = array_column($event['event_locations'], 'id');
        $event['audience_year_level_ids'] = $this->pivotIds('tbl_event_year_level', 'year_level_id', $id);
        $event['audience_team_ids'] = $this->pivotIds('tbl_event_team', 'team_id', $id);
        $event['participant_ids'] = $this->pivotIds('tbl_event_participants', 'user_id', $id);
        $event['expected_participants'] = $this->expectedParticipants($event);
        $metadata = $this->metadata();
        $assigned = array_column($event['assigned_users'], 'id');
        $metadata['available_users'] = array_values(array_filter(
            $metadata['assignable_users'],
            static fn (array $user): bool => !in_array($user['id'], $assigned, true)
        ));
        return ['event' => $event, 'metadata' => $metadata];
    }

    private function activities(int $eventId): array
    {
        $statement = $this->db->prepare('SELECT ea.id,a.label AS name,a.description,ea.status FROM tbl_event_activities ea INNER JOIN tbl_activities a ON a.id=ea.activity_id WHERE ea.event_id=? ORDER BY ea.id');
        $statement->execute([$eventId]);
        $activities = $statement->fetchAll();
        foreach ($activities as &$activity) $activity['id'] = (int) $activity['id'];
        unset($activity);
        return $activities;
    }

    public function saveActivity(int $eventId, array $input, int $actorId, ?int $activityId = null): void
    {
        $event = $this->eventRow($eventId);
        $name = trim((string) ($input['name'] ?? ''));
        $description = trim((string) ($input['description'] ?? ''));
        $errors = [];
        if ($name === '' || mb_strlen($name) > 120) $errors['name'] = ['Activity name is required and may not exceed 120 characters.'];
        if (mb_strlen($description) > 2000) $errors['description'] = ['Description may not exceed 2,000 characters.'];
        if ($errors) throw new EventValidationException($errors);
        if ($activityId !== null) {
            $activity = $this->db->prepare('SELECT id FROM tbl_event_activities WHERE id=? AND event_id=?');
            $activity->execute([$activityId, $eventId]);
            if (!$activity->fetch()) throw new EventValidationException(['activity' => ['Activity not found for this event.']]);
        }
        $this->db->beginTransaction();
        try {
            $eventLock = $this->db->prepare('SELECT id,event_type_id FROM tbl_events WHERE id=? AND deleted_at IS NULL FOR UPDATE');
            $eventLock->execute([$eventId]);
            $lockedEvent = $eventLock->fetch();
            if (!$lockedEvent) throw new EventValidationException(['event' => ['Event not found.']]);
            if ($lockedEvent['event_type_id'] === null) throw new EventValidationException(['event_type' => ['Assign an event type before adding an activity.']]);
            $duplicate = $this->db->prepare('SELECT id FROM tbl_event_activities WHERE event_id=? AND LOWER(name)=LOWER(?)'.($activityId === null ? '' : ' AND id<>?'));
            $duplicate->execute($activityId === null ? [$eventId, $name] : [$eventId, $name, $activityId]);
            if ($duplicate->fetch()) throw new EventValidationException(['name' => ['An activity with this name already exists for this event.']]);
            if ($activityId === null) {
                $catalog = $this->db->prepare("INSERT INTO tbl_activities(label,description,event_type_id,status,created_at,updated_at) VALUES(?,?,?,'active',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE description=VALUES(description),status='active',updated_at=CURRENT_TIMESTAMP");
                $catalog->execute([$name, $description ?: null, $lockedEvent['event_type_id']]);
                $catalogId = (int) $this->scalar('SELECT id FROM tbl_activities WHERE event_type_id=? AND label=?', [(int) $lockedEvent['event_type_id'], $name]);
                $statement = $this->db->prepare("INSERT INTO tbl_event_activities(event_id,activity_id,name,status,created_by,created_at,updated_at) VALUES(?,?,?,'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
                $statement->execute([$eventId, $catalogId, $name, $actorId]);
            } else {
                $activityLock = $this->db->prepare('SELECT id FROM tbl_event_activities WHERE id=? AND event_id=? FOR UPDATE');
                $activityLock->execute([$activityId, $eventId]);
                if (!$activityLock->fetchColumn()) throw new EventValidationException(['activity' => ['Activity not found for this event.']]);
                $catalog = $this->db->prepare('UPDATE tbl_activities a INNER JOIN tbl_event_activities ea ON ea.activity_id=a.id SET a.label=?,a.description=?,a.updated_at=CURRENT_TIMESTAMP WHERE ea.id=? AND ea.event_id=?');
                $catalog->execute([$name, $description ?: null, $activityId, $eventId]);
                $statement = $this->db->prepare('UPDATE tbl_event_activities SET name=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND event_id=?');
                $statement->execute([$name, $activityId, $eventId]);
            }
            $this->log($actorId, $eventId, $activityId === null ? 'event_activity_created' : 'event_activity_updated', $name.' was '.($activityId === null ? 'added to ' : 'updated for ').$event['title'].'.');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function setActivityStatus(int $eventId, int $activityId, string $status, int $actorId): void
    {
        $event = $this->eventRow($eventId);
        if (!in_array($status, ['active', 'inactive'], true)) throw new EventValidationException(['status' => ['Invalid activity status.']]);
        $this->db->beginTransaction();
        try {
            $eventLock=$this->db->prepare('SELECT id,title FROM tbl_events WHERE id=? AND deleted_at IS NULL FOR UPDATE');$eventLock->execute([$eventId]);$lockedEvent=$eventLock->fetch();
            if(!$lockedEvent)throw new EventValidationException(['event'=>['Event not found.']]);
            $statement = $this->db->prepare('SELECT name,status FROM tbl_event_activities WHERE id=? AND event_id=? FOR UPDATE');
            $statement->execute([$activityId, $eventId]);
            $activity = $statement->fetch();
            if (!$activity) throw new EventValidationException(['activity' => ['Activity not found for this event.']]);
            if ((string) $activity['status'] === $status) { $this->db->commit(); return; }
            $this->db->prepare('UPDATE tbl_event_activities SET status=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND event_id=?')->execute([$status, $activityId, $eventId]);
            $this->log($actorId, $eventId, 'event_activity_status_updated', $activity['name'].' was marked '.$status.' for '.$lockedEvent['title'].'.');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function metadata(): array
    {
        $studentRole = (int) $this->scalar("SELECT id FROM tbl_roles WHERE name='Student'");
        $activeStatus = (int) $this->scalar("SELECT id FROM tbl_user_statuses WHERE label='active'");
        $students = $this->db->prepare(
            'SELECT u.id,u.id_number,u.first_name,u.middle_name,u.last_name,u.year_level,yl.label year_level_label
             FROM tbl_users u LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level
             WHERE u.role_id=? AND u.status=? ORDER BY u.last_name,u.first_name'
        );
        $students->execute([$studentRole, $activeStatus]);
        $studentRows = $students->fetchAll();
        foreach ($studentRows as &$student) {
            $student['id'] = (int) $student['id'];
            $student['year_level'] = $student['year_level'] === null ? null : (int) $student['year_level'];
            $student['full_name'] = $this->fullName($student);
        }
        unset($student);
        $levels = $this->db->query('SELECT id,label FROM tbl_year_levels ORDER BY id')->fetchAll();
        foreach ($levels as &$level) {
            $level['id'] = (int) $level['id'];
            $level['member_ids'] = array_values(array_map(
                static fn (array $student): int => $student['id'],
                array_filter($studentRows, static fn (array $student): bool => $student['year_level'] === $level['id'])
            ));
            $level['students_count'] = count($level['member_ids']);
        }
        unset($level);
        $teams = $this->db->query('SELECT id,name,school_year_id FROM tbl_teams WHERE is_active=1 ORDER BY name')->fetchAll();
        foreach ($teams as &$team) {
            $team['id'] = (int) $team['id'];
            $team['school_year_id'] = (int) $team['school_year_id'];
            $team['member_ids'] = $this->teamMemberIds($team['id']);
            $team['students_count'] = count($team['member_ids']);
        }
        unset($team);

        $assignable = $this->db->prepare(
            "SELECT u.id,u.first_name,u.middle_name,u.last_name,r.name role
             FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id JOIN tbl_user_statuses s ON s.id=u.status
             WHERE r.name IN ('SBO Adviser','Faculty') AND s.label='active'
             ORDER BY u.last_name,u.first_name"
        );
        $assignable->execute();
        $assignableRows = $assignable->fetchAll();
        foreach ($assignableRows as &$user) {
            $user['id'] = (int) $user['id'];
            $user['full_name'] = $this->fullName($user);
        }
        unset($user);

        $locations = $this->db->query("SELECT id,name,type,parent_location_id FROM tbl_locations ORDER BY type,name")->fetchAll();
        foreach ($locations as &$location) {
            $location['id'] = (int) $location['id'];
            $location['parent_location_id'] = $location['parent_location_id'] === null ? null : (int) $location['parent_location_id'];
        }
        unset($location);

        $periods = (new AcademicPeriodScope($this->db))->periods();
        return [
            'statuses' => $this->db->query('SELECT id,label FROM tbl_event_statuses ORDER BY id')->fetchAll(),
            'event_types' => $this->eventTypes(),
            'attendance_modes' => $this->db->query('SELECT id,code,name FROM tbl_attendance_session_modes ORDER BY id')->fetchAll(),
            'locations' => $locations,
            'assignable_users' => $assignableRows,
            'audience_teams' => $teams,
            'audience_year_levels' => $levels,
            'active_students' => $studentRows,
            'academic_periods' => $periods,
            'default_academic_period_id' => $periods[0]['id'] ?? null,
        ];
    }

    public function eventTypes(): array
    {
        $rows = $this->db->query('SELECT t.id,t.label,COUNT(e.id) events_count FROM tbl_event_types t LEFT JOIN tbl_events e ON e.event_type_id=t.id GROUP BY t.id,t.label ORDER BY t.label')->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['events_count'] = (int) $row['events_count'];
        }
        unset($row);
        return $rows;
    }

    public function saveEventType(array $input, ?int $typeId = null): int
    {
        $label = trim((string) ($input['label'] ?? ''));
        if ($label === '' || mb_strlen($label) > 255) throw new EventValidationException(['label' => ['Event type is required and may not exceed 255 characters.']]);
        if ($typeId !== null && !$this->exists('tbl_event_types', $typeId)) throw new EventValidationException(['event_type' => ['Event type not found.']]);
        $duplicate = $this->db->prepare('SELECT id FROM tbl_event_types WHERE LOWER(label)=LOWER(?)'.($typeId === null ? '' : ' AND id<>?'));
        $duplicate->execute($typeId === null ? [$label] : [$label, $typeId]);
        if ($duplicate->fetch()) throw new EventValidationException(['label' => ['That event type already exists.']]);
        try {
            if ($typeId === null) {
                $this->db->prepare('INSERT INTO tbl_event_types(label,created_at,updated_at) VALUES(?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([$label]);
                return (int) $this->db->lastInsertId();
            }
            $this->db->prepare('UPDATE tbl_event_types SET label=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$label, $typeId]);
            return $typeId;
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') throw new EventValidationException(['label' => ['That event type already exists.']]);
            throw $exception;
        }
    }

    public function deleteEventType(int $typeId): void
    {
        if (!$this->exists('tbl_event_types', $typeId)) throw new EventValidationException(['event_type' => ['Event type not found.']]);
        if ((int) $this->scalar('SELECT COUNT(*) FROM tbl_events WHERE event_type_id=?', [$typeId]) > 0) {
            throw new EventValidationException(['event_type' => ['This type is used by an event and cannot be deleted. Rename it instead.']]);
        }
        $this->db->prepare('DELETE FROM tbl_event_types WHERE id=?')->execute([$typeId]);
    }

    public function save(array $input, array $files, int $actorId, ?int $id = null): int
    {
        $existing = $id ? $this->eventRow($id) : null;
        $data = $this->validateEvent($input, $id);
        $scopeChanged = !$existing
            || (int)($existing['academic_period_id'] ?? 0) !== $data['academic_period_id']
            || (string)$existing['audience_type'] !== $data['audience_type']
            || $this->audienceSelectionChanged((int)$id, $data);
        if ($existing && $scopeChanged && $this->scopeHasOperationalHistory((int)$id)) {
            throw new EventValidationException(['academic_period_id' => ['The academic period or participant scope cannot change after attendance, scoring, posts, or officer responsibilities have been recorded.']]);
        }
        $data['event_status_id'] = $existing && $existing['deleted_at'] !== null
            ? (int)$existing['event_status_id']
            : $this->lifecycleStatusId($data['start_at'], $data['end_at']);
        $newPoster = $this->storePoster($files['poster'] ?? null);
        $posterPath = $newPoster ?: ($existing['poster_path'] ?? null);
        if ($id && !$newPoster && $this->boolean($input['remove_poster'] ?? false)) {
            $posterPath = null;
        }
        $oldPoster = $existing['poster_path'] ?? null;
        $this->db->beginTransaction();
        try {
            if ($id) {
                $statement = $this->db->prepare(
                    "UPDATE tbl_events SET title=?,description=?,location=?,location_id=?,attendance_location_policy=?,audience_type=?,academic_period_id=?,poster_path=?,
                     start_at=?,end_at=?,event_type_id=?,event_status_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?"
                );
                $statement->execute([$data['title'], $data['description'], $data['location'], $data['location_id'], $data['attendance_location_policy'], $data['audience_type'], $data['academic_period_id'], $posterPath,
                    $data['start_at'], $data['end_at'], $data['event_type_id'], $data['event_status_id'], $id]);
            } else {
                $statement = $this->db->prepare(
                    "INSERT INTO tbl_events(title,description,location,location_id,attendance_location_policy,audience_type,academic_period_id,poster_path,start_at,end_at,event_type_id,event_status_id,created_by,is_featured,created_at,updated_at)
                     VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)"
                );
                $statement->execute([$data['title'], $data['description'], $data['location'], $data['location_id'], $data['attendance_location_policy'], $data['audience_type'], $data['academic_period_id'], $posterPath,
                    $data['start_at'], $data['end_at'], $data['event_type_id'], $data['event_status_id'], $actorId]);
                $id = (int) $this->db->lastInsertId();
            }
            $this->replaceSchedules($id, $data['schedules']);
            $this->replaceEventLocations($id, $data['location_ids'], $data['location_id']);
            $this->replacePivot('tbl_event_user', 'user_id', $id, $data['assigned_user_ids']);
            $this->replacePivot('tbl_event_team', 'team_id', $id, $data['audience_type'] === 'selected_tribes' ? $data['tribe_ids'] : []);
            $this->replacePivot('tbl_event_year_level', 'year_level_id', $id, $data['audience_type'] === 'selected_year_levels' ? $data['year_level_ids'] : []);
            $this->replacePivot('tbl_event_participants', 'user_id', $id, $data['audience_type'] === 'specific_students' ? $data['participant_ids'] : []);
            $periodScope = new AcademicPeriodScope($this->db);
            if ($scopeChanged || $periodScope->snapshotCount($id) === 0) $periodScope->refreshMembershipSnapshot($id);
            $this->log($actorId, $id, $existing ? 'event_updated' : 'event_created', $data['title'].($existing ? ' was updated.' : ' was created.'));
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            if ($newPoster) {
                $this->deletePoster($newPoster);
            }
            throw $exception;
        }
        if ($oldPoster && $oldPoster !== $posterPath) {
            $this->deletePoster($oldPoster);
        }
        return $id;
    }

    public function conflicts(array $input): array
    {
        $start = $this->dateTime((string) ($input['start_date'] ?? ''), (string) ($input['start_time'] ?? ''));
        $end = $this->dateTime((string) ($input['end_date'] ?? ''), (string) ($input['end_time'] ?? ''));
        if (!$start || !$end || $end <= $start) {
            return ['has_conflicts' => false, 'conflicts' => ['location' => [], 'people' => []]];
        }
        $generalLocationId = (int) ($input['general_location_id'] ?? 0);
        $specificLocationId = (int) ($input['specific_location_id'] ?? 0);
        $locationId = $specificLocationId ?: $generalLocationId;
        $locationIds = array_values(array_unique(array_merge(
            $this->integerList($input['location_ids'] ?? []),
            $locationId > 0 ? [$locationId] : []
        )));
        $locationName = '';
        if ($locationId) {
            $locationName = (string) ($this->scalar('SELECT name FROM tbl_locations WHERE id=?', [$locationId]) ?: '');
        }
        $eventId = (int) ($input['event_id'] ?? 0);
        $users = $this->integerList($input['assigned_user_ids'] ?? []);
        $params = [$end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')];
        $sql = 'SELECT id,title,location,location_id,start_at,end_at FROM tbl_events WHERE deleted_at IS NULL AND start_at < ? AND end_at > ?';
        if ($eventId) {
            $sql .= ' AND id<>?';
            $params[] = $eventId;
        }
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        $locationConflicts = [];
        $peopleConflicts = [];
        foreach ($statement->fetchAll() as $event) {
            $schedule = $this->scheduleLabel($event['start_at'], $event['end_at']);
            $savedLocationIds = [(int) ($event['location_id'] ?? 0)];
            if ($event['id']) {
                $savedLocationIds = array_values(array_unique(array_merge($savedLocationIds, array_map(
                    'intval',
                    array_column($this->eventLocations((int) $event['id']), 'id')
                ))));
            }
            $sharedLocationIds = array_values(array_intersect($locationIds, $savedLocationIds));
            $sameLocation = $sharedLocationIds !== [];
            $sameLegacyLocation = !$event['location_id'] && $locationName !== '' && mb_strtolower(trim((string) $event['location'])) === mb_strtolower($locationName);
            if ($sameLocation || $sameLegacyLocation) {
                $conflictLocation = $locationName;
                if ($sharedLocationIds) {
                    $marks = implode(',', array_fill(0, count($sharedLocationIds), '?'));
                    $locationNames = $this->db->prepare("SELECT name FROM tbl_locations WHERE id IN ($marks) ORDER BY name");
                    $locationNames->execute($sharedLocationIds);
                    $conflictLocation = implode(', ', $locationNames->fetchAll(PDO::FETCH_COLUMN));
                }
                $locationConflicts[] = ['id' => (int) $event['id'], 'title' => $event['title'], 'location' => $conflictLocation, 'schedule' => $schedule];
            }
            if ($users) {
                $marks = implode(',', array_fill(0, count($users), '?'));
                $assigned = $this->db->prepare("SELECT u.first_name,u.middle_name,u.last_name FROM tbl_event_user eu JOIN tbl_users u ON u.id=eu.user_id WHERE eu.event_id=? AND eu.user_id IN ($marks)");
                $assigned->execute(array_merge([(int) $event['id']], $users));
                $names = array_map(fn (array $user): string => $this->fullName($user), $assigned->fetchAll());
                if ($names) {
                    $peopleConflicts[] = ['id' => (int) $event['id'], 'title' => $event['title'], 'people' => $names, 'schedule' => $schedule];
                }
            }
        }
        return ['has_conflicts' => $locationConflicts !== [] || $peopleConflicts !== [], 'conflicts' => ['location' => $locationConflicts, 'people' => $peopleConflicts]];
    }

    public function setStatus(int $id, int $statusId, int $actorId): void
    {
        $this->eventRow($id);
        throw new EventValidationException([
            'event_status_id' => ['Event lifecycle status is determined automatically from its schedule. Archive the event if it should leave active views.'],
        ]);
    }

    public function archive(int $id, int $actorId): void
    {
        $event = $this->eventRow($id);
        $archived = (int) $this->scalar("SELECT id FROM tbl_event_statuses WHERE label='archived'");
        $this->db->beginTransaction();
        try {
            $lock=$this->db->prepare('SELECT event_status_id,deleted_at FROM tbl_events WHERE id=? FOR UPDATE');$lock->execute([$id]);$current=$lock->fetch();
            if(!$current)throw new EventValidationException(['event'=>['Event not found.']]);
            if($current['deleted_at']!==null){$this->db->commit();return;}
            $this->db->prepare('UPDATE tbl_events SET event_status_id=?,deleted_at=CURRENT_TIMESTAMP,is_featured=0,featured_order=NULL,featured_until=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$archived,$id]);
            $this->log($actorId,$id,'event_archived',$event['title'].' was archived.');
            $this->db->commit();
        } catch (Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $exception; }
    }

    public function restore(int $id, int $actorId): void
    {
        $event = $this->eventRow($id);
        $this->db->beginTransaction();
        try {
            $lock=$this->db->prepare('SELECT deleted_at,event_status_id,start_at,end_at FROM tbl_events WHERE id=? FOR UPDATE');$lock->execute([$id]);$current=$lock->fetch();
            if(!$current)throw new EventValidationException(['event'=>['Event not found.']]);
            $lifecycleStatus = $this->lifecycleStatusId((string)$current['start_at'], (string)$current['end_at']);
            if($current['deleted_at']===null&&(int)$current['event_status_id']===$lifecycleStatus){$this->db->commit();return;}
            $this->db->prepare('UPDATE tbl_events SET event_status_id=?,deleted_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$lifecycleStatus, $id]);
            $this->log($actorId, $id, 'event_restored', $event['title'].' was restored.');
            $this->db->commit();
        } catch (Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $exception; }
    }

    public function forceDelete(int $id): void
    {
        $posterPath = null;
        $this->db->beginTransaction();
        try {
            $eventLock=$this->db->prepare('SELECT poster_path,deleted_at FROM tbl_events WHERE id=? FOR UPDATE');$eventLock->execute([$id]);$event=$eventLock->fetch();
            if(!$event)throw new EventValidationException(['event'=>['Event not found.']]);
            if($event['deleted_at']===null)throw new EventValidationException(['event'=>['Archive the event before permanently deleting it.']]);
            $posterPath=$event['poster_path'];
            foreach (['tbl_event_user', 'tbl_event_team', 'tbl_event_year_level', 'tbl_event_participants', 'tbl_event_attendance_schedules'] as $table) {
                $this->db->prepare("DELETE FROM $table WHERE event_id=?")->execute([$id]);
            }
            $this->db->prepare('DELETE FROM tbl_activity_logs WHERE event_id=?')->execute([$id]);
            $this->db->prepare('DELETE FROM tbl_events WHERE id=?')->execute([$id]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if($this->db->inTransaction())$this->db->rollBack();
            throw $exception;
        }
        if ($posterPath) {
            $this->deletePoster($posterPath);
        }
    }

    public function feature(int $id, array $input, int $actorId): void
    {
        $event = $this->eventRow($id);
        $featured = $this->boolean($input['is_featured'] ?? false);
        $status = (string) $this->scalar('SELECT label FROM tbl_event_statuses WHERE id=?', [(int) $event['event_status_id']]);
        if ($featured && (!in_array($status, ['upcoming', 'ongoing'], true) || strtotime($event['end_at']) < time())) {
            throw new EventValidationException(['is_featured' => ['Only upcoming or ongoing events can be featured.']]);
        }
        $order = ($input['featured_order'] ?? '') === '' ? null : (int) $input['featured_order'];
        if ($featured && $order !== null && ($order < 1 || $order > 999)) {
            throw new EventValidationException(['featured_order' => ['Featured order must be between 1 and 999.']]);
        }
        $untilInput = trim((string) ($input['featured_until'] ?? ''));
        $untilDate = $untilInput === '' ? null : DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $untilInput);
        if ($featured && $untilInput !== '' && (!$untilDate || $untilDate->format('Y-m-d\TH:i') !== $untilInput)) {
            throw new EventValidationException(['featured_until' => ['Choose a valid featured-until date and time.']]);
        }
        if ($featured && $untilDate && ($untilDate < new DateTimeImmutable() || $untilDate > new DateTimeImmutable((string) $event['end_at']))) {
            throw new EventValidationException(['featured_until' => ['Featured-until must be in the future and no later than the event end.']]);
        }
        $until = $untilDate?->format('Y-m-d H:i:s');
        $this->db->beginTransaction();
        try {
            $lock=$this->db->prepare('SELECT is_featured,featured_order,featured_until FROM tbl_events WHERE id=? FOR UPDATE');$lock->execute([$id]);$current=$lock->fetch();
            if(!$current)throw new EventValidationException(['event'=>['Event not found.']]);
            $same=(bool)$current['is_featured']===$featured
                &&($featured?($current['featured_order']===null?null:(int)$current['featured_order']):null)===($featured?$order:null)
                &&($featured?($current['featured_until']?:null):null)===($featured?$until:null);
            if($same){$this->db->commit();return;}
            $this->db->prepare('UPDATE tbl_events SET is_featured=?,featured_order=?,featured_until=?,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$featured ? 1 : 0, $featured ? $order : null, $featured ? $until : null, $id]);
            $this->log($actorId, $id, $featured ? 'event_featured' : 'event_unfeatured', $event['title'].($featured ? ' was added to the featured carousel.' : ' was removed from the featured carousel.'));
            $this->db->commit();
        } catch (Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $exception; }
    }

    public function assign(int $eventId, int $userId, int $actorId): void
    {
        $this->db->beginTransaction();
        try {
            $eventLock=$this->db->prepare("SELECT e.id,e.title,e.deleted_at,s.label status FROM tbl_events e LEFT JOIN tbl_event_statuses s ON s.id=e.event_status_id WHERE e.id=? FOR UPDATE");$eventLock->execute([$eventId]);$event=$eventLock->fetch();
            if(!$event)throw new EventValidationException(['event'=>['Event not found.']]);
            if($event['deleted_at']!==null||$event['status']==='archived')throw new EventValidationException(['user_id'=>['Users cannot be assigned while this event is archived.']]);
            $valid=$this->db->prepare("SELECT u.id,u.first_name,u.middle_name,u.last_name FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id JOIN tbl_user_statuses s ON s.id=u.status WHERE u.id=? AND r.name IN ('SBO Adviser','Faculty') AND s.label='active' FOR UPDATE");$valid->execute([$userId]);$user=$valid->fetch();
            if(!$user)throw new EventValidationException(['user_id'=>['Select an active SBO Adviser or Faculty member.']]);
            $statement = $this->db->prepare('INSERT IGNORE INTO tbl_event_user(event_id,user_id,created_at,updated_at) VALUES(?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
            $statement->execute([$eventId, $userId]);
            if ($statement->rowCount()) $this->log($actorId, $eventId, 'event_assigned', $this->fullName($user).' was assigned to '.$event['title'].'.', $userId);
            $this->db->commit();
        } catch (Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $exception; }
    }

    public function unassign(int $eventId, int $userId, int $actorId): void
    {
        $this->db->beginTransaction();
        try {
            $eventLock=$this->db->prepare('SELECT id,title FROM tbl_events WHERE id=? FOR UPDATE');$eventLock->execute([$eventId]);$event=$eventLock->fetch();
            if(!$event)throw new EventValidationException(['event'=>['Event not found.']]);
            $nameStatement=$this->db->prepare('SELECT first_name,middle_name,last_name FROM tbl_users WHERE id=? FOR UPDATE');$nameStatement->execute([$userId]);$user=$nameStatement->fetch();
            $statement=$this->db->prepare('DELETE FROM tbl_event_user WHERE event_id=? AND user_id=?');$statement->execute([$eventId, $userId]);
            if ($user && $statement->rowCount()) $this->log($actorId, $eventId, 'event_unassigned', $this->fullName($user).' was unassigned from '.$event['title'].'.', $userId);
            $this->db->commit();
        } catch (Throwable $exception) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $exception; }
    }

    private function validateEvent(array $input, ?int $id): array
    {
        $errors = [];
        $title = trim((string) ($input['title'] ?? ''));
        $description = trim((string) ($input['description'] ?? '')) ?: null;
        $generalLocationId = (int) ($input['general_location_id'] ?? 0);
        $specificLocationId = (int) ($input['specific_location_id'] ?? 0);
        $locationRow = null;
        if ($generalLocationId > 0) {
            $statement = $this->db->prepare("SELECT id,name,type,parent_location_id FROM tbl_locations WHERE id=? AND type='general'");
            $statement->execute([$generalLocationId]);
            $locationRow = $statement->fetch() ?: null;
        }
        if (!$locationRow) {
            $errors['general_location_id'][] = 'Select a valid general location.';
        }
        if ($specificLocationId > 0) {
            $statement = $this->db->prepare("SELECT id,name,type,parent_location_id FROM tbl_locations WHERE id=? AND type='specific' AND parent_location_id=?");
            $statement->execute([$specificLocationId, $generalLocationId]);
            $specificLocation = $statement->fetch() ?: null;
            if (!$specificLocation) {
                $errors['specific_location_id'][] = 'Select a specific location inside the chosen general location.';
            } else {
                $locationRow = $specificLocation;
            }
        }
        $location = (string) ($locationRow['name'] ?? '');
        $locationId = isset($locationRow['id']) ? (int) $locationRow['id'] : 0;
        $locationIds = array_values(array_unique(array_merge(
            $this->integerList($input['location_ids'] ?? []),
            $locationId > 0 ? [$locationId] : []
        )));
        if ($locationIds) {
            $marks = implode(',', array_fill(0, count($locationIds), '?'));
            $statement = $this->db->prepare("SELECT id FROM tbl_locations WHERE id IN ($marks)");
            $statement->execute($locationIds);
            $validLocationIds = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
            if (count($validLocationIds) !== count($locationIds)) {
                $errors['location_ids'][] = 'Select only valid saved event locations.';
            }
        }
        $locationPolicy = (string) ($input['attendance_location_policy'] ?? 'warning');
        if (!in_array($locationPolicy, ['warning','strict'], true)) $errors['attendance_location_policy'][] = 'Choose a valid scan location policy.';
        if (in_array($locationPolicy, ['warning','strict'], true) && $locationId > 0) {
            $marks = implode(',', array_fill(0, count($locationIds), '?'));
            $venue = $this->db->prepare("SELECT COUNT(*) FROM tbl_locations WHERE id IN ($marks) AND (latitude IS NULL OR longitude IS NULL OR radius IS NULL)");
            $venue->execute($locationIds);
            if ((int) $venue->fetchColumn() > 0)
                $errors['attendance_location_policy'][] = 'Every selected event location requires coordinates and a box radius for attendance scanning.';
        }
        $typeId = (int) ($input['event_type_id'] ?? 0);
        $audienceType = (string) ($input['audience_type'] ?? 'all_students');
        $academicPeriodId = (int)($input['academic_period_id'] ?? 0);
        if ($title === '' || mb_strlen($title) > 255) $errors['title'][] = 'Event name is required and may not exceed 255 characters.';
        if ($description !== null && mb_strlen($description) > 5000) $errors['description'][] = 'Description may not exceed 5,000 characters.';
        if (!$typeId || !$this->exists('tbl_event_types', $typeId)) $errors['event_type_id'][] = 'Select a valid event type.';
        if (!in_array($audienceType, ['all_students', 'selected_tribes', 'selected_year_levels', 'specific_students'], true)) $errors['audience_type'][] = 'Select a valid participant group.';
        try {$period = (new AcademicPeriodScope($this->db))->period($academicPeriodId);} catch (InvalidArgumentException) {$period = null;$errors['academic_period_id'][] = 'Select a valid academic period.';}
        $schedules = $this->validateSchedules($input['attendance_days'] ?? [], $errors, $id);
        $startAt = $schedules ? $schedules[0]['start_at'] : '';
        $endAt = $schedules ? $schedules[array_key_last($schedules)]['end_at'] : '';
        $assigned = $this->integerList($input['assigned_user_ids'] ?? []);
        $this->validateAssignable($assigned, $errors);
        $tribes = $this->integerList($input['tribe_ids'] ?? []);
        $yearLevels = $this->integerList($input['year_level_ids'] ?? []);
        $participants = $this->integerList($input['participant_ids'] ?? []);
        if ($audienceType === 'selected_tribes') $this->validateAudienceIds('tbl_teams', $tribes, 'tribe_ids', $errors, 'is_active=1'.($period?' AND school_year_id='.(int)$period['school_year_id']:''));
        if ($audienceType === 'selected_year_levels') $this->validateAudienceIds('tbl_year_levels', $yearLevels, 'year_level_ids', $errors);
        if ($audienceType === 'specific_students') $this->validateStudents($participants, $errors);
        if ($startAt && $endAt && !$this->boolean($input['acknowledge_conflicts'] ?? false)) {
            $conflicts = $this->conflicts([
                'start_date' => substr($startAt, 0, 10), 'start_time' => substr($startAt, 11, 5),
                'end_date' => substr($endAt, 0, 10), 'end_time' => substr($endAt, 11, 5),
                'general_location_id' => $generalLocationId, 'specific_location_id' => $specificLocationId,
                'location_ids' => $locationIds,
                'assigned_user_ids' => $assigned, 'event_id' => $id,
            ]);
            if ($conflicts['conflicts']['location']) $errors['general_location_id'][] = 'Possible scheduling conflict at this location. Review and confirm the warning to continue.';
            if ($conflicts['conflicts']['people']) $errors['assigned_user_ids'][] = 'An Event-in-Charge has a scheduling conflict. Review and confirm the warning to continue.';
        }
        if ($id) $this->eventRow($id);
        if ($errors) throw new EventValidationException($errors);
        return [
            'title' => $title, 'description' => $description, 'location' => $location, 'location_id' => $locationId, 'location_ids' => $locationIds,
            'attendance_location_policy' => $locationPolicy, 'event_type_id' => $typeId,
            'audience_type' => $audienceType, 'academic_period_id' => $academicPeriodId, 'start_at' => $startAt, 'end_at' => $endAt,
            'schedules' => $schedules, 'assigned_user_ids' => $assigned, 'tribe_ids' => $tribes,
            'year_level_ids' => $yearLevels, 'participant_ids' => $participants,
        ];
    }

    private function validateSchedules(mixed $input, array &$errors, ?int $eventId = null): array
    {
        if (!is_array($input) || !$input) {
            $errors['attendance_days'][] = 'Add at least one event day.';
            return [];
        }
        if (count($input) > 31) $errors['attendance_days'][] = 'An event schedule cannot exceed 31 days.';
        $modes = [];
        foreach ($this->db->query('SELECT id,code FROM tbl_attendance_session_modes') as $mode) $modes[(int) $mode['id']] = $mode['code'];
        $existing = [];
        $existingByDate = [];
        if ($eventId) {
            $statement = $this->db->prepare('SELECT id,schedule_date FROM tbl_event_attendance_schedules WHERE event_id=?');
            $statement->execute([$eventId]);
            foreach ($statement->fetchAll() as $saved) {
                $existing[(int) $saved['id']] = (string) $saved['schedule_date'];
                $existingByDate[(string) $saved['schedule_date']] = (int) $saved['id'];
            }
        }
        $rows = [];
        $dates = [];
        $usedIds = [];
        foreach (array_values($input) as $index => $day) {
            $field = 'attendance_days.'.$index;
            $date = trim((string) ($day['date'] ?? ''));
            $scheduleId = (int) ($day['id'] ?? 0);
            $modeId = (int) ($day['attendance_session_mode_id'] ?? 0);
            if ($scheduleId && (!isset($existing[$scheduleId]) || in_array($scheduleId, $usedIds, true)))
                $errors[$field.'.id'][] = 'This schedule no longer belongs to the event. Refresh and try again.';
            if ($scheduleId && isset($existingByDate[$date]) && $existingByDate[$date] !== $scheduleId)
                $errors[$field.'.date'][] = 'This date belongs to another existing event day. Edit that day instead.';
            if ($scheduleId) $usedIds[] = $scheduleId;
            if (!$this->validDate($date) || ($date < date('Y-m-d') && ($scheduleId === 0 || ($existing[$scheduleId] ?? null) !== $date)))
                $errors[$field.'.date'][] = 'Choose today or a future date, or keep an existing past day unchanged.';
            if ($scheduleId && isset($existing[$scheduleId]) && $existing[$scheduleId] !== $date &&
                ((int) $this->scalar('SELECT COUNT(*) FROM tbl_attendance_entries WHERE event_schedule_id=?', [$scheduleId]) > 0 ||
                 (int) $this->scalar('SELECT COUNT(*) FROM tbl_sbo_event_assignments WHERE event_schedule_id=?', [$scheduleId]) > 0 ||
                 (int) $this->scalar('SELECT COUNT(*) FROM tbl_attendances WHERE event_id=? AND attendance_date=?', [$eventId, $existing[$scheduleId]]) > 0))
                $errors[$field.'.date'][] = 'A day with scans or SBO assignments cannot be moved to another date.';
            if (in_array($date, $dates, true)) $errors[$field.'.date'][] = 'Each schedule date must be unique.';
            $dates[] = $date;
            if (!isset($modes[$modeId])) $errors[$field.'.attendance_session_mode_id'][] = 'Select a valid attendance session.';
            $code = $modes[$modeId] ?? 'none';
            $times = [
                'morning_in' => trim((string) ($day['morning_in'] ?? '')),
                'morning_in_close' => trim((string) ($day['morning_in_close'] ?? '')),
                'morning_out_open' => trim((string) ($day['morning_out_open'] ?? '')),
                'morning_out' => trim((string) ($day['morning_out'] ?? '')),
                'afternoon_in' => trim((string) ($day['afternoon_in'] ?? '')),
                'afternoon_in_close' => trim((string) ($day['afternoon_in_close'] ?? '')),
                'afternoon_out_open' => trim((string) ($day['afternoon_out_open'] ?? '')),
                'afternoon_out' => trim((string) ($day['afternoon_out'] ?? '')),
            ];
            $activeKeys = $code === 'two_sessions' ? array_keys($times) : ($code === 'whole_day' ? ['morning_in', 'morning_in_close', 'morning_out_open', 'morning_out'] : []);
            foreach ($activeKeys as $key) if (!$this->validTime($times[$key])) $errors[$field.'.'.$key][] = 'Enter a valid checkpoint time.';
            if (!$activeKeys && implode('', $times) !== '') $errors[$field.'.morning_in'][] = 'Remove checkpoint times when attendance scanning is off.';
            $activeTimes = array_map(static fn (string $key): string => $times[$key], $activeKeys);
            for ($i = 1; $i < count($activeTimes); $i++) {
                $allowBoundary = in_array($activeKeys[$i], ['morning_out_open', 'afternoon_in', 'afternoon_out_open'], true);
                if ($activeTimes[$i] < $activeTimes[$i - 1] || (!$allowBoundary && $activeTimes[$i] === $activeTimes[$i - 1]))
                    $errors[$field.'.'.$activeKeys[$i]][] = 'Scan windows must be chronological and may not overlap.';
            }
            $startTime = $code === 'none' ? '00:00' : ($times['morning_in'] ?: '00:00');
            $endTime = $code === 'two_sessions' ? ($times['afternoon_out'] ?: '00:00') : ($code === 'whole_day' ? ($times['morning_out'] ?: '00:00') : '23:59');
            $rows[] = [
                'id' => $scheduleId ?: null, 'schedule_date' => $date, 'attendance_session_mode_id' => $modeId,
                'whole_day_in_time' => $code === 'whole_day' ? $times['morning_in'] : null,
                'whole_day_in_close_time' => $code === 'whole_day' ? $times['morning_in_close'] : null,
                'whole_day_out_open_time' => $code === 'whole_day' ? $times['morning_out_open'] : null,
                'whole_day_out_time' => $code === 'whole_day' ? $times['morning_out'] : null,
                'morning_in_time' => $code === 'two_sessions' ? $times['morning_in'] : null,
                'morning_in_close_time' => $code === 'two_sessions' ? $times['morning_in_close'] : null,
                'morning_out_open_time' => $code === 'two_sessions' ? $times['morning_out_open'] : null,
                'morning_out_time' => $code === 'two_sessions' ? $times['morning_out'] : null,
                'afternoon_in_time' => $code === 'two_sessions' ? $times['afternoon_in'] : null,
                'afternoon_in_close_time' => $code === 'two_sessions' ? $times['afternoon_in_close'] : null,
                'afternoon_out_open_time' => $code === 'two_sessions' ? $times['afternoon_out_open'] : null,
                'afternoon_out_time' => $code === 'two_sessions' ? $times['afternoon_out'] : null,
                'start_at' => $date.' '.$startTime.':00', 'end_at' => $date.' '.$endTime.':00',
            ];
        }
        usort($rows, static fn (array $a, array $b): int => strcmp($a['schedule_date'], $b['schedule_date']));
        if ($rows && (strtotime(end($rows)['schedule_date']) - strtotime($rows[0]['schedule_date'])) / 86400 > 30) $errors['attendance_days'][] = 'An event schedule cannot exceed 31 days.';
        return $rows;
    }

    private function validateAssignable(array $ids, array &$errors): void
    {
        if (!$ids) return;
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->prepare("SELECT COUNT(*) FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id JOIN tbl_user_statuses s ON s.id=u.status WHERE u.id IN ($marks) AND r.name IN ('SBO Adviser','Faculty') AND s.label='active'");
        $statement->execute($ids);
        if ((int) $statement->fetchColumn() !== count($ids)) $errors['assigned_user_ids'][] = 'Only active SBO Adviser and Faculty users can be Event-in-Charge.';
    }

    private function validateAudienceIds(string $table, array $ids, string $field, array &$errors, string $condition = '1=1'): void
    {
        if (!$ids) {
            $errors[$field][] = 'Select at least one option.';
            return;
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->prepare("SELECT COUNT(*) FROM $table WHERE id IN ($marks) AND $condition");
        $statement->execute($ids);
        if ((int) $statement->fetchColumn() !== count($ids)) $errors[$field][] = 'One or more selected options are invalid.';
    }

    private function validateStudents(array $ids, array &$errors): void
    {
        if (!$ids) {
            $errors['participant_ids'][] = 'Select at least one student.';
            return;
        }
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->prepare("SELECT COUNT(*) FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id JOIN tbl_user_statuses s ON s.id=u.status WHERE u.id IN ($marks) AND r.name='Student' AND s.label='active'");
        $statement->execute($ids);
        if ((int) $statement->fetchColumn() !== count($ids)) $errors['participant_ids'][] = 'Only active students can be selected as participants.';
    }

    private function replaceSchedules(int $eventId, array $schedules): void
    {
        $stored = $this->db->prepare('SELECT id,schedule_date FROM tbl_event_attendance_schedules WHERE event_id=? FOR UPDATE');
        $stored->execute([$eventId]);
        $existingById = [];
        $existingByDate = [];
        foreach ($stored->fetchAll() as $row) {
            $existingById[(int) $row['id']] = $row;
            $existingByDate[(string) $row['schedule_date']] = (int) $row['id'];
        }
        $used = [];
        $update = $this->db->prepare(
            'UPDATE tbl_event_attendance_schedules SET schedule_date=?,attendance_session_mode_id=?,whole_day_in_time=?,whole_day_in_close_time=?,whole_day_out_open_time=?,whole_day_out_time=?,morning_in_time=?,morning_in_close_time=?,morning_out_open_time=?,morning_out_time=?,afternoon_in_time=?,afternoon_in_close_time=?,afternoon_out_open_time=?,afternoon_out_time=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND event_id=?'
        );
        $insert = $this->db->prepare(
            "INSERT INTO tbl_event_attendance_schedules(event_id,schedule_date,attendance_session_mode_id,whole_day_in_time,whole_day_in_close_time,whole_day_out_open_time,whole_day_out_time,morning_in_time,morning_in_close_time,morning_out_open_time,morning_out_time,afternoon_in_time,afternoon_in_close_time,afternoon_out_open_time,afternoon_out_time,created_at,updated_at)
             VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)"
        );
        foreach ($schedules as $schedule) {
            $scheduleId = (int) ($schedule['id'] ?? 0);
            if (!$scheduleId && isset($existingByDate[$schedule['schedule_date']]) && !isset($used[$existingByDate[$schedule['schedule_date']]]))
                $scheduleId = $existingByDate[$schedule['schedule_date']];
            $values = [$schedule['schedule_date'], $schedule['attendance_session_mode_id'], $schedule['whole_day_in_time'], $schedule['whole_day_in_close_time'], $schedule['whole_day_out_open_time'], $schedule['whole_day_out_time'], $schedule['morning_in_time'], $schedule['morning_in_close_time'], $schedule['morning_out_open_time'], $schedule['morning_out_time'], $schedule['afternoon_in_time'], $schedule['afternoon_in_close_time'], $schedule['afternoon_out_open_time'], $schedule['afternoon_out_time']];
            if ($scheduleId && isset($existingById[$scheduleId])) {
                $update->execute([...$values, $scheduleId, $eventId]);
                $used[$scheduleId] = true;
            } else {
                $insert->execute([$eventId, ...$values]);
                $used[(int) $this->db->lastInsertId()] = true;
            }
        }
        $linked = $this->db->prepare('SELECT
            (SELECT COUNT(*) FROM tbl_attendance_entries WHERE event_schedule_id=?) +
            (SELECT COUNT(*) FROM tbl_sbo_event_assignments WHERE event_schedule_id=?) +
            (SELECT COUNT(*) FROM tbl_attendances WHERE event_id=? AND attendance_date=?)');
        $delete = $this->db->prepare('DELETE FROM tbl_event_attendance_schedules WHERE id=? AND event_id=?');
        foreach ($existingById as $scheduleId => $row) {
            if (isset($used[$scheduleId])) continue;
            $linked->execute([$scheduleId, $scheduleId, $eventId, $row['schedule_date']]);
            if ((int) $linked->fetchColumn() > 0)
                throw new EventValidationException(['attendance_days' => ['A day with assignments or attendance records cannot be removed.']]);
            $delete->execute([$scheduleId, $eventId]);
        }
    }

    private function replacePivot(string $table, string $column, int $eventId, array $ids): void
    {
        $this->db->prepare("DELETE FROM $table WHERE event_id=?")->execute([$eventId]);
        if (!$ids) return;
        $statement = $this->db->prepare("INSERT INTO $table(event_id,$column,created_at,updated_at) VALUES(?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        foreach ($ids as $id) $statement->execute([$eventId, $id]);
    }

    private function replaceEventLocations(int $eventId, array $locationIds, int $primaryLocationId): void
    {
        if ($primaryLocationId < 1 || !in_array($primaryLocationId, $locationIds, true)) {
            throw new EventValidationException(['general_location_id' => ['Choose a primary venue before saving the event.']]);
        }
        $this->db->prepare('DELETE FROM tbl_event_locations WHERE event_id=?')->execute([$eventId]);
        $statement = $this->db->prepare('INSERT INTO tbl_event_locations(event_id,location_id,is_primary,created_at,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        foreach ($locationIds as $locationId) {
            $statement->execute([$eventId, $locationId, $locationId === $primaryLocationId ? 1 : 0]);
        }
    }

    private function assignedUsers(int $eventId): array
    {
        $statement = $this->db->prepare('SELECT u.id,u.first_name,u.middle_name,u.last_name,r.name role FROM tbl_event_user eu JOIN tbl_users u ON u.id=eu.user_id LEFT JOIN tbl_roles r ON r.id=u.role_id WHERE eu.event_id=? ORDER BY u.last_name,u.first_name');
        $statement->execute([$eventId]);
        $users = $statement->fetchAll();
        foreach ($users as &$user) {
            $user['id'] = (int) $user['id'];
            $user['full_name'] = $this->fullName($user);
        }
        unset($user);
        return $users;
    }

    private function schedules(int $eventId): array
    {
        $statement = $this->db->prepare('SELECT eas.*,asm.code mode_code,asm.name mode_name FROM tbl_event_attendance_schedules eas LEFT JOIN tbl_attendance_session_modes asm ON asm.id=eas.attendance_session_mode_id WHERE eas.event_id=? ORDER BY eas.schedule_date');
        $statement->execute([$eventId]);
        return $statement->fetchAll();
    }

    private function eventLocations(int $eventId): array
    {
        $statement = $this->db->prepare('SELECT l.id,l.name,l.type,l.parent_location_id,el.is_primary FROM tbl_event_locations el JOIN tbl_locations l ON l.id=el.location_id WHERE el.event_id=? ORDER BY el.is_primary DESC,l.type,l.name');
        $statement->execute([$eventId]);
        $locations = $statement->fetchAll();
        foreach ($locations as &$location) {
            $location['id'] = (int) $location['id'];
            $location['parent_location_id'] = $location['parent_location_id'] === null ? null : (int) $location['parent_location_id'];
            $location['is_primary'] = (bool) $location['is_primary'];
        }
        unset($location);
        return $locations;
    }

    private function expectedParticipants(array $event): int
    {
        return (int)$this->scalar('SELECT COUNT(*) FROM tbl_event_membership_snapshots WHERE event_id=?',[(int)$event['id']]);
    }

    private function synchronizeStatuses(): void
    {
        $upcoming = (int) $this->scalar("SELECT id FROM tbl_event_statuses WHERE label='upcoming'");
        $ongoing = (int) $this->scalar("SELECT id FROM tbl_event_statuses WHERE label='ongoing'");
        $completed = (int) $this->scalar("SELECT id FROM tbl_event_statuses WHERE label='completed'");
        $now = date('Y-m-d H:i:s');
        $statement = $this->db->prepare('UPDATE tbl_events SET event_status_id=CASE WHEN start_at > ? THEN ? WHEN end_at <= ? THEN ? ELSE ? END,is_featured=CASE WHEN end_at <= ? THEN 0 ELSE is_featured END,featured_order=CASE WHEN end_at <= ? THEN NULL ELSE featured_order END,featured_until=CASE WHEN end_at <= ? THEN NULL ELSE featured_until END WHERE deleted_at IS NULL');
        $statement->execute([$now, $upcoming, $now, $completed, $ongoing, $now, $now, $now]);
    }

    private function lifecycleStatusId(string $startAt, string $endAt): int
    {
        $now = date('Y-m-d H:i:s');
        $label = $startAt > $now ? 'upcoming' : ($endAt <= $now ? 'completed' : 'ongoing');
        return (int)$this->scalar('SELECT id FROM tbl_event_statuses WHERE label=?', [$label]);
    }

    private function normalizeEvent(array $event): array
    {
        foreach (['id', 'location_id', 'academic_period_id', 'event_type_id', 'event_status_id', 'created_by', 'featured_order'] as $field) {
            $event[$field] = $event[$field] === null ? null : (int) $event[$field];
        }
        $event['is_featured'] = (bool) $event['is_featured'];
        $event['is_archived'] = $event['deleted_at'] !== null;
        return $event;
    }

    private function audienceSelectionChanged(int $eventId,array $data):bool
    {
        $expected=match($data['audience_type']){
            'selected_tribes'=>$data['tribe_ids'],'selected_year_levels'=>$data['year_level_ids'],'specific_students'=>$data['participant_ids'],default=>[],
        };
        $current=match($data['audience_type']){
            'selected_tribes'=>$this->pivotIds('tbl_event_team','team_id',$eventId),'selected_year_levels'=>$this->pivotIds('tbl_event_year_level','year_level_id',$eventId),'specific_students'=>$this->pivotIds('tbl_event_participants','user_id',$eventId),default=>[],
        };
        sort($expected);sort($current);return$expected!==$current;
    }

    private function scopeHasOperationalHistory(int $eventId):bool
    {
        return (int)$this->scalar("SELECT
          (SELECT COUNT(*) FROM tbl_attendances WHERE event_id=?)+
          (SELECT COUNT(*) FROM tbl_scores WHERE event_id=?)+
          (SELECT COUNT(*) FROM tbl_posts WHERE event_id=?)+
          (SELECT COUNT(*) FROM tbl_sbo_event_assignments sea JOIN tbl_event_attendance_schedules es ON es.id=sea.event_schedule_id WHERE es.event_id=?)",[$eventId,$eventId,$eventId,$eventId])>0;
    }

    private function eventRow(int $id): array
    {
        $statement = $this->db->prepare('SELECT * FROM tbl_events WHERE id=?');
        $statement->execute([$id]);
        $event = $statement->fetch();
        if (!$event) throw new EventValidationException(['event' => ['Event not found.']]);
        return $event;
    }

    private function exists(string $table, int $id): bool
    {
        return (bool) $this->scalar("SELECT id FROM $table WHERE id=?", [$id]);
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }

    private function integerList(mixed $values): array
    {
        if (!is_array($values)) return [];
        return array_values(array_unique(array_filter(array_map('intval', $values), static fn (int $id): bool => $id > 0)));
    }

    private function teamMemberIds(int $teamId): array
    {
        $statement = $this->db->prepare('SELECT user_id FROM tbl_team_user WHERE team_id=?');
        $statement->execute([$teamId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function pivotIds(string $table, string $column, int $eventId): array
    {
        $statement = $this->db->prepare("SELECT $column FROM $table WHERE event_id=?");
        $statement->execute([$eventId]);
        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    private function fullName(array $person, string $prefix = ''): string
    {
        return trim(implode(' ', array_filter([$person[$prefix.'first_name'] ?? null, $person[$prefix.'middle_name'] ?? null, $person[$prefix.'last_name'] ?? null])));
    }

    private function validDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function validTime(string $value): bool
    {
        $time = DateTimeImmutable::createFromFormat('!H:i', $value);
        return $time !== false && $time->format('H:i') === $value;
    }

    private function dateTime(string $date, string $time): ?DateTimeImmutable
    {
        $value = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$time);
        return $value ?: null;
    }

    private function scheduleLabel(string $start, string $end): string
    {
        return date('M j, Y g:i A', strtotime($start)).' – '.date('M j, Y g:i A', strtotime($end));
    }

    private function boolean(mixed $value): bool
    {
        return in_array($value, [true, 1, '1', 'true', 'on'], true);
    }

    private function storePoster(mixed $file): ?string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new EventValidationException(['poster' => ['The poster could not be uploaded.']]);
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) throw new EventValidationException(['poster' => ['The poster may not exceed 5 MB.']]);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) throw new EventValidationException(['poster' => ['Use a JPG, PNG, or WebP image.']]);
        $directory = dirname(__DIR__).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'uploads'.DIRECTORY_SEPARATOR.'event-posters';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('Poster directory could not be created.');
        $name = bin2hex(random_bytes(16)).'.'.$extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $directory.DIRECTORY_SEPARATOR.$name)) throw new RuntimeException('Poster could not be stored.');
        return 'assets/uploads/event-posters/'.$name;
    }

    private function deletePoster(string $path): void
    {
        if (!str_starts_with($path, 'assets/uploads/event-posters/')) return;
        $file = dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($file)) unlink($file);
    }

    private function log(int $actorId, int $eventId, string $action, string $description, ?int $subjectId = null): void
    {
        $role = $this->scalar('SELECT r.name FROM tbl_users u LEFT JOIN tbl_roles r ON r.id=u.role_id WHERE u.id=?', [$actorId]);
        $statement = $this->db->prepare('INSERT INTO tbl_activity_logs(actor_id,subject_user_id,event_id,action,description,acting_role,created_at,updated_at) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $statement->execute([$actorId, $subjectId, $eventId, $action, $description, $role ?: null]);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

$actor = AuthGuard::requireRole('SBO Adviser');
$repository = new EventManagementRepository((new Database())->connection());

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $data = ($_GET['action'] ?? '') === 'event_types'
            ? $repository->eventTypes()
            : (isset($_GET['id']) ? $repository->details((int) $_GET['id']) : $repository->index($_GET));
        JsonResponse::send(['success' => true, 'data' => $data]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        JsonResponse::send(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 403);
    }
    $input = $_POST;
    if (!$input) {
        $decoded = json_decode(file_get_contents('php://input'), true);
        $input = is_array($decoded) ? $decoded : [];
    }
    $action = (string) ($input['action'] ?? 'create');
    $actorId = (int) $actor['id'];
    switch ($action) {
        case 'create':
            $id = $repository->save($input, $_FILES, $actorId);
            JsonResponse::send(['success' => true, 'id' => $id, 'message' => 'Event created successfully.']);
        case 'update':
            $id = $repository->save($input, $_FILES, $actorId, (int) ($input['id'] ?? 0));
            JsonResponse::send(['success' => true, 'id' => $id, 'message' => 'Event updated successfully.']);
        case 'conflicts':
            JsonResponse::send(['success' => true] + $repository->conflicts($input));
        case 'status':
            $repository->setStatus((int) ($input['id'] ?? 0), (int) ($input['event_status_id'] ?? 0), $actorId);
            JsonResponse::send(['success' => true, 'message' => 'Event status updated successfully.']);
        case 'archive':
            $repository->archive((int) ($input['id'] ?? 0), $actorId);
            JsonResponse::send(['success' => true, 'message' => 'Event archived successfully.']);
        case 'restore':
            $repository->restore((int) ($input['id'] ?? 0), $actorId);
            JsonResponse::send(['success' => true, 'message' => 'Event restored successfully.']);
        case 'force_delete':
            $repository->forceDelete((int) ($input['id'] ?? 0));
            JsonResponse::send(['success' => true, 'message' => 'Event permanently deleted.']);
        case 'feature':
            $repository->feature((int) ($input['id'] ?? 0), $input, $actorId);
            JsonResponse::send(['success' => true, 'message' => $input['is_featured'] ? 'Event added to the featured carousel.' : 'Event removed from the featured carousel.']);
        case 'assign':
            $repository->assign((int) ($input['id'] ?? 0), (int) ($input['user_id'] ?? 0), $actorId);
            JsonResponse::send(['success' => true, 'message' => 'Person assigned successfully.']);
        case 'unassign':
            $repository->unassign((int) ($input['id'] ?? 0), (int) ($input['user_id'] ?? 0), $actorId);
            JsonResponse::send(['success' => true, 'message' => 'Assignment removed successfully.']);
        case 'event_type_create':
            $id = $repository->saveEventType($input);
            JsonResponse::send(['success' => true, 'id' => $id, 'message' => 'Event type added.']);
        case 'event_type_update':
            $id = $repository->saveEventType($input, (int) ($input['type_id'] ?? 0));
            JsonResponse::send(['success' => true, 'id' => $id, 'message' => 'Event type updated.']);
        case 'event_type_delete':
            $repository->deleteEventType((int) ($input['type_id'] ?? 0));
            JsonResponse::send(['success' => true, 'message' => 'Event type deleted.']);
        case 'activity_create':
            $repository->saveActivity((int) ($input['id'] ?? 0), $input, $actorId);
            JsonResponse::send(['success' => true, 'message' => 'Activity added successfully.']);
        case 'activity_update':
            $repository->saveActivity((int) ($input['id'] ?? 0), $input, $actorId, (int) ($input['activity_id'] ?? 0));
            JsonResponse::send(['success' => true, 'message' => 'Activity updated successfully.']);
        case 'activity_status':
            $repository->setActivityStatus((int) ($input['id'] ?? 0), (int) ($input['activity_id'] ?? 0), (string) ($input['status'] ?? ''), $actorId);
            JsonResponse::send(['success' => true, 'message' => 'Activity status updated successfully.']);
        default:
            throw new EventValidationException(['action' => ['Unknown event-management action.']]);
    }
} catch (EventValidationException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage(), 'errors' => $exception->errors()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'Event management request failed.'], 500);
}
