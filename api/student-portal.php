<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/LeaderboardPublication.php';

final class StudentPortalRepository
{
    private const TEAM_MEMBERS_PER_PAGE = 24;

    public function __construct(private readonly PDO $db)
    {
    }

    public function events(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT e.id, e.title, e.description, e.location, e.poster_path,
                    e.start_at, e.end_at, es.label AS status, et.label AS type
             FROM tbl_events e
             LEFT JOIN tbl_event_statuses es ON es.id = e.event_status_id
             LEFT JOIN tbl_event_types et ON et.id = e.event_type_id
             WHERE e.deleted_at IS NULL
               AND {$this->eligibleEventSql('e')}
             ORDER BY e.start_at"
        );
        $statement->execute([$userId, $userId]);
        $events = $statement->fetchAll();
        $this->attachSchedules($events);

        $active = [];
        $past = [];
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        foreach ($events as $event) {
            $event['id'] = (int) $event['id'];
            $end = new DateTimeImmutable((string) $event['end_at'], new DateTimeZone('Asia/Manila'));
            $isPast = $end <= $now;
            $event['schedule_state'] = $isPast
                ? 'completed'
                : ((new DateTimeImmutable((string) $event['start_at'], new DateTimeZone('Asia/Manila'))) > $now ? 'upcoming' : 'ongoing');
            if ($isPast) {
                array_unshift($past, $event);
            } else {
                $active[] = $event;
            }
        }

        return ['active' => $active, 'past' => $past];
    }

    public function attendance(int $userId): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
        $history = $this->db->prepare(
            "SELECT eas.id schedule_id,e.id event_id,e.title event_title,e.start_at,eas.schedule_date attendance_date,
                    asm.code attendance_mode,
                    eas.whole_day_out_time,eas.morning_out_time,eas.afternoon_out_time,
                    a.id,a.effective_status,a.manual_status,a.notes,
                    (SELECT MIN(scan_in.scanned_at) FROM tbl_attendance_entries scan_in WHERE scan_in.attendance_id=a.id AND scan_in.phase='in') time_in_at,
                    (SELECT MAX(scan_out.scanned_at) FROM tbl_attendance_entries scan_out WHERE scan_out.attendance_id=a.id AND scan_out.phase='out') time_out_at,
                    EXISTS(SELECT 1 FROM tbl_attendance_entries scan_in WHERE scan_in.attendance_id=a.id AND scan_in.phase='in') has_time_in,
                    EXISTS(SELECT 1 FROM tbl_attendance_entries scan_out WHERE scan_out.attendance_id=a.id AND scan_out.phase='out') has_time_out
             FROM tbl_events e
             JOIN tbl_event_attendance_schedules eas ON eas.event_id=e.id
             JOIN tbl_attendance_session_modes asm ON asm.id=eas.attendance_session_mode_id
             LEFT JOIN vw_attendance_effective a ON a.event_id=e.id AND a.user_id=? AND a.attendance_date=eas.schedule_date
             WHERE e.deleted_at IS NULL AND eas.schedule_date<=?
               AND {$this->eligibleEventSql('e')}
             ORDER BY eas.schedule_date DESC,e.start_at DESC"
        );
        $history->execute([$userId, $now->format('Y-m-d'), $userId, $userId]);
        $records = $history->fetchAll();
        $summary = ['total' => count($records), 'present' => 0, 'absent' => 0, 'pending' => 0, 'rate' => 0];
        foreach ($records as &$record) {
            $record['id'] = $record['id'] === null ? null : (int) $record['id'];
            $record['schedule_id'] = (int) $record['schedule_id'];
            $record['event_id'] = (int) $record['event_id'];
            $record['has_time_in'] = (bool) $record['has_time_in'];
            $record['has_time_out'] = (bool) $record['has_time_out'];
            $closeTime = match ($record['attendance_mode']) {
                'whole_day' => $record['whole_day_out_time'],
                'two_sessions' => $record['afternoon_out_time'] ?: $record['morning_out_time'],
                default => null,
            };
            $closed = $closeTime
                ? $now >= new DateTimeImmutable($record['attendance_date'].' '.$closeTime, new DateTimeZone('Asia/Manila'))
                : false;
            if ($record['manual_status'] !== null) {
                $record['status'] = in_array($record['effective_status'], ['present', 'late'], true) ? 'present' : 'absent';
            } elseif ($record['has_time_in'] && $record['has_time_out']) {
                $record['status'] = 'present';
            } elseif ($record['has_time_in'] || $record['has_time_out']) {
                $record['status'] = 'absent';
            } else {
                $record['status'] = $closed ? 'absent' : 'pending';
            }
            $summary[$record['status']]++;
            unset($record['effective_status'], $record['whole_day_out_time'], $record['morning_out_time'], $record['afternoon_out_time']);
        }
        unset($record);
        $finalized = $summary['present'] + $summary['absent'];
        $summary['rate'] = $finalized > 0 ? (int) round(($summary['present'] / $finalized) * 100) : 0;

        $events = $this->events($userId);
        return [
            'summary' => $summary,
            'current_event' => $events['active'][0] ?? null,
            'records' => $records,
        ];
    }

    public function team(int $userId): array
    {
        $leaderboardVisible = (new LeaderboardPublication($this->db))->studentVisible();
        $statement = $this->db->prepare(
            "SELECT t.id, t.name, t.color, t.image_path, sy.label AS school_year,
                    COUNT(DISTINCT members.user_id) AS members_count,
                    COALESCE(SUM(DISTINCT score_totals.total), 0) AS total_score
             FROM tbl_team_user mine
             JOIN tbl_teams t ON t.id = mine.team_id
             JOIN tbl_school_years sy ON sy.id = t.school_year_id
             LEFT JOIN tbl_team_user members ON members.team_id = t.id AND EXISTS(SELECT 1 FROM tbl_users mu JOIN tbl_roles mr ON mr.id=mu.role_id WHERE mu.id=members.user_id AND mr.name='Student')
             LEFT JOIN (
                 SELECT team_id, SUM(points) AS total
                 FROM (SELECT team_id,points FROM vw_finalized_scores
                       UNION ALL SELECT team_id,overall_points AS points FROM tbl_activity_score_results) official_scores
                 GROUP BY team_id
             ) score_totals ON score_totals.team_id = t.id
             WHERE mine.user_id = ? AND t.is_active = 1
             GROUP BY t.id, t.name, t.color, t.image_path, sy.label
             ORDER BY sy.id DESC
             LIMIT 1"
        );
        $statement->execute([$userId]);
        $team = $statement->fetch();
        if (!$team) {
            return ['team' => null];
        }

        $teamId = (int) $team['id'];
        $team['id'] = $teamId;
        $team['members_count'] = (int) $team['members_count'];
        $team['leaderboard_visible'] = $leaderboardVisible;
        $team['total_score'] = $leaderboardVisible ? (float) $team['total_score'] : null;
        $team['rank'] = $leaderboardVisible ? $this->teamRank($teamId) : null;

        $currentMember = $this->db->prepare(
            "SELECT u.id,u.first_name,u.middle_name,u.last_name,u.profile_photo_path,yl.label year_level
             FROM tbl_users u LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level WHERE u.id=?"
        );
        $currentMember->execute([$userId]);
        $team['current_member'] = $currentMember->fetch() ?: null;
        if ($team['current_member']) {
            $team['current_member']['id'] = (int) $team['current_member']['id'];
            $team['current_member']['full_name'] = $this->fullName($team['current_member']);
            $team['current_member']['initials'] = $this->initials($team['current_member']);
        }

        $team['scores'] = [];
        if ($leaderboardVisible) {
            $scores = $this->db->prepare(
                "SELECT category,SUM(points) AS points,MAX(updated_at) AS updated_at FROM (
                    SELECT CONCAT('category-',COALESCE(sc.id,0)) AS score_key,COALESCE(sc.name,'General') AS category,s.points,s.updated_at
                    FROM vw_finalized_scores s LEFT JOIN tbl_score_categories sc ON sc.id=s.score_category_id WHERE s.team_id=?
                    UNION ALL
                    SELECT CONCAT('activity-',result.activity_id),activity.name,result.overall_points,result.finalized_at
                    FROM tbl_activity_score_results result JOIN tbl_event_activities activity ON activity.id=result.activity_id WHERE result.team_id=?
                ) official_scores GROUP BY score_key,category ORDER BY points DESC"
            );
            $scores->execute([$teamId, $teamId]);
            $team['scores'] = $scores->fetchAll();
            foreach ($team['scores'] as &$score) $score['points'] = (float) $score['points'];
            unset($score);
        }

        $leaders = $this->db->prepare(
            "SELECT GROUP_CONCAT(DISTINCT responsibility.label ORDER BY responsibility.label SEPARATOR ', ') AS position,
                    u.first_name, u.middle_name, u.last_name
             FROM tbl_sbo_event_assignments event_assignment
             JOIN tbl_sbo_officer_assignments officer_assignment ON officer_assignment.id=event_assignment.officer_assignment_id AND LOWER(officer_assignment.status)='active'
             JOIN tbl_officer_responsibilities responsibility ON responsibility.id=event_assignment.responsibility_id
             JOIN tbl_users u ON u.id=officer_assignment.officer_user_id
             WHERE event_assignment.team_id=? AND event_assignment.status='active'
             GROUP BY u.id,u.first_name,u.middle_name,u.last_name
             ORDER BY position,u.last_name,u.first_name"
        );
        $leaders->execute([$teamId]);
        $team['leaders'] = $leaders->fetchAll();
        foreach ($team['leaders'] as &$leader) {
            $leader['full_name'] = $this->fullName($leader);
        }
        unset($leader);

        $activities = $this->db->prepare(
            "SELECT e.id, e.title, e.start_at, e.end_at, e.location
             FROM tbl_event_team et
             JOIN tbl_events e ON e.id = et.event_id
             WHERE et.team_id = ? AND e.deleted_at IS NULL AND e.end_at >= CURRENT_TIMESTAMP
             ORDER BY e.start_at
             LIMIT 5"
        );
        $activities->execute([$teamId]);
        $team['activities'] = $activities->fetchAll();

        return ['team' => $team];
    }

    public function teamMembers(int $userId, array $filters = []): array
    {
        $teamStatement=$this->db->prepare('SELECT t.id,t.name FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id WHERE tu.user_id=? AND t.is_active=1 ORDER BY t.school_year_id DESC,tu.id DESC LIMIT 1');
        $teamStatement->execute([$userId]);$team=$teamStatement->fetch();
        if(!$team)return ['team'=>null,'members'=>[],'pagination'=>$this->pagination(1,0,self::TEAM_MEMBERS_PER_PAGE)];
        $search=trim((string)($filters['search']??''));$page=max(1,(int)($filters['page_number']??1));
        if(mb_strlen($search)>100)throw new InvalidArgumentException('Search may not exceed 100 characters.');
        $where=["tu.team_id=:team_id","r.name='Student'","u.id<>:current_user"];$params=['team_id'=>(int)$team['id'],'current_user'=>$userId];
        if($search!==''){$term='%'.addcslashes($search,'%_\\').'%';$where[]="(u.first_name LIKE :q_first ESCAPE '\\\\' OR u.middle_name LIKE :q_middle ESCAPE '\\\\' OR u.last_name LIKE :q_last ESCAPE '\\\\' OR CONCAT_WS(' ',u.first_name,NULLIF(u.middle_name,''),u.last_name) LIKE :q_full ESCAPE '\\\\' OR yl.label LIKE :q_year ESCAPE '\\\\')";foreach(['q_first','q_middle','q_last','q_full','q_year'] as $key)$params[$key]=$term;}
        $from=' FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level WHERE '.implode(' AND ',$where);
        $count=$this->db->prepare('SELECT COUNT(DISTINCT u.id)'.$from);foreach($params as $key=>$value)$count->bindValue(':'.$key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);$count->execute();$total=(int)$count->fetchColumn();
        $lastPage=max(1,(int)ceil($total/self::TEAM_MEMBERS_PER_PAGE));$page=min($page,$lastPage);$offset=($page-1)*self::TEAM_MEMBERS_PER_PAGE;
        // A team/user pair is unique, so DISTINCT is unnecessary. It also makes
        // MySQL reject the year-level sort unless yl.id is in the SELECT list.
        $statement=$this->db->prepare("SELECT u.id,u.first_name,u.middle_name,u.last_name,u.profile_photo_path,yl.label year_level{$from} ORDER BY yl.id,u.last_name,u.first_name,u.id LIMIT :limit OFFSET :offset");
        foreach($params as $key=>$value)$statement->bindValue(':'.$key,$value,is_int($value)?PDO::PARAM_INT:PDO::PARAM_STR);$statement->bindValue(':limit',self::TEAM_MEMBERS_PER_PAGE,PDO::PARAM_INT);$statement->bindValue(':offset',$offset,PDO::PARAM_INT);$statement->execute();$members=$statement->fetchAll();
        foreach($members as &$member){$member['id']=(int)$member['id'];$member['full_name']=$this->fullName($member);$member['initials']=$this->initials($member);}unset($member);
        return ['team'=>['id'=>(int)$team['id'],'name'=>$team['name']],'members'=>$members,'pagination'=>$this->pagination($page,$total,self::TEAM_MEMBERS_PER_PAGE)];
    }

    private function pagination(int $page,int $total,int $perPage):array{$offset=($page-1)*$perPage;return['current_page'=>$page,'last_page'=>max(1,(int)ceil($total/$perPage)),'per_page'=>$perPage,'total'=>$total,'from'=>$total?$offset+1:null,'to'=>$total?min($offset+$perPage,$total):null];}

    public function leaderboard(int $userId): array
    {
        if (!(new LeaderboardPublication($this->db))->studentVisible()) {
            return ['visible' => false, 'event' => null, 'categories' => [], 'teams' => []];
        }
        $eventStatement = $this->db->prepare(
            "SELECT e.id, e.title, e.start_at, e.end_at
             FROM tbl_events e
             WHERE e.deleted_at IS NULL
               AND {$this->eligibleEventSql('e')}
             ORDER BY (EXISTS(SELECT 1 FROM vw_finalized_scores score WHERE score.event_id=e.id)
                       OR EXISTS(SELECT 1 FROM tbl_activity_score_results result WHERE result.event_id=e.id)) DESC,
                      (e.end_at >= CURRENT_TIMESTAMP) DESC,e.start_at DESC
             LIMIT 1"
        );
        $eventStatement->execute([$userId, $userId]);
        $event = $eventStatement->fetch();
        if (!$event) {
            return ['visible' => true, 'event' => null, 'categories' => [], 'teams' => []];
        }
        $eventId = (int) $event['id'];
        $event['id'] = $eventId;

        $categories = $this->db->prepare(
            "SELECT id, name
             FROM tbl_score_categories
             WHERE event_id = ?
             ORDER BY sort_order, name"
        );
        $categories->execute([$eventId]);
        $categoryRows = $categories->fetchAll();
        foreach ($categoryRows as &$category) {
            $category['id'] = 'category-'.$category['id'];
        }
        unset($category);
        $activities = $this->db->prepare('SELECT DISTINCT activity.id,activity.name FROM tbl_event_activities activity JOIN tbl_activity_score_results result ON result.activity_id=activity.id AND result.event_id=activity.event_id WHERE activity.event_id=? ORDER BY activity.name');
        $activities->execute([$eventId]);
        foreach ($activities->fetchAll() as $activity) $categoryRows[] = ['id' => 'activity-'.$activity['id'], 'name' => $activity['name']];

        $teams = $this->db->prepare(
            "SELECT t.id, t.name, t.color,
                    COALESCE(members.members_count,0) AS members_count,
                    COALESCE(scores.total_score,0) AS total_score,
                    COALESCE(scores.score_entries,0) AS score_entries,
                    scores.last_scored_at
             FROM tbl_teams t
             LEFT JOIN (SELECT team_id,COUNT(DISTINCT user_id) AS members_count FROM tbl_team_user GROUP BY team_id) members ON members.team_id=t.id
             LEFT JOIN (
                 SELECT team_id,SUM(points) AS total_score,COUNT(*) AS score_entries,MAX(updated_at) AS last_scored_at FROM (
                     SELECT team_id,points,updated_at FROM vw_finalized_scores WHERE event_id=?
                     UNION ALL
                     SELECT team_id,overall_points AS points,finalized_at AS updated_at FROM tbl_activity_score_results WHERE event_id=?
                 ) official_scores GROUP BY team_id
             ) scores ON scores.team_id=t.id
             WHERE t.is_active = 1
             ORDER BY scores.score_entries > 0 DESC, COALESCE(scores.total_score,0) DESC, t.name"
        );
        $teams->execute([$eventId, $eventId]);
        $teamRows = $teams->fetchAll();

        $categoryScores = $this->db->prepare(
            "SELECT team_id,score_key,SUM(points) AS points FROM (
                 SELECT team_id,CONCAT('category-',score_category_id) AS score_key,points FROM vw_finalized_scores WHERE event_id=? AND score_category_id IS NOT NULL
                 UNION ALL
                 SELECT team_id,CONCAT('activity-',activity_id),overall_points FROM tbl_activity_score_results WHERE event_id=?
             ) official_scores GROUP BY team_id,score_key"
        );
        $categoryScores->execute([$eventId, $eventId]);
        $scoreMap = [];
        foreach ($categoryScores->fetchAll() as $score) {
            $scoreMap[(int) $score['team_id']][$score['score_key']] = (float) $score['points'];
        }

        $rank = 0;
        $lastPoints = null;
        $lastRank = null;
        foreach ($teamRows as &$team) {
            $rank++;
            $team['id'] = (int) $team['id'];
            $team['members_count'] = (int) $team['members_count'];
            $team['total_score'] = (float) $team['total_score'];
            $team['has_score'] = (int) $team['score_entries'] > 0;
            $team['category_scores'] = $scoreMap[$team['id']] ?? [];
            if (!$team['has_score']) {
                $team['rank'] = null;
            } elseif ($lastPoints !== null && abs($team['total_score'] - $lastPoints) < 0.00001) {
                $team['rank'] = $lastRank;
            } else {
                $team['rank'] = $rank;
                $lastRank = $rank;
                $lastPoints = $team['total_score'];
            }
            unset($team['score_entries']);
        }
        unset($team);

        return ['visible' => true, 'event' => $event, 'categories' => $categoryRows, 'teams' => $teamRows];
    }

    private function eligibleEventSql(string $eventAlias): string
    {
        return "(
            EXISTS (SELECT 1 FROM tbl_event_user eu WHERE eu.event_id = {$eventAlias}.id AND eu.user_id = ?)
            OR EXISTS (SELECT 1 FROM tbl_event_membership_snapshots membership WHERE membership.event_id={$eventAlias}.id AND membership.user_id=?)
        )";
    }

    private function attachSchedules(array &$events): void
    {
        if (!$events) {
            return;
        }
        $ids = array_map(static fn (array $event): int => (int) $event['id'], $events);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->prepare(
            "SELECT eas.event_id, eas.schedule_date, asm.name AS mode,
                    eas.whole_day_in_time, eas.whole_day_out_time,
                    eas.morning_in_time, eas.morning_out_time,
                    eas.afternoon_in_time, eas.afternoon_out_time
             FROM tbl_event_attendance_schedules eas
             JOIN tbl_attendance_session_modes asm ON asm.id = eas.attendance_session_mode_id
             WHERE eas.event_id IN ({$placeholders})
             ORDER BY eas.schedule_date"
        );
        $statement->execute($ids);
        $byEvent = [];
        foreach ($statement->fetchAll() as $schedule) {
            $byEvent[(int) $schedule['event_id']][] = $schedule;
        }
        foreach ($events as &$event) {
            $event['schedules'] = $byEvent[(int) $event['id']] ?? [];
        }
        unset($event);
    }

    private function teamRank(int $teamId): ?int
    {
        $rows = $this->db->query(
            "SELECT t.id, COUNT(s.team_id) AS entries, COALESCE(SUM(s.points), 0) AS points
             FROM tbl_teams t
             LEFT JOIN (SELECT team_id,points FROM vw_finalized_scores
                        UNION ALL SELECT team_id,overall_points AS points FROM tbl_activity_score_results) s ON s.team_id = t.id
             WHERE t.is_active = 1
             GROUP BY t.id
             ORDER BY COUNT(s.team_id) > 0 DESC, COALESCE(SUM(s.points), 0) DESC, t.name"
        )->fetchAll();
        foreach ($rows as $index => $row) {
            if ((int) $row['id'] === $teamId) {
                return (int) $row['entries'] > 0 ? $index + 1 : null;
            }
        }
        return null;
    }

    private function fullName(array $person): string
    {
        return trim(implode(' ', array_filter([
            $person['first_name'] ?? null,
            $person['middle_name'] ?? null,
            $person['last_name'] ?? null,
        ])));
    }

    private function initials(array $person): string
    {
        return mb_strtoupper(
            mb_substr((string) ($person['first_name'] ?? ''), 0, 1)
            .mb_substr((string) ($person['last_name'] ?? ''), 0, 1)
        );
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

$actor = AuthGuard::requireRole('Student');
$repository = new StudentPortalRepository((new Database())->connection());
$page = (string) ($_GET['page'] ?? 'events');

try {
    $data = match ($page) {
        'events' => $repository->events((int) $actor['id']),
        'attendance' => $repository->attendance((int) $actor['id']),
        'team' => $repository->team((int) $actor['id']),
        'team_members' => $repository->teamMembers((int) $actor['id'], $_GET),
        'leaderboard' => $repository->leaderboard((int) $actor['id']),
        default => null,
    };
    if ($data === null) {
        JsonResponse::send(['success' => false, 'message' => 'Unknown student page.'], 404);
    }
    JsonResponse::send(['success' => true, 'data' => $data]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'The student page could not be loaded.'], 500);
}
