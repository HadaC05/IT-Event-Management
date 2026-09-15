<?php
declare(strict_types=1);
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class AdviserAttendanceScanRepository {
    public function __construct(private readonly PDO $db) {}
    public function index(array $filters):array {
        $conditions=[];$values=[];
        $columns=['event_id'=>'atd.event_id','day'=>'s.id','session'=>'ae.session_code','team_id'=>'ae.team_id','officer_id'=>'ae.recorded_by','location_status'=>'ae.location_status'];
        foreach($columns as $key=>$column){
            $value=trim((string)($filters[$key]??''));
            if($value==='')continue;
            if(in_array($key,['event_id','day','team_id','officer_id'],true)){
                if(!ctype_digit($value)||((int)$value)<1)throw new InvalidArgumentException('Invalid '.$key.' filter.');
                $value=(int)$value;
            }elseif($key==='session'&&!in_array($value,['morning','afternoon','whole_day'],true))throw new InvalidArgumentException('Invalid session filter.');
            elseif($key==='location_status'&&!in_array($value,['inside','outside','unavailable'],true))throw new InvalidArgumentException('Invalid location status filter.');
            $conditions[]=$column.'=?';$values[]=$value;
        }
        $where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
        $q=$this->db->prepare("SELECT ae.id,ae.scanned_at,ae.status,ae.session_code,ae.phase,ae.scan_latitude,ae.scan_longitude,
            ae.location_accuracy_m,ae.distance_from_venue_m,ae.location_status,ae.location_captured_at,ae.location_unavailable_reason,
            ae.venue_name_snapshot,e.title event_name,s.schedule_date,s.id schedule_id,
            1+(SELECT COUNT(*) FROM tbl_event_attendance_schedules older WHERE older.event_id=s.event_id AND older.schedule_date<s.schedule_date) day_number,
            u.id_number,u.first_name,u.middle_name,u.last_name,t.name team_name,
            officer.first_name officer_first,officer.middle_name officer_middle,officer.last_name officer_last
            FROM tbl_attendance_entries ae JOIN tbl_attendances atd ON atd.id=ae.attendance_id
            JOIN tbl_event_attendance_schedules s ON s.id=ae.event_schedule_id JOIN tbl_events e ON e.id=atd.event_id
            JOIN tbl_users u ON u.id=atd.user_id JOIN tbl_teams t ON t.id=ae.team_id
            LEFT JOIN tbl_users officer ON officer.id=ae.recorded_by".$where." ORDER BY ae.scanned_at DESC,ae.id DESC LIMIT 100");
        $q->execute($values);$rows=$q->fetchAll();
        foreach($rows as &$row){
            $row['id']=(int)$row['id'];$row['day_number']=(int)$row['day_number'];$row['schedule_id']=(int)$row['schedule_id'];
            $row['student_name']=$this->name($row['first_name'],$row['middle_name'],$row['last_name']);
            $row['officer_name']=$this->name($row['officer_first'],$row['officer_middle'],$row['officer_last'])?:'Unknown';
            foreach(['scan_latitude','scan_longitude','location_accuracy_m','distance_from_venue_m'] as $key)
                $row[$key]=$row[$key]===null?null:(float)$row[$key];
            unset($row['first_name'],$row['middle_name'],$row['last_name'],$row['officer_first'],$row['officer_middle'],$row['officer_last']);
        }unset($row);
        return ['scans'=>$rows,'options'=>$this->options()];
    }
    private function options():array {
        return [
            'events'=>$this->db->query('SELECT DISTINCT e.id,e.title FROM tbl_events e JOIN tbl_event_attendance_schedules s ON s.event_id=e.id ORDER BY e.title')->fetchAll(),
            'days'=>$this->db->query('SELECT s.id,s.schedule_date,e.title event_name,1+(SELECT COUNT(*) FROM tbl_event_attendance_schedules older WHERE older.event_id=s.event_id AND older.schedule_date<s.schedule_date) day_number FROM tbl_event_attendance_schedules s JOIN tbl_events e ON e.id=s.event_id ORDER BY e.title,s.schedule_date')->fetchAll(),
            'teams'=>$this->db->query('SELECT id,name FROM tbl_teams ORDER BY name')->fetchAll(),
            'officers'=>$this->db->query("SELECT DISTINCT u.id,u.first_name,u.middle_name,u.last_name FROM tbl_users u JOIN tbl_sbo_officer_assignments oa ON oa.officer_user_id=u.id ORDER BY u.last_name,u.first_name")->fetchAll(),
        ];
    }
    private function name(?string $first,?string $middle,?string $last):string{return trim(implode(' ',array_filter([$first,$middle,$last])));}
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))!==__FILE__)return;
$actor=AuthGuard::requireRole('SBO Adviser');
if($_SERVER['REQUEST_METHOD']!=='GET')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
try{$repository=new AdviserAttendanceScanRepository((new Database())->connection());JsonResponse::send(['success'=>true,'message'=>'Scan locations loaded.','data'=>$repository->index($_GET)]);}
catch(InvalidArgumentException $e){JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],422);}
catch(Throwable $e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>'Scan locations could not be loaded.'],500);}
