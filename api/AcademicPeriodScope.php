<?php

declare(strict_types=1);

require_once __DIR__.'/AcademicPeriodLabel.php';

final class AcademicPeriodScope
{
    public function __construct(private readonly PDO $db) {}

    public function periods(): array
    {
        $rows=$this->db->query("SELECT ap.id,ap.school_year_id,ap.term_code,ap.term_name,ap.starts_on,ap.ends_on,ap.is_active,sy.label school_year_label FROM tbl_academic_periods ap JOIN tbl_school_years sy ON sy.id=ap.school_year_id ORDER BY ap.is_active DESC,COALESCE(ap.starts_on,'9999-12-31') DESC,sy.id DESC,ap.id DESC")->fetchAll();
        foreach($rows as &$row){$row['id']=(int)$row['id'];$row['school_year_id']=(int)$row['school_year_id'];$row['is_active']=(bool)$row['is_active'];$row['term_name']=AcademicPeriodLabel::termName((string)$row['term_name']);$row['label']=AcademicPeriodLabel::display((string)$row['school_year_label'],(string)$row['term_name']);}unset($row);
        return $rows;
    }

    public function period(int $periodId): array
    {
        $statement=$this->db->prepare('SELECT ap.*,sy.label school_year_label FROM tbl_academic_periods ap JOIN tbl_school_years sy ON sy.id=ap.school_year_id WHERE ap.id=?');$statement->execute([$periodId]);$period=$statement->fetch();
        if(!$period)throw new InvalidArgumentException('Select a valid academic period.');
        $period['id']=(int)$period['id'];$period['school_year_id']=(int)$period['school_year_id'];$period['term_name']=AcademicPeriodLabel::termName((string)$period['term_name']);$period['label']=AcademicPeriodLabel::display((string)$period['school_year_label'],(string)$period['term_name']);return $period;
    }

    public function teamIdsForEvent(int $eventId,bool $includeHistoricalScores=false):array
    {
        $sql="SELECT DISTINCT t.id FROM tbl_events e JOIN tbl_academic_periods ap ON ap.id=e.academic_period_id JOIN tbl_teams t ON t.school_year_id=ap.school_year_id WHERE e.id=? AND ((t.is_active=1 AND (e.audience_type='all_students' OR (e.audience_type='selected_tribes' AND EXISTS(SELECT 1 FROM tbl_event_team et WHERE et.event_id=e.id AND et.team_id=t.id)) OR (e.audience_type IN ('selected_year_levels','specific_students') AND EXISTS(SELECT 1 FROM tbl_event_membership_snapshots ms WHERE ms.event_id=e.id AND ms.team_id=t.id))))";
        if($includeHistoricalScores)$sql.=" OR EXISTS(SELECT 1 FROM tbl_scores old_score WHERE old_score.event_id=e.id AND old_score.team_id=t.id)";
        $sql.=') ORDER BY t.id';$statement=$this->db->prepare($sql);$statement->execute([$eventId]);return array_map('intval',$statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function teamIsInEvent(int $eventId,int $teamId):bool{return in_array($teamId,$this->teamIdsForEvent($eventId,true),true);}
    public function snapshotCount(int $eventId):int{$statement=$this->db->prepare('SELECT COUNT(*) FROM tbl_event_membership_snapshots WHERE event_id=?');$statement->execute([$eventId]);return(int)$statement->fetchColumn();}

    public function refreshMembershipSnapshot(int $eventId):int
    {
        $statement=$this->db->prepare('SELECT id,academic_period_id,audience_type FROM tbl_events WHERE id=? AND deleted_at IS NULL');$statement->execute([$eventId]);$event=$statement->fetch();if(!$event)throw new InvalidArgumentException('Event not found.');$period=$this->period((int)$event['academic_period_id']);
        $this->db->prepare('DELETE FROM tbl_event_membership_snapshots WHERE event_id=?')->execute([$eventId]);
        $audience=match($event['audience_type']){
            'selected_tribes'=>' AND EXISTS(SELECT 1 FROM tbl_event_team et WHERE et.event_id=e.id AND et.team_id=t.id)',
            'selected_year_levels'=>' AND EXISTS(SELECT 1 FROM tbl_event_year_level eyl WHERE eyl.event_id=e.id AND eyl.year_level_id=u.year_level)',
            'specific_students'=>' AND EXISTS(SELECT 1 FROM tbl_event_participants ep WHERE ep.event_id=e.id AND ep.user_id=u.id)',
            default=>'',
        };
        $sql="INSERT INTO tbl_event_membership_snapshots(event_id,academic_period_id,user_id,team_id,student_id_number,student_name,year_level_id,year_level_label,team_name,team_color,captured_at)
            SELECT e.id,e.academic_period_id,u.id,MAX(t.id),u.id_number,TRIM(CONCAT_WS(' ',u.first_name,NULLIF(u.middle_name,''),u.last_name)),u.year_level,MAX(yl.label),MAX(t.name),MAX(t.color),CURRENT_TIMESTAMP
            FROM tbl_events e JOIN tbl_users u JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' JOIN tbl_user_statuses us ON us.id=u.status AND us.label='active'
            LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level LEFT JOIN tbl_team_user tu ON tu.user_id=u.id LEFT JOIN tbl_teams t ON t.id=tu.team_id AND t.school_year_id=?
            WHERE e.id=?$audience GROUP BY e.id,u.id ORDER BY u.id";
        $insert=$this->db->prepare($sql);$insert->execute([$period['school_year_id'],$eventId]);return$insert->rowCount();
    }
}
