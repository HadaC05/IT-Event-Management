<?php

declare(strict_types=1);

final class SboAuthorization
{
    private const RESPONSIBILITIES = ['attendance', 'scoring', 'media'];

    public function __construct(private readonly PDO $db) {}

    public function assignments(int $officerUserId, string $responsibility, bool $todayOnly = true): array
    {
        if (!in_array($responsibility, self::RESPONSIBILITIES, true)) {
            throw new InvalidArgumentException('Invalid SBO responsibility.');
        }

        $dateClause = $todayOnly ? ' AND s.schedule_date = CURDATE()' : '';
        $statement = $this->db->prepare("SELECT sea.id,sea.session_code,sea.responsibility,
            s.id event_schedule_id,s.schedule_date,s.attendance_session_mode_id,
            CASE sea.session_code WHEN 'morning' THEN s.morning_in_time WHEN 'afternoon' THEN s.afternoon_in_time ELSE s.whole_day_in_time END session_start,
            CASE sea.session_code WHEN 'morning' THEN s.morning_out_time WHEN 'afternoon' THEN s.afternoon_out_time ELSE s.whole_day_out_time END session_end,
            e.id event_id,e.title event_name,e.location,e.start_at,e.end_at,e.audience_type,
            a.id activity_id,a.name activity_name,t.id team_id,t.name team_name,t.color team_color,
            1 + (SELECT COUNT(*) FROM tbl_event_attendance_schedules ds WHERE ds.event_id=e.id AND ds.schedule_date<s.schedule_date) day_number
          FROM tbl_sbo_event_assignments sea
          JOIN tbl_sbo_officer_assignments oa ON oa.id=sea.officer_assignment_id AND oa.status='Active'
          JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id
          JOIN tbl_events e ON e.id=s.event_id AND e.deleted_at IS NULL
          JOIN tbl_event_activities a ON a.id=sea.activity_id AND a.event_id=e.id AND a.status='active'
          JOIN tbl_teams t ON t.id=sea.team_id AND t.is_active=1
          WHERE oa.officer_user_id=? AND sea.status='active' AND sea.responsibility=?
            AND CURDATE() BETWEEN DATE(e.start_at) AND DATE(e.end_at)$dateClause
          ORDER BY s.schedule_date,session_start,a.name,t.name");
        $statement->execute([$officerUserId, $responsibility]);
        $rows = $statement->fetchAll();
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        foreach ($rows as &$row) {
            foreach (['id','event_schedule_id','event_id','activity_id','team_id','day_number'] as $key) $row[$key] = (int) $row[$key];
            $row['session_name'] = match ($row['session_code']) {'morning' => 'Morning Session', 'afternoon' => 'Afternoon Session', default => 'Whole Day Session'};
            $start = new DateTimeImmutable($row['schedule_date'].' '.$row['session_start'], new DateTimeZone('Asia/Manila'));
            $end = new DateTimeImmutable($row['schedule_date'].' '.$row['session_end'], new DateTimeZone('Asia/Manila'));
            $eventStart = new DateTimeImmutable($row['start_at'], new DateTimeZone('Asia/Manila'));
            $eventEnd = new DateTimeImmutable($row['end_at'], new DateTimeZone('Asia/Manila'));
            $row['is_session_active'] = $now >= $start && $now <= $end && $now >= $eventStart && $now <= $eventEnd;
        }
        unset($row);
        return $rows;
    }

    public function assignment(int $officerUserId, int $assignmentId, string $responsibility, bool $todayOnly = true): array
    {
        foreach ($this->assignments($officerUserId, $responsibility, $todayOnly) as $assignment) {
            if ($assignment['id'] === $assignmentId) return $assignment;
        }
        throw new DomainException('Unauthorized officer assignment.');
    }

    public function studentIsEligible(array $assignment, int $studentId): bool
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM tbl_users u
          JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student'
          JOIN tbl_user_statuses us ON us.id=u.status AND us.label='active'
          WHERE u.id=?
            AND EXISTS(SELECT 1 FROM tbl_team_user tu WHERE tu.user_id=u.id AND tu.team_id=?)
            AND (
              ?='all_students'
              OR (?='selected_tribes' AND EXISTS(SELECT 1 FROM tbl_event_team et WHERE et.event_id=? AND et.team_id=?))
              OR (?='selected_year_levels' AND EXISTS(SELECT 1 FROM tbl_event_year_level eyl WHERE eyl.event_id=? AND eyl.year_level_id=u.year_level))
              OR (?='specific_students' AND EXISTS(SELECT 1 FROM tbl_event_participants ep WHERE ep.event_id=? AND ep.user_id=u.id))
            )");
        $audience = $assignment['audience_type'];
        $statement->execute([$studentId,$assignment['team_id'],$audience,$audience,$assignment['event_id'],$assignment['team_id'],$audience,$assignment['event_id'],$audience,$assignment['event_id']]);
        return (bool) $statement->fetchColumn();
    }
}
