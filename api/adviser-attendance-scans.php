<?php
declare(strict_types=1);
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class AdviserAttendanceScanRepository {
    private const PAGE_SIZE=10;
    public function __construct(private readonly PDO $db) {}
    public function index(array $filters):array {
        $pageRaw=trim((string)($filters['page']??'1'));
        if(!ctype_digit($pageRaw)||(int)$pageRaw<1)throw new InvalidArgumentException('Invalid page.');
        $page=(int)$pageRaw;
        $perPage=PageSize::from($filters,self::PAGE_SIZE);
        $conditions=[];$values=[];
        $columns=['event_id'=>'atd.event_id','event_type_id'=>'e.event_type_id','schedule_date'=>'s.schedule_date','phase'=>'ae.phase','session'=>'ae.session_code','team_id'=>'ae.team_id','officer_id'=>'ae.recorded_by','location_status'=>'ae.location_status'];
        foreach($columns as $key=>$column){
            $value=trim((string)($filters[$key]??''));
            if($value==='')continue;
            if(in_array($key,['event_id','event_type_id','team_id','officer_id'],true)){
                if(!ctype_digit($value)||((int)$value)<1)throw new InvalidArgumentException('Invalid '.$key.' filter.');
                $value=(int)$value;
            }elseif($key==='schedule_date'){
                $date=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
                if(!$date||$date->format('Y-m-d')!==$value)throw new InvalidArgumentException('Invalid calendar date filter.');
            }elseif($key==='phase'&&!in_array($value,['in','out'],true))throw new InvalidArgumentException('Invalid scan phase filter.');
            elseif($key==='session'&&!in_array($value,['morning','afternoon','whole_day'],true))throw new InvalidArgumentException('Invalid session filter.');
            elseif($key==='location_status'&&!in_array($value,['inside','outside','unavailable'],true))throw new InvalidArgumentException('Invalid location status filter.');
            $conditions[]=$column.'=?';$values[]=$value;
        }
        // Keep the officer summary independent from the distance-group filter.
        $latestWhere=$conditions?' WHERE '.implode(' AND ',$conditions):'';
        $latestValues=$values;
        $summaryDate=trim((string)($filters['schedule_date']??''));
        $summaryWhere=$summaryDate===''?'':' WHERE s.schedule_date=?';
        $summaryValues=$summaryDate=== ''?[]:[$summaryDate];
        $boxDistance='ae.distance_from_venue_box_m';
        $distanceGroups=$filters['distance_group']??[];
        if(!is_array($distanceGroups))$distanceGroups=[$distanceGroups];
        $distanceGroups=array_values(array_unique(array_filter(array_map(static fn(mixed $value):string=>trim((string)$value),$distanceGroups))));
        $invalidDistanceGroups=array_diff($distanceGroups,['inside','nearby','far']);
        if($invalidDistanceGroups)throw new InvalidArgumentException('Invalid distance filter.');
        if($distanceGroups){
            $distanceConditions=[];
            if(in_array('inside',$distanceGroups,true))$distanceConditions[]="ae.location_status='inside'";
            if(in_array('nearby',$distanceGroups,true))$distanceConditions[]="(ae.location_status='outside' AND ($boxDistance)<=150)";
            if(in_array('far',$distanceGroups,true))$distanceConditions[]="(ae.location_status='outside' AND ($boxDistance)>150)";
            if($distanceConditions)$conditions[]='('.implode(' OR ',$distanceConditions).')';
        }
        $mapWhere=$conditions?' WHERE '.implode(' AND ',$conditions):'';
        $mapValues=$values;
        $search=trim((string)($filters['search']??''));
        if(strlen($search)>100)throw new InvalidArgumentException('Student name search is too long.');
        if($search!==''){
            $conditions[]="TRIM(CONCAT_WS(' ',u.first_name,NULLIF(u.middle_name,''),u.last_name)) LIKE ?";
            $values[]='%'.$search.'%';
        }
        $where=$conditions?' WHERE '.implode(' AND ',$conditions):'';
        $includeOptionsRaw=trim((string)($filters['include_options']??'1'));
        if(!in_array($includeOptionsRaw,['0','1'],true))throw new InvalidArgumentException('Invalid options flag.');
        $includeOptions=$includeOptionsRaw==='1';
        $knownVersion=trim((string)($filters['data_version']??''));
        if($knownVersion!==''&&!preg_match('/^[a-f0-9]{40}$/',$knownVersion))throw new InvalidArgumentException('Invalid data version.');
        $dataVersion=$this->dataVersion();
        if(!$includeOptions&&$knownVersion!==''&&hash_equals($dataVersion,$knownVersion))
            return ['not_modified'=>true,'data_version'=>$dataVersion];
        $fields="ae.id,ae.recorded_by officer_id,ae.team_id,ae.scanner_mode_snapshot scanner_mode,ae.scanned_at,ae.status,ae.session_code,ae.phase,ae.scan_latitude,ae.scan_longitude,
            ae.location_accuracy_m,ae.distance_from_venue_m,ae.distance_from_venue_box_m,ae.location_status,ae.location_captured_at,ae.location_unavailable_reason,
            ae.venue_location_id,ae.venue_name_snapshot,e.id event_id,COALESCE(ae.venue_location_id,e.location_id) location_id,e.title event_name,
            COALESCE(ae.venue_latitude_snapshot,l.latitude) venue_latitude,COALESCE(ae.venue_longitude_snapshot,l.longitude) venue_longitude,
            COALESCE(ae.venue_radius_snapshot_m,l.radius) venue_radius,s.schedule_date,s.id schedule_id,
            1+(SELECT COUNT(*) FROM tbl_event_attendance_schedules older WHERE older.event_id=s.event_id AND older.schedule_date<s.schedule_date) day_number,
            u.id_number,u.first_name,u.middle_name,u.last_name,t.name team_name,t.color team_color,
            officer.first_name officer_first,officer.middle_name officer_middle,officer.last_name officer_last,
            COALESCE(officer_role.name,'Recorder') recorder_role";
        $from=" FROM tbl_attendance_entries ae JOIN tbl_attendances atd ON atd.id=ae.attendance_id
            JOIN tbl_event_attendance_schedules s ON s.id=ae.event_schedule_id JOIN tbl_events e ON e.id=atd.event_id
            LEFT JOIN tbl_locations l ON l.id=COALESCE(ae.venue_location_id,e.location_id)
            JOIN tbl_users u ON u.id=atd.user_id JOIN tbl_teams t ON t.id=ae.team_id
            LEFT JOIN tbl_users officer ON officer.id=ae.recorded_by
            LEFT JOIN tbl_roles officer_role ON officer_role.id=officer.role_id";
        $count=$this->db->prepare('SELECT COUNT(*)'.$from.$where);
        $count->execute($values);$total=(int)$count->fetchColumn();
        $lastPage=max(1,(int)ceil($total/$perPage));$page=min($page,$lastPage);$offset=($page-1)*$perPage;
        $q=$this->db->prepare('SELECT '.$fields.$from.$where.' ORDER BY ae.scanned_at DESC,ae.id DESC LIMIT '.$perPage.' OFFSET '.$offset);
        $q->execute($values);$rows=$q->fetchAll();
        $latestMapQuery=$this->db->prepare('SELECT ranked.* FROM (SELECT '.$fields.',ROW_NUMBER() OVER (PARTITION BY COALESCE(ae.recorded_by,-ae.id) ORDER BY ae.scanned_at DESC,ae.id DESC) scan_rank'.$from.$latestWhere.') ranked WHERE ranked.scan_rank<=3 ORDER BY ranked.scanned_at DESC,ranked.id DESC');
        $latestMapQuery->execute($latestValues);$latestMapRows=$latestMapQuery->fetchAll();
        if($distanceGroups){
            $mapQuery=$this->db->prepare('SELECT '.$fields.$from.$mapWhere.' ORDER BY ae.scanned_at DESC,ae.id DESC');
            $mapQuery->execute($mapValues);$mapRows=$mapQuery->fetchAll();
        }else $mapRows=$latestMapRows;
        $latestQuery=$this->db->prepare('SELECT ranked.* FROM (SELECT '.$fields.',ROW_NUMBER() OVER (PARTITION BY COALESCE(ae.recorded_by,-ae.id) ORDER BY ae.scanned_at DESC,ae.id DESC) scan_rank'.$from.$summaryWhere.') ranked WHERE ranked.scan_rank<=3 ORDER BY ranked.scanned_at DESC,ranked.id DESC');
        $latestQuery->execute($summaryValues);$latestRows=$latestQuery->fetchAll();
        $this->hydrate($rows);$this->hydrate($mapRows);$this->hydrate($latestMapRows);$this->hydrate($latestRows);
        return ['not_modified'=>false,'data_version'=>$dataVersion,'scans'=>$rows,'map_scans'=>$mapRows,'latest_map_officer_scans'=>$latestMapRows,'latest_officer_scans'=>$latestRows,'distance_counts'=>$this->distanceCounts($from,$latestWhere,$latestValues),'pagination'=>['current_page'=>$page,'last_page'=>$lastPage,'per_page'=>$perPage,'total'=>$total,'from'=>$total?$offset+1:null,'to'=>$total?min($offset+$perPage,$total):null],'options'=>$includeOptions?$this->options():null];
    }
    private function hydrate(array &$rows):void {
        foreach($rows as &$row){
            $row['id']=(int)$row['id'];$row['officer_id']=$row['officer_id']===null?null:(int)$row['officer_id'];$row['team_id']=(int)$row['team_id'];$row['event_id']=(int)$row['event_id'];$row['location_id']=$row['location_id']===null?null:(int)$row['location_id'];$row['venue_location_id']=$row['venue_location_id']===null?null:(int)$row['venue_location_id'];$row['day_number']=(int)$row['day_number'];$row['schedule_id']=(int)$row['schedule_id'];
            $row['student_name']=$this->name($row['first_name'],$row['middle_name'],$row['last_name']);
            $row['officer_name']=$this->name($row['officer_first'],$row['officer_middle'],$row['officer_last'])?:'Unknown';
            foreach(['scan_latitude','scan_longitude','location_accuracy_m','distance_from_venue_m','distance_from_venue_box_m','venue_latitude','venue_longitude','venue_radius'] as $key)
                $row[$key]=$row[$key]===null?null:(float)$row[$key];
            unset($row['first_name'],$row['middle_name'],$row['last_name'],$row['officer_first'],$row['officer_middle'],$row['officer_last'],$row['scan_rank']);
        }unset($row);
    }
    private function options():array {
        return [
            'events'=>$this->db->query('SELECT DISTINCT e.id,e.title,e.event_type_id,s.schedule_date,e.location_id,l.name venue_name,l.latitude venue_latitude,l.longitude venue_longitude,l.radius venue_radius FROM tbl_events e JOIN tbl_event_attendance_schedules s ON s.event_id=e.id LEFT JOIN tbl_locations l ON l.id=e.location_id ORDER BY s.schedule_date,e.title')->fetchAll(),
            'event_locations'=>$this->db->query('SELECT e.id event_id,s.schedule_date,l.id location_id,l.name venue_name,l.latitude venue_latitude,l.longitude venue_longitude,l.radius venue_radius,el.is_primary FROM tbl_events e JOIN tbl_event_attendance_schedules s ON s.event_id=e.id JOIN tbl_event_locations el ON el.event_id=e.id JOIN tbl_locations l ON l.id=el.location_id ORDER BY s.schedule_date,e.title,el.is_primary DESC,l.name')->fetchAll(),
            'event_types'=>$this->db->query('SELECT id,label FROM tbl_event_types ORDER BY label')->fetchAll(),
            'teams'=>$this->db->query('SELECT id,name,color FROM tbl_teams ORDER BY name')->fetchAll(),
            'officers'=>$this->db->query("SELECT DISTINCT u.id,u.first_name,u.middle_name,u.last_name FROM tbl_users u JOIN tbl_attendance_entries ae ON ae.recorded_by=u.id ORDER BY u.last_name,u.first_name")->fetchAll(),
        ];
    }
    private function distanceCounts(string $from,string $where,array $values):array {
        $query=$this->db->prepare("SELECT
            COALESCE(SUM(ae.location_status='outside' AND ae.distance_from_venue_box_m<=150),0) nearby,
            COALESCE(SUM(ae.location_status='outside' AND ae.distance_from_venue_box_m>150),0) far
            ".$from.$where);
        $query->execute($values);
        $counts=$query->fetch();
        return ['nearby'=>(int)$counts['nearby'],'far'=>(int)$counts['far']];
    }
    private function dataVersion():string {
        $row=$this->db->query("SELECT COUNT(*) attendance_count,COALESCE(MAX(ae.id),0) latest_attendance_id,COALESCE(MAX(ae.updated_at),'') attendance_updated,
            (SELECT COALESCE(MAX(t.updated_at),'') FROM tbl_teams t) team_updated,
            (SELECT COALESCE(MAX(l.updated_at),'') FROM tbl_locations l) location_updated,
            (SELECT COALESCE(MAX(e.updated_at),'') FROM tbl_events e) event_updated,
            (SELECT COALESCE(MAX(sea.updated_at),'') FROM tbl_sbo_event_assignments sea) assignment_updated
            FROM tbl_attendance_entries ae")->fetch();
        return sha1(json_encode($row,JSON_THROW_ON_ERROR));
    }
    private function name(?string $first,?string $middle,?string $last):string{return trim(implode(' ',array_filter([$first,$middle,$last])));}
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))!==__FILE__)return;
$actor=AuthGuard::requireRole('SBO Adviser');
if($_SERVER['REQUEST_METHOD']!=='GET')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
try{$repository=new AdviserAttendanceScanRepository((new Database())->connection());JsonResponse::send(['success'=>true,'message'=>'Scan locations loaded.','data'=>$repository->index($_GET)]);}
catch(InvalidArgumentException $e){JsonResponse::send(['success'=>false,'message'=>$e->getMessage()],422);}
catch(Throwable $e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>'Scan locations could not be loaded.'],500);}
