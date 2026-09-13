<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/leaderboard.php';

final class ReportValidationException extends RuntimeException
{
    public function __construct(private readonly array $validationErrors)
    {
        parent::__construct('Please correct the report filters.');
    }

    public function errors(): array { return $this->validationErrors; }
}

final class ReportRepository
{
    private const PAGE_SIZE = 25;
    private const TYPES = ['attendance', 'participation', 'scores', 'rankings'];
    private const ATTENDANCE_STATUSES = ['present', 'late', 'absent', 'excused'];

    public function __construct(private readonly PDO $db) {}

    public function view(array $input, bool $all = false): array
    {
        $filters = $this->validateFilters($input);
        $report = match ($filters['type']) {
            'participation' => $this->participation($filters, $all),
            'scores' => $this->scores($filters, $all),
            'rankings' => $this->rankings($filters, $all),
            default => $this->attendance($filters, $all),
        };

        return $report + [
            'type' => $filters['type'],
            'filters' => $filters,
            'events' => $this->typedRows('SELECT id,title,start_at FROM events WHERE deleted_at IS NULL ORDER BY start_at DESC,id DESC'),
            'school_years' => $this->typedRows('SELECT id,label FROM school_years ORDER BY label DESC,id DESC'),
            'categories' => $filters['event_id'] ? $this->typedRows('SELECT id,name,max_points FROM score_categories WHERE event_id=? ORDER BY sort_order,id', [$filters['event_id']]) : [],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function export(array $input): never
    {
        $report = $this->view($input, true);
        $type = $report['type'];
        $definitions = [
            'attendance' => [
                ['Student ID', 'Student', 'Year Level', 'Tribe', 'Event', 'Attendance Date', 'Status', 'Check-in'],
                fn (array $r): array => [$r['id_number'], $r['student_name'], $r['year_level'], $r['team_name'], $r['event_title'], $r['attendance_date'], ucfirst($r['status']), $r['checked_in_at']],
            ],
            'participation' => [
                ['Event', 'Schedule', 'Venue', 'Status', 'Expected Students', 'Recorded Students', 'Attended Students', 'Participation Rate'],
                fn (array $r): array => [$r['title'], date('Y-m-d H:i', strtotime($r['start_at'])), $r['location'], $r['schedule_state'], $r['expected_count'], $r['recorded_count'], $r['attended_count'], $r['participation_rate'] === null ? 'N/A' : $r['participation_rate'].'%'],
            ],
            'scores' => [
                ['Event', 'Event Date', 'Tribe', 'School Year', 'Criterion', 'Points', 'Maximum Points', 'Recorded By', 'Last Updated'],
                fn (array $r): array => [$r['event_title'], substr((string) $r['event_start_at'], 0, 10), $r['team_name'], $r['school_year'], $r['category_name'], $r['points'], $r['max_points'], $r['recorder_name'], $r['updated_at'] ? date('Y-m-d H:i', strtotime($r['updated_at'])) : null],
            ],
            'rankings' => [
                ['Rank', 'Tribe', 'School Year', 'Total Points', 'Score Entries', 'Scored Events', 'Status'],
                fn (array $r): array => [$r['rank'] ?? 'Unranked', $r['name'], $r['school_year_label'], $r['total_score'], $r['score_entries_count'], $r['scored_events_count'], $r['has_score'] ? 'Ranked' : 'Not scored'],
            ],
        ];
        [$headers, $mapper] = $definitions[$type];
        $filename = $type.'-report-'.date('Y-m-d-His').'.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);
        foreach ($report['rows'] as $row) fputcsv($output, array_map($this->sanitizeCsv(...), $mapper($row)));
        fclose($output);
        exit;
    }

    private function attendance(array $filters, bool $all): array
    {
        $where = [];
        $params = [];
        if ($filters['event_id']) {$where[] = 'a.event_id=?'; $params[] = $filters['event_id'];}
        if ($filters['school_year_id']) {$where[] = 'EXISTS(SELECT 1 FROM team_user tus JOIN teams ts ON ts.id=tus.team_id WHERE tus.user_id=a.user_id AND ts.school_year_id=?)'; $params[] = $filters['school_year_id'];}
        if ($filters['status']) {$where[] = 'a.status=?'; $params[] = $filters['status'];}
        if ($filters['date_from']) {$where[] = 'date(a.attendance_date)>=date(?)'; $params[] = $filters['date_from'];}
        if ($filters['date_to']) {$where[] = 'date(a.attendance_date)<=date(?)'; $params[] = $filters['date_to'];}
        if ($filters['search'] !== '') {
            $like = '%'.$this->escapeLike($filters['search']).'%';
            $where[] = "(e.title LIKE ? ESCAPE '\\\\' OR u.first_name LIKE ? ESCAPE '\\\\' OR u.middle_name LIKE ? ESCAPE '\\\\' OR u.last_name LIKE ? ESCAPE '\\\\' OR u.id_number LIKE ? ESCAPE '\\\\')";
            array_push($params, $like, $like, $like, $like, $like);
        }
        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
        $from = "FROM attendances a LEFT JOIN events e ON e.id=a.event_id LEFT JOIN users u ON u.id=a.user_id LEFT JOIN year_levels yl ON yl.id=u.year_level $whereSql";
        $summary = $this->row("SELECT COUNT(*) records,SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END) attended,SUM(CASE WHEN a.status='absent' THEN 1 ELSE 0 END) absent,SUM(CASE WHEN a.status='excused' THEN 1 ELSE 0 END) excused $from", $params);
        $total = (int) $summary['records'];
        $sql = "SELECT a.id,a.attendance_date,a.status,a.checked_in_at,e.title event_title,u.id_number,TRIM(CONCAT_WS(' ',u.first_name,NULLIF(u.middle_name,''),u.last_name)) student_name,yl.label year_level,(SELECT t.name FROM team_user tu JOIN teams t ON t.id=tu.team_id WHERE tu.user_id=u.id ORDER BY tu.id LIMIT 1) team_name $from ORDER BY DATE(a.attendance_date) DESC,a.id DESC";
        $rows = $this->pagedRows($sql, $params, $total, $filters['page'], $all);
        return ['rows' => $rows['rows'], 'pagination' => $rows['pagination'], 'summary' => [
            'records' => $total,
            'attended' => (int) ($summary['attended'] ?? 0),
            'absent' => (int) ($summary['absent'] ?? 0),
            'excused' => (int) ($summary['excused'] ?? 0),
            'rate' => $total ? round(((int) $summary['attended'] / $total) * 100, 1) : null,
        ]];
    }

    private function participation(array $filters, bool $all): array
    {
        $where = ['e.deleted_at IS NULL'];
        $params = [];
        if ($filters['event_id']) {$where[] = 'e.id=?'; $params[] = $filters['event_id'];}
        if ($filters['date_from']) {$where[] = 'date(e.start_at)>=date(?)'; $params[] = $filters['date_from'];}
        if ($filters['date_to']) {$where[] = 'date(e.start_at)<=date(?)'; $params[] = $filters['date_to'];}
        if ($filters['search'] !== '') {$like = '%'.$this->escapeLike($filters['search']).'%'; $where[] = "(e.title LIKE ? ESCAPE '\\\\' OR e.location LIKE ? ESCAPE '\\\\')"; array_push($params, $like, $like);}
        $events = $this->rows('SELECT e.* FROM events e WHERE '.implode(' AND ', $where).' ORDER BY e.start_at DESC,e.id DESC', $params);
        foreach ($events as &$event) {
            $eventId = (int) $event['id'];
            $event['id'] = $eventId;
            $studentWhere = ["r.name='Student'", "us.label='active'"];
            $studentParams = [];
            if ($filters['school_year_id']) {$studentWhere[] = 'EXISTS(SELECT 1 FROM team_user tu JOIN teams t ON t.id=tu.team_id WHERE tu.user_id=u.id AND t.school_year_id=?)'; $studentParams[] = $filters['school_year_id'];}
            match ($event['audience_type']) {
                'selected_tribes' => $studentWhere[] = 'EXISTS(SELECT 1 FROM team_user tu JOIN event_team et ON et.team_id=tu.team_id WHERE tu.user_id=u.id AND et.event_id='.$eventId.')',
                'selected_year_levels' => $studentWhere[] = 'EXISTS(SELECT 1 FROM event_year_level eyl WHERE eyl.event_id='.$eventId.' AND eyl.year_level_id=u.year_level)',
                'specific_students' => $studentWhere[] = 'EXISTS(SELECT 1 FROM event_participants ep WHERE ep.event_id='.$eventId.' AND ep.user_id=u.id)',
                default => null,
            };
            $event['expected_count'] = (int) $this->scalar('SELECT COUNT(DISTINCT u.id) FROM users u JOIN roles r ON r.id=u.role_id JOIN user_statuses us ON us.id=u.status WHERE '.implode(' AND ', $studentWhere), $studentParams);
            $attendanceWhere = ['a.event_id=?'];
            $attendanceParams = [$eventId];
            if ($filters['school_year_id']) {$attendanceWhere[] = 'EXISTS(SELECT 1 FROM team_user tu JOIN teams t ON t.id=tu.team_id WHERE tu.user_id=a.user_id AND t.school_year_id=?)'; $attendanceParams[] = $filters['school_year_id'];}
            $event['recorded_count'] = (int) $this->scalar('SELECT COUNT(DISTINCT a.user_id) FROM attendances a WHERE '.implode(' AND ', $attendanceWhere), $attendanceParams);
            $event['attended_count'] = (int) $this->scalar("SELECT COUNT(DISTINCT a.user_id) FROM attendances a WHERE ".implode(' AND ', $attendanceWhere)." AND a.status IN ('present','late')", $attendanceParams);
            $event['participation_rate'] = $event['expected_count'] ? min(100, round(($event['attended_count'] / $event['expected_count']) * 100, 1)) : null;
            $event['schedule_state'] = $event['start_at'] > date('Y-m-d H:i:s') ? 'upcoming' : ($event['end_at'] < date('Y-m-d H:i:s') ? 'completed' : 'ongoing');
        }
        unset($event);
        $expected = (int) array_sum(array_column($events, 'expected_count'));
        $attended = (int) array_sum(array_column($events, 'attended_count'));
        $total = count($events);
        $rows = $all ? $events : array_slice($events, ($this->page($filters['page'], $total) - 1) * self::PAGE_SIZE, self::PAGE_SIZE);
        return ['rows' => $rows, 'pagination' => $this->pagination($filters['page'], $total, $all), 'summary' => [
            'events' => $total, 'expected' => $expected,
            'recorded' => (int) array_sum(array_column($events, 'recorded_count')),
            'attended' => $attended,
            'rate' => $expected ? min(100, round(($attended / $expected) * 100, 1)) : null,
        ]];
    }

    private function scores(array $filters, bool $all): array
    {
        $where = ['s.score_category_id IS NOT NULL'];
        $params = [];
        if ($filters['event_id']) {$where[] = 's.event_id=?'; $params[] = $filters['event_id'];}
        if ($filters['category_id']) {$where[] = 's.score_category_id=?'; $params[] = $filters['category_id'];}
        if ($filters['school_year_id']) {$where[] = 't.school_year_id=?'; $params[] = $filters['school_year_id'];}
        if ($filters['date_from']) {$where[] = 'date(e.start_at)>=date(?)'; $params[] = $filters['date_from'];}
        if ($filters['date_to']) {$where[] = 'date(e.start_at)<=date(?)'; $params[] = $filters['date_to'];}
        if ($filters['search'] !== '') {$like = '%'.$this->escapeLike($filters['search']).'%'; $where[] = "(e.title LIKE ? ESCAPE '\\\\' OR t.name LIKE ? ESCAPE '\\\\' OR c.name LIKE ? ESCAPE '\\\\')"; array_push($params, $like, $like, $like);}
        $from = 'FROM scores s LEFT JOIN events e ON e.id=s.event_id LEFT JOIN teams t ON t.id=s.team_id LEFT JOIN school_years sy ON sy.id=t.school_year_id LEFT JOIN score_categories c ON c.id=s.score_category_id LEFT JOIN users u ON u.id=s.recorded_by WHERE '.implode(' AND ', $where);
        $summary = $this->row("SELECT COUNT(*) entries,COUNT(DISTINCT s.team_id) teams,COALESCE(SUM(s.points),0) points,AVG(s.points) average $from", $params);
        $total = (int) $summary['entries'];
        $sql = "SELECT s.id,s.points,s.updated_at,e.title event_title,e.start_at event_start_at,t.name team_name,t.color,sy.label school_year,c.name category_name,c.max_points,TRIM(CONCAT_WS(' ',u.first_name,NULLIF(u.middle_name,''),u.last_name)) recorder_name $from ORDER BY s.updated_at DESC,s.id DESC";
        $rows = $this->pagedRows($sql, $params, $total, $filters['page'], $all);
        foreach ($rows['rows'] as &$row) {$row['id'] = (int) $row['id']; $row['points'] = (float) $row['points']; $row['max_points'] = (float) ($row['max_points'] ?? 0);}
        unset($row);
        return ['rows' => $rows['rows'], 'pagination' => $rows['pagination'], 'summary' => [
            'entries' => $total, 'teams' => (int) $summary['teams'], 'points' => (float) $summary['points'],
            'average' => $total ? round((float) $summary['average'], 2) : null,
        ]];
    }

    private function rankings(array $filters, bool $all): array
    {
        $data = (new LeaderboardRepository($this->db))->view([
            'event_id' => $filters['event_id'] ?: '', 'school_year_id' => $filters['school_year_id'] ?: '',
            'category_id' => $filters['category_id'] ?: '', 'search' => $filters['search'],
        ]);
        $total = count($data['rankings']);
        $page = $this->page($filters['page'], $total);
        $rows = $all ? $data['rankings'] : array_slice($data['rankings'], ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);
        return ['rows' => $rows, 'pagination' => $this->pagination($page, $total, $all), 'summary' => [
            'ranked' => $data['summary']['ranked_teams'], 'eligible' => $data['summary']['eligible_teams'],
            'points' => $data['summary']['points'], 'events' => $data['summary']['events'],
            'leader' => $data['summary']['leader']['name'] ?? null,
        ]];
    }

    private function validateFilters(array $input): array
    {
        $errors = [];
        $type = (string) ($input['type'] ?? 'attendance');
        if (!in_array($type, self::TYPES, true)) {$errors['type'][] = 'Choose a valid report type.'; $type = 'attendance';}
        $eventId = $this->optionalExistingId($input['event_id'] ?? null, 'events', 'event_id', $errors, 'Choose a valid event.');
        if ($eventId && !(bool) $this->scalar('SELECT id FROM events WHERE id=? AND deleted_at IS NULL', [$eventId])) {
            $errors['event_id'][] = 'Choose a valid event.';
            $eventId = null;
        }
        $schoolYearId = $this->optionalExistingId($input['school_year_id'] ?? null, 'school_years', 'school_year_id', $errors, 'Choose a valid school year.');
        $categoryId = $this->optionalExistingId($input['category_id'] ?? null, 'score_categories', 'category_id', $errors, 'Choose a valid scoring criterion.');
        if ($categoryId && (!$eventId || !(bool) $this->scalar('SELECT id FROM score_categories WHERE id=? AND event_id=?', [$categoryId, $eventId]))) $errors['category_id'][] = 'Choose a scoring criterion from the selected event.';
        $status = trim((string) ($input['status'] ?? ''));
        if ($status !== '' && !in_array($status, self::ATTENDANCE_STATUSES, true)) $errors['status'][] = 'Choose a valid attendance status.';
        $from = trim((string) ($input['date_from'] ?? ''));
        $to = trim((string) ($input['date_to'] ?? ''));
        if ($from !== '' && !$this->validDate($from)) $errors['date_from'][] = 'Enter a valid from date.';
        if ($to !== '' && !$this->validDate($to)) $errors['date_to'][] = 'Enter a valid to date.';
        if ($from !== '' && $to !== '' && $this->validDate($from) && $this->validDate($to) && $to < $from) $errors['date_to'][] = 'The to date must be on or after the from date.';
        $search = trim((string) ($input['search'] ?? ''));
        if (mb_strlen($search) > 100) $errors['search'][] = 'Search may not exceed 100 characters.';
        if ($errors) throw new ReportValidationException($errors);
        return ['type' => $type, 'event_id' => $eventId, 'school_year_id' => $schoolYearId, 'category_id' => $categoryId, 'status' => $status ?: null, 'date_from' => $from ?: null, 'date_to' => $to ?: null, 'search' => $search, 'page' => max(1, (int) ($input['page'] ?? 1))];
    }

    private function optionalExistingId(mixed $value, string $table, string $field, array &$errors, string $message): ?int
    {
        if ($value === null || $value === '') return null;
        if (!ctype_digit((string) $value) || (int) $value < 1 || !(bool) $this->scalar("SELECT id FROM $table WHERE id=?", [(int) $value])) {$errors[$field][] = $message; return null;}
        return (int) $value;
    }

    private function pagedRows(string $sql, array $params, int $total, int $requestedPage, bool $all): array
    {
        if ($all) return ['rows' => $this->rows($sql, $params), 'pagination' => $this->pagination(1, $total, true)];
        $page = $this->page($requestedPage, $total);
        $statement = $this->db->prepare($sql.' LIMIT ? OFFSET ?');
        $index = 1;
        foreach ($params as $value) $statement->bindValue($index++, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $statement->bindValue($index++, self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->bindValue($index, ($page - 1) * self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->execute();
        return ['rows' => $statement->fetchAll(), 'pagination' => $this->pagination($page, $total, false)];
    }

    private function pagination(int $page, int $total, bool $all): array
    {
        return ['page' => $all ? 1 : $this->page($page, $total), 'last_page' => $all ? 1 : max(1, (int) ceil($total / self::PAGE_SIZE)), 'per_page' => self::PAGE_SIZE, 'total' => $total];
    }

    private function page(int $requested, int $total): int { return min(max(1, $requested), max(1, (int) ceil($total / self::PAGE_SIZE))); }
    private function typedRows(string $sql, array $params = []): array { $rows = $this->rows($sql, $params); foreach ($rows as &$row) {$row['id'] = (int) $row['id']; if (isset($row['max_points'])) $row['max_points'] = (float) $row['max_points'];} unset($row); return $rows; }
    private function rows(string $sql, array $params = []): array { $statement = $this->db->prepare($sql); $statement->execute($params); return $statement->fetchAll(); }
    private function row(string $sql, array $params = []): array { return $this->rows($sql, $params)[0] ?? []; }
    private function scalar(string $sql, array $params = []): mixed { $statement = $this->db->prepare($sql); $statement->execute($params); return $statement->fetchColumn(); }
    private function validDate(string $value): bool { $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value); return $date !== false && $date->format('Y-m-d') === $value; }
    private function escapeLike(string $value): string { return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value); }
    private function sanitizeCsv(mixed $value): mixed { return is_string($value) && preg_match('/^[=+\-@]/', $value) ? "'".$value : $value; }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

AuthGuard::requireRole('SBO Adviser');
$repository = new ReportRepository((new Database())->connection());
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    if (($_GET['action'] ?? '') === 'export') $repository->export($_GET);
    JsonResponse::send(['success' => true, 'data' => $repository->view($_GET)]);
} catch (ReportValidationException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage(), 'errors' => $exception->errors()], 422);
} catch (InvalidArgumentException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'Report request failed.'], 500);
}
