<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/leaderboard.php';
require_once __DIR__.'/AcademicPeriodScope.php';

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
    private int $perPage = self::PAGE_SIZE;
    private const TYPES = ['attendance', 'participation', 'scores', 'rankings'];
    private const ATTENDANCE_STATUSES = ['present', 'absent'];
    private const FILTER_TABLES = ['tbl_events', 'tbl_school_years', 'tbl_academic_periods', 'tbl_score_categories'];

    public function __construct(private readonly PDO $db) {}

    public function view(array $input, bool $all = false): array
    {
        $this->perPage = PageSize::from($input, self::PAGE_SIZE);
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
            'events' => $this->typedRows('SELECT id,title,start_at FROM tbl_events WHERE deleted_at IS NULL ORDER BY start_at DESC,id DESC'),
            'school_years' => $this->typedRows('SELECT id,label FROM tbl_school_years ORDER BY label DESC,id DESC'),
            'academic_periods' => (new AcademicPeriodScope($this->db))->periods(),
            'categories' => $filters['event_id'] ? $this->typedRows('SELECT id,name,max_points FROM tbl_score_categories WHERE event_id=? ORDER BY sort_order,id', [$filters['event_id']]) : [],
            'has_any_data' => $this->hasAnyData($filters['type']),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    public function export(array $input): never
    {
        $report = $this->view($input, true);
        $type = $report['type'];
        $definitions = [
            'attendance' => [
                ['Student ID', 'Student', 'Year Level', 'Tribe', 'Event', 'Academic Period', 'Attendance Date', 'Status', 'Time In', 'Time Out'],
                fn (array $r): array => [$r['id_number'], $r['student_name'], $r['year_level'], $r['team_name'], $r['event_title'], $r['academic_period_label'], $r['attendance_date'], ucfirst($r['status']), $r['time_in_at'], $r['time_out_at']],
            ],
            'participation' => [
                ['Event', 'Academic Period', 'Schedule', 'Venue', 'Status', 'Expected Students', 'Recorded Students', 'Attended Students', 'Participation Rate'],
                fn (array $r): array => [$r['title'], $r['academic_period_label'], date('Y-m-d H:i', strtotime($r['start_at'])), $r['location'], $r['schedule_state'], $r['expected_count'], $r['recorded_count'], $r['attended_count'], $r['participation_rate'] === null ? 'N/A' : $r['participation_rate'].'%'],
            ],
            'scores' => [
                ['Event', 'Event Date', 'Tribe', 'Academic Period', 'Criterion', 'Points', 'Maximum Points', 'Recorded By', 'Last Updated'],
                fn (array $r): array => [$r['event_title'], substr((string) $r['event_start_at'], 0, 10), $r['team_name'], $r['academic_period_label'], $r['category_name'], $r['points'], $r['max_points'], $r['recorder_name'], $r['updated_at'] ? date('Y-m-d H:i', strtotime($r['updated_at'])) : null],
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
        $scanComplete = "(EXISTS(SELECT 1 FROM tbl_attendance_entries final_in WHERE final_in.attendance_id=a.id AND final_in.phase='in') AND EXISTS(SELECT 1 FROM tbl_attendance_entries final_out WHERE final_out.attendance_id=a.id AND final_out.phase='out'))";
        $finalized = "(a.manual_status IS NOT NULL OR $scanComplete OR a.cutoff_passed=1)";
        $resultStatus = "CASE WHEN a.manual_status IS NOT NULL OR $scanComplete THEN CASE WHEN a.effective_status IN ('present','late') THEN 'present' ELSE 'absent' END WHEN a.cutoff_passed=1 THEN 'absent' ELSE 'incomplete' END";
        $closeTime = "CASE mode.code WHEN 'whole_day' THEN schedule.whole_day_out_time WHEN 'two_sessions' THEN COALESCE(schedule.afternoon_out_time,schedule.morning_out_time) ELSE NULL END";
        $cutoffPassed = "EXISTS(SELECT 1 FROM tbl_event_attendance_schedules schedule JOIN tbl_attendance_session_modes mode ON mode.id=schedule.attendance_session_mode_id WHERE schedule.event_id=base.event_id AND schedule.schedule_date=base.attendance_date AND $closeTime IS NOT NULL AND CURRENT_TIMESTAMP>=TIMESTAMP(schedule.schedule_date,$closeTime))";
        $attendanceSource = "(SELECT base.id,base.event_id,base.user_id,base.attendance_date,base.effective_status,base.manual_status,CASE WHEN $cutoffPassed THEN 1 ELSE 0 END cutoff_passed FROM vw_attendance_effective base UNION ALL SELECT NULL,schedule.event_id,membership.user_id,schedule.schedule_date,'absent',NULL,1 FROM tbl_event_attendance_schedules schedule JOIN tbl_attendance_session_modes mode ON mode.id=schedule.attendance_session_mode_id JOIN tbl_event_membership_snapshots membership ON membership.event_id=schedule.event_id WHERE $closeTime IS NOT NULL AND CURRENT_TIMESTAMP>=TIMESTAMP(schedule.schedule_date,$closeTime) AND NOT EXISTS(SELECT 1 FROM tbl_attendances existing WHERE existing.event_id=schedule.event_id AND existing.user_id=membership.user_id AND existing.attendance_date=schedule.schedule_date)) a";
        if ($filters['event_id']) {$where[] = 'a.event_id=?'; $params[] = $filters['event_id'];}
        if ($filters['school_year_id']) {$where[] = 'EXISTS(SELECT 1 FROM tbl_events period_event JOIN tbl_academic_periods ap ON ap.id=period_event.academic_period_id WHERE period_event.id=a.event_id AND ap.school_year_id=?)'; $params[] = $filters['school_year_id'];}
        if ($filters['academic_period_id']) {$where[] = 'EXISTS(SELECT 1 FROM tbl_events period_event WHERE period_event.id=a.event_id AND period_event.academic_period_id=?)'; $params[] = $filters['academic_period_id'];}
        if ($filters['status']) $where[] = "$finalized AND $resultStatus=".$this->db->quote($filters['status']);
        if ($filters['date_from']) {$where[] = 'date(a.attendance_date)>=date(?)'; $params[] = $filters['date_from'];}
        if ($filters['date_to']) {$where[] = 'date(a.attendance_date)<=date(?)'; $params[] = $filters['date_to'];}
        if ($filters['search'] !== '') {
            $like = '%'.$this->escapeLike($filters['search']).'%';
            $where[] = "(e.title LIKE ? ESCAPE '\\\\' OR u.first_name LIKE ? ESCAPE '\\\\' OR u.middle_name LIKE ? ESCAPE '\\\\' OR u.last_name LIKE ? ESCAPE '\\\\' OR u.id_number LIKE ? ESCAPE '\\\\')";
            array_push($params, $like, $like, $like, $like, $like);
        }
        $whereSql = $where ? 'WHERE '.implode(' AND ', $where) : '';
        $from = "FROM $attendanceSource LEFT JOIN tbl_events e ON e.id=a.event_id LEFT JOIN tbl_academic_periods ap ON ap.id=e.academic_period_id LEFT JOIN tbl_school_years sy ON sy.id=ap.school_year_id LEFT JOIN tbl_users u ON u.id=a.user_id LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level $whereSql";
        $summary = $this->row("SELECT COUNT(*) records,SUM(CASE WHEN $finalized AND $resultStatus='present' THEN 1 ELSE 0 END) attended,SUM(CASE WHEN $finalized AND $resultStatus='absent' THEN 1 ELSE 0 END) absent $from", $params);
        $total = (int) $summary['records'];
        $sql = "SELECT a.id,a.attendance_date,$resultStatus status,(SELECT MIN(scan_in.scanned_at) FROM tbl_attendance_entries scan_in WHERE scan_in.attendance_id=a.id AND scan_in.phase='in') time_in_at,(SELECT MAX(scan_out.scanned_at) FROM tbl_attendance_entries scan_out WHERE scan_out.attendance_id=a.id AND scan_out.phase='out') time_out_at,e.title event_title,CONCAT('SY ',sy.label,' · ',ap.term_name) academic_period_label,u.id_number,TRIM(CONCAT_WS(' ',u.first_name,NULLIF(u.middle_name,''),u.last_name)) student_name,yl.label year_level,(SELECT ms.team_name FROM tbl_event_membership_snapshots ms WHERE ms.event_id=a.event_id AND ms.user_id=a.user_id LIMIT 1) team_name $from ORDER BY DATE(a.attendance_date) DESC,a.id DESC";
        $rows = $this->pagedRows($sql, $params, $total, $filters['page'], $all);
        $finalizedTotal=(int)($summary['attended']??0)+(int)($summary['absent']??0);
        return ['rows' => $rows['rows'], 'pagination' => $rows['pagination'], 'summary' => [
            'records' => $total,
            'attended' => (int) ($summary['attended'] ?? 0),
            'absent' => (int) ($summary['absent'] ?? 0),
            'rate' => $finalizedTotal ? round(((int) $summary['attended'] / $finalizedTotal) * 100, 1) : null,
        ]];
    }

    private function participation(array $filters, bool $all): array
    {
        $where = ['e.deleted_at IS NULL'];
        $params = [];
        if ($filters['event_id']) {$where[] = 'e.id=?'; $params[] = $filters['event_id'];}
        if ($filters['school_year_id']) {$where[] = 'EXISTS(SELECT 1 FROM tbl_academic_periods ap WHERE ap.id=e.academic_period_id AND ap.school_year_id=?)'; $params[] = $filters['school_year_id'];}
        if ($filters['academic_period_id']) {$where[] = 'e.academic_period_id=?'; $params[] = $filters['academic_period_id'];}
        if ($filters['date_from']) {$where[] = 'date(e.start_at)>=date(?)'; $params[] = $filters['date_from'];}
        if ($filters['date_to']) {$where[] = 'date(e.start_at)<=date(?)'; $params[] = $filters['date_to'];}
        if ($filters['search'] !== '') {$like = '%'.$this->escapeLike($filters['search']).'%'; $where[] = "(e.title LIKE ? ESCAPE '\\\\' OR e.location LIKE ? ESCAPE '\\\\')"; array_push($params, $like, $like);}
        $events = $this->rows("SELECT e.*,CONCAT('SY ',sy.label,' · ',ap.term_name) academic_period_label FROM tbl_events e LEFT JOIN tbl_academic_periods ap ON ap.id=e.academic_period_id LEFT JOIN tbl_school_years sy ON sy.id=ap.school_year_id WHERE ".implode(' AND ', $where).' ORDER BY e.start_at DESC,e.id DESC', $params);
        foreach ($events as &$event) {
            $eventId = (int) $event['id'];
            $event['id'] = $eventId;
            $event['expected_count'] = (int) $this->scalar('SELECT COUNT(*) FROM tbl_event_membership_snapshots WHERE event_id=?',[$eventId]);
            $attendanceWhere = ['a.event_id=?'];
            $attendanceParams = [$eventId];
            $event['recorded_count'] = (int) $this->scalar('SELECT COUNT(DISTINCT a.user_id) FROM vw_attendance_effective a WHERE '.implode(' AND ', $attendanceWhere), $attendanceParams);
            $event['attended_count'] = (int) $this->scalar("SELECT COUNT(DISTINCT a.user_id) FROM vw_attendance_effective a WHERE ".implode(' AND ', $attendanceWhere)." AND a.effective_status IN ('present','late')", $attendanceParams);
            $event['participation_rate'] = $event['expected_count'] ? min(100, round(($event['attended_count'] / $event['expected_count']) * 100, 1)) : null;
            $event['schedule_state'] = $event['start_at'] > date('Y-m-d H:i:s') ? 'upcoming' : ($event['end_at'] < date('Y-m-d H:i:s') ? 'completed' : 'ongoing');
        }
        unset($event);
        $expected = (int) array_sum(array_column($events, 'expected_count'));
        $attended = (int) array_sum(array_column($events, 'attended_count'));
        $total = count($events);
        $rows = $all ? $events : array_slice($events, ($this->page($filters['page'], $total) - 1) * $this->perPage, $this->perPage);
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
        if ($filters['academic_period_id']) {$where[] = 'e.academic_period_id=?'; $params[] = $filters['academic_period_id'];}
        if ($filters['date_from']) {$where[] = 'date(e.start_at)>=date(?)'; $params[] = $filters['date_from'];}
        if ($filters['date_to']) {$where[] = 'date(e.start_at)<=date(?)'; $params[] = $filters['date_to'];}
        if ($filters['search'] !== '') {$like = '%'.$this->escapeLike($filters['search']).'%'; $where[] = "(e.title LIKE ? ESCAPE '\\\\' OR t.name LIKE ? ESCAPE '\\\\' OR c.name LIKE ? ESCAPE '\\\\')"; array_push($params, $like, $like, $like);}
        $from = 'FROM vw_finalized_scores s LEFT JOIN tbl_events e ON e.id=s.event_id LEFT JOIN tbl_academic_periods ap ON ap.id=e.academic_period_id LEFT JOIN tbl_teams t ON t.id=s.team_id LEFT JOIN tbl_school_years sy ON sy.id=ap.school_year_id LEFT JOIN tbl_score_categories c ON c.id=s.score_category_id LEFT JOIN tbl_users u ON u.id=s.recorded_by WHERE '.implode(' AND ', $where);
        $summary = $this->row("SELECT COUNT(*) entries,COUNT(DISTINCT s.team_id) teams,COALESCE(SUM(s.points),0) points,AVG(s.points) average $from", $params);
        $total = (int) $summary['entries'];
        $sql = "SELECT s.id,s.points,s.updated_at,e.title event_title,e.start_at event_start_at,t.name team_name,t.color,CONCAT('SY ',sy.label,' · ',ap.term_name) academic_period_label,c.name category_name,c.max_points,TRIM(CONCAT_WS(' ',u.first_name,NULLIF(u.middle_name,''),u.last_name)) recorder_name $from ORDER BY s.updated_at DESC,s.id DESC";
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
            'academic_period_id' => $filters['academic_period_id'] ?: '',
            'category_id' => $filters['category_id'] ?: '', 'search' => $filters['search'],
        ]);
        $total = count($data['rankings']);
        $page = $this->page($filters['page'], $total);
        $rows = $all ? $data['rankings'] : array_slice($data['rankings'], ($page - 1) * $this->perPage, $this->perPage);
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
        $eventId = $this->optionalExistingId($input['event_id'] ?? null, 'tbl_events', 'event_id', $errors, 'Choose a valid event.');
        if ($eventId && !(bool) $this->scalar('SELECT id FROM tbl_events WHERE id=? AND deleted_at IS NULL', [$eventId])) {
            $errors['event_id'][] = 'Choose a valid event.';
            $eventId = null;
        }
        $schoolYearId = $this->optionalExistingId($input['school_year_id'] ?? null, 'tbl_school_years', 'school_year_id', $errors, 'Choose a valid school year.');
        $academicPeriodId = $this->optionalExistingId($input['academic_period_id'] ?? null, 'tbl_academic_periods', 'academic_period_id', $errors, 'Choose a valid academic period.');
        $categoryId = $this->optionalExistingId($input['category_id'] ?? null, 'tbl_score_categories', 'category_id', $errors, 'Choose a valid scoring criterion.');
        if ($categoryId && (!$eventId || !(bool) $this->scalar('SELECT id FROM tbl_score_categories WHERE id=? AND event_id=?', [$categoryId, $eventId]))) $errors['category_id'][] = 'Choose a scoring criterion from the selected event.';
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
        return ['type' => $type, 'event_id' => $eventId, 'school_year_id' => $schoolYearId, 'academic_period_id' => $academicPeriodId, 'category_id' => $categoryId, 'status' => $status ?: null, 'date_from' => $from ?: null, 'date_to' => $to ?: null, 'search' => $search, 'page' => max(1, (int) ($input['page'] ?? 1))];
    }

    private function optionalExistingId(mixed $value, string $table, string $field, array &$errors, string $message): ?int
    {
        if ($value === null || $value === '') return null;
        if (!in_array($table, self::FILTER_TABLES, true)) throw new LogicException('Unsafe report filter table.');
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
        $statement->bindValue($index++, $this->perPage, PDO::PARAM_INT);
        $statement->bindValue($index, ($page - 1) * $this->perPage, PDO::PARAM_INT);
        $statement->execute();
        return ['rows' => $statement->fetchAll(), 'pagination' => $this->pagination($page, $total, false)];
    }

    private function pagination(int $page, int $total, bool $all): array
    {
        return ['page' => $all ? 1 : $this->page($page, $total), 'last_page' => $all ? 1 : max(1, (int) ceil($total / $this->perPage)), 'per_page' => $this->perPage, 'total' => $total];
    }

    private function hasAnyData(string $type): bool
    {
        return match ($type) {
            'participation' => (bool) $this->scalar('SELECT 1 FROM tbl_events WHERE deleted_at IS NULL LIMIT 1'),
            'scores' => (bool) $this->scalar('SELECT 1 FROM vw_finalized_scores WHERE score_category_id IS NOT NULL LIMIT 1'),
            'rankings' => (bool) $this->scalar('SELECT 1 FROM tbl_teams LIMIT 1'),
            default => (bool) $this->scalar('SELECT 1 FROM tbl_attendances LIMIT 1'),
        };
    }

    private function page(int $requested, int $total): int { return min(max(1, $requested), max(1, (int) ceil($total / $this->perPage))); }
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
