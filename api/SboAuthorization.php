<?php

declare(strict_types=1);

require_once __DIR__.'/AttendanceScanWindows.php';

final class SboAuthorization
{
    public function __construct(private readonly PDO $db) {}

    public function assignments(int $officerUserId, string $responsibility, bool $todayOnly = true): array
    {
        $dateClause = $todayOnly ? ' AND s.schedule_date = CURDATE()' : '';
        $statement = $this->db->prepare("SELECT sea.id,sea.officer_assignment_id,sea.session_code,r.code responsibility,
            s.id event_schedule_id,s.schedule_date,s.attendance_session_mode_id,
            s.whole_day_in_time,s.whole_day_in_close_time,s.whole_day_out_open_time,s.whole_day_out_time,
            s.morning_in_time,s.morning_in_close_time,s.morning_out_open_time,s.morning_out_time,
            s.afternoon_in_time,s.afternoon_in_close_time,s.afternoon_out_open_time,s.afternoon_out_time,
            CASE sea.session_code WHEN 'morning' THEN s.morning_in_time WHEN 'afternoon' THEN s.afternoon_in_time ELSE s.whole_day_in_time END session_start,
            CASE sea.session_code WHEN 'morning' THEN s.morning_out_time WHEN 'afternoon' THEN s.afternoon_out_time ELSE s.whole_day_out_time END session_end,
            e.id event_id,e.title event_name,e.location,e.start_at,e.end_at,e.audience_type,e.academic_period_id,
            ap.term_name academic_term_name,sy.label school_year_label,
            a.id activity_id,a.name activity_name,t.id team_id,t.name team_name,t.color team_color,
            COALESCE(sea.scanner_mode,'specific') scanner_mode,COALESCE(sea.scanner_team_id,sea.team_id) scanner_team_id,allowed_team.name scanner_team_name,
            1 + (SELECT COUNT(*) FROM tbl_event_attendance_schedules ds WHERE ds.event_id=e.id AND ds.schedule_date<s.schedule_date) day_number
          FROM tbl_sbo_event_assignments sea
          JOIN tbl_officer_responsibilities r ON r.id=sea.responsibility_id
          JOIN tbl_sbo_officer_assignments oa ON oa.id=sea.officer_assignment_id AND oa.status='Active'
          JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id
          JOIN tbl_events e ON e.id=s.event_id AND e.deleted_at IS NULL
          JOIN tbl_academic_periods ap ON ap.id=e.academic_period_id
          JOIN tbl_school_years sy ON sy.id=ap.school_year_id
          JOIN tbl_event_activities a ON a.id=sea.activity_id AND a.event_id=e.id AND a.status<>'inactive'
          JOIN tbl_teams t ON t.id=sea.team_id AND t.is_active=1 AND t.school_year_id=ap.school_year_id
          LEFT JOIN tbl_teams allowed_team ON allowed_team.id=COALESCE(sea.scanner_team_id,sea.team_id)
          WHERE oa.officer_user_id=? AND sea.status='active' AND r.code=?
            AND (e.audience_type='all_students'
              OR (e.audience_type='selected_tribes' AND EXISTS(SELECT 1 FROM tbl_event_team et WHERE et.event_id=e.id AND et.team_id=t.id))
              OR (e.audience_type IN ('selected_year_levels','specific_students') AND EXISTS(SELECT 1 FROM tbl_event_membership_snapshots ms WHERE ms.event_id=e.id AND ms.team_id=t.id)))
            AND (
              (CURDATE() BETWEEN DATE(e.start_at) AND DATE(e.end_at)$dateClause)
              OR e.start_at > CURRENT_TIMESTAMP
            )
          ORDER BY s.schedule_date,session_start,a.name,t.name");
        $statement->execute([$officerUserId, $responsibility]);
        $rows = $statement->fetchAll();
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        foreach ($rows as &$row) {
            foreach (['id','officer_assignment_id','event_schedule_id','event_id','academic_period_id','activity_id','team_id','scanner_team_id','day_number'] as $key) $row[$key] = (int) $row[$key];
            $row['session_name'] = match ($row['session_code']) {'morning' => 'Morning Session', 'afternoon' => 'Afternoon Session', default => 'Whole Day Session'};
            $start = new DateTimeImmutable($row['schedule_date'].' '.$row['session_start'], new DateTimeZone('Asia/Manila'));
            $end = new DateTimeImmutable($row['schedule_date'].' '.$row['session_end'], new DateTimeZone('Asia/Manila'));
            $eventStart = new DateTimeImmutable($row['start_at'], new DateTimeZone('Asia/Manila'));
            $eventEnd = new DateTimeImmutable($row['end_at'], new DateTimeZone('Asia/Manila'));
            $row['assignment_state'] = $eventStart > $now ? 'upcoming' : 'current';
            if ($responsibility === 'attendance') {
                try {
                    $windows = AttendanceScanWindows::forSession($row, $row['session_code']);
                    $row['in_window_open'] = AttendanceScanWindows::isOpen($windows['in'], $now);
                    $row['out_window_open'] = AttendanceScanWindows::isOpen($windows['out'], $now);
                    foreach (['in','out'] as $phase) {
                        $row[$phase.'_opens_at'] = $windows[$phase]['opens']->format('Y-m-d H:i:s');
                        $row[$phase.'_closes_at'] = $windows[$phase]['closes']->format('Y-m-d H:i:s');
                    }
                } catch (DomainException $exception) {
                    $row['in_window_open'] = $row['out_window_open'] = false;
                    $row['window_error'] = $exception->getMessage();
                }
                $row['is_session_active'] = $row['in_window_open'] || $row['out_window_open'];
            } else $row['is_session_active'] = $now >= $start && $now <= $end && $now >= $eventStart && $now <= $eventEnd;
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

    public function studentIsEligible(array $assignment, int $studentId, int $studentTeamId): bool
    {
        $statement=$this->db->prepare('SELECT COUNT(*) FROM tbl_event_membership_snapshots WHERE event_id=? AND user_id=? AND team_id=?');
        $statement->execute([(int)$assignment['event_id'],$studentId,$studentTeamId]);
        return (bool) $statement->fetchColumn();
    }
}
