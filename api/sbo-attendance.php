<?php
declare(strict_types=1);
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/SboAuthorization.php';

final class ScanRateLimitException extends RuntimeException {}

final class SboAttendanceRepository {
    private const ZONE='Asia/Manila';
    public function __construct(private readonly PDO $db,private readonly SboAuthorization $auth) {}
    private function now():DateTimeImmutable{return new DateTimeImmutable('now',new DateTimeZone(self::ZONE));}
    private function row(string $sql,array $values=[]):array|false{$q=$this->db->prepare($sql);$q->execute($values);return $q->fetch();}
    private function name(array $r):string{return trim(implode(' ',array_filter([$r['first_name']??null,$r['middle_name']??null,$r['last_name']??null])));}

    public function dashboard(int $officer,int $requested=0):array {
        $all=$this->auth->assignments($officer,'attendance');$selected=null;$next=null;$now=$this->now();
        foreach($all as &$a){
            $a+=$this->venue((int)$a['event_id']);
            if($requested>0&&$a['id']===$requested)$selected=$a;
            $start=new DateTimeImmutable($a['schedule_date'].' '.$a['session_start'],new DateTimeZone(self::ZONE));
            if($start>$now&&(!$next||$start<$next['date']))$next=['date'=>$start,'name'=>$a['session_name'],'event'=>$a['event_name']];
        }unset($a);
        if(!$selected&&$requested===0) {
            foreach($all as $candidate) if($candidate['is_session_active']) {$selected=$candidate;break;}
            if(!$selected&&count($all)===1)$selected=$all[0];
        }
        return ['assignments'=>$all,'selected_assignment'=>$selected,'server_now'=>$now->format('Y-m-d H:i:s'),
            'next_session'=>$next?['name'=>$next['name'],'event'=>$next['event'],'starts_at'=>$next['date']->format('Y-m-d H:i:s')]:null,
            'recent_scans'=>$selected?$this->recent($selected):[],'counts'=>$selected?$this->counts($selected):['total'=>0,'remaining'=>0]];
    }

    public function scan(int $officer,array $input):array {
        $this->rateLimit($officer);
        $id=filter_var($input['assignment_id']??null,FILTER_VALIDATE_INT);
        if(!$id||$id<1)throw new InvalidArgumentException('Select an active attendance assignment.');
        $a=$this->auth->assignment($officer,$id,'attendance');
        return $this->scanResolved($officer,$input,$a,false);
    }

    public function scanFaculty(int $faculty,array $input,array $assignment):array {
        $this->rateLimit($faculty);
        return $this->scanResolved($faculty,$input,$assignment,true);
    }

    private function scanResolved(int $officer,array $input,array $a,bool $faculty):array {
        $id=$faculty?null:(int)$a['id'];
        $checkpoint=(string)($input['checkpoint']??'in');
        if(!in_array($checkpoint,['in','out'],true))throw new InvalidArgumentException('Choose time in or time out.');
        $nowDate=$this->now();
        $windows=AttendanceScanWindows::forSession($a,$a['session_code']);
        if(!AttendanceScanWindows::isOpen($windows[$checkpoint],$nowDate))
            throw new InvalidArgumentException('The Time '.($checkpoint==='in'?'In':'Out').' scan window is closed.');
        $a+=$this->venue((int)$a['event_id']);
        $mode=(string)($input['mode']??'qr');
        if(!in_array($mode,['qr','manual'],true))throw new InvalidArgumentException('Invalid scan mode.');
        $value=trim((string)($input[$mode==='manual'?'student_id':'token']??''));
        if($value===''||strlen($value)>80)throw new InvalidArgumentException($mode==='qr'?'Invalid QR code or student not found.':'Enter a valid Student ID.');
        if($mode==='qr'){
            if(!preg_match('/^[A-Za-z0-9_-]{32,48}$/',$value))throw new InvalidArgumentException('Invalid QR code or student not found.');
            $student=$this->row('SELECT q.id qr_id,q.phase qr_phase,q.schedule_date qr_date,u.* FROM tbl_attendance_qr_tokens q JOIN tbl_users u ON u.id=q.user_id WHERE q.token=? AND q.event_id=? AND q.session=? LIMIT 1',[$value,$a['event_id'],$a['session_code']]);
        }else $student=$this->row("SELECT u.* FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE u.id_number=? LIMIT 1",[$value]);
        if(!$student)throw new InvalidArgumentException($mode==='qr'?'Invalid QR code or student not found.':'Student ID was not found.');
        if($mode==='qr'&&$student['qr_phase']!==$checkpoint)
            throw new InvalidArgumentException('This student QR is for Time '.($student['qr_phase']==='in'?'In':'Out').', not Time '.($checkpoint==='in'?'In':'Out').'.');
        $membership=$this->row('SELECT ms.team_id id,COALESCE(ms.team_name,t.name) name FROM tbl_event_membership_snapshots ms LEFT JOIN tbl_teams t ON t.id=ms.team_id WHERE ms.event_id=? AND ms.user_id=? LIMIT 1',[(int)$a['event_id'],(int)$student['id']]);
        if(!$membership){
            throw new InvalidArgumentException('Scan rejected. This student has no active team.');
        }
        if($a['scanner_mode']!=='general'&&(int)$membership['id']!==$a['scanner_team_id'])
            throw new InvalidArgumentException('Scan rejected. This student belongs to '.$membership['name'].', but your scanner is limited to '.$a['scanner_team_name'].'.');
        if(!$this->auth->studentIsEligible($a,(int)$student['id'],(int)$membership['id']))throw new InvalidArgumentException('This student is not registered for the current event.');
        $location=$this->location($a,$input['location']??null);
        $now=$nowDate->format('Y-m-d H:i:s');
        $inColumn=$a['session_code']==='afternoon'?'afternoon_in_at':'morning_in_at';
        $outColumn=$a['session_code']==='afternoon'?'afternoon_out_at':'morning_out_at';
        $this->db->beginTransaction();
        try {
            $recordedNow=AttendanceScanWindows::now();
            if(!AttendanceScanWindows::isOpen($windows[$checkpoint],$recordedNow))
                throw new InvalidArgumentException('The Time '.($checkpoint==='in'?'In':'Out').' scan window is closed.');
            $now=$recordedNow->format('Y-m-d H:i:s');
            $parent=$this->row('SELECT id,checked_in_at,morning_in_at,morning_out_at,afternoon_in_at,afternoon_out_at FROM tbl_attendances WHERE event_id=? AND user_id=? AND attendance_date=? FOR UPDATE',[$a['event_id'],$student['id'],$a['schedule_date']]);
            if($parent){
                $attendanceId=(int)$parent['id'];
                $entry=$this->row('SELECT id FROM tbl_attendance_entries WHERE attendance_id=? AND event_schedule_id=? AND session_code=? AND phase=? LIMIT 1 FOR UPDATE',[$attendanceId,$a['event_schedule_id'],$a['session_code'],$checkpoint]);
                $inEntry=$checkpoint==='out'?$this->row("SELECT id FROM tbl_attendance_entries WHERE attendance_id=? AND event_schedule_id=? AND session_code=? AND phase='in' LIMIT 1 FOR UPDATE",[$attendanceId,$a['event_schedule_id'],$a['session_code']]):false;
            }else{
                if($checkpoint==='out')throw new LogicException('Record time in before scanning time out.');
                $this->db->prepare("INSERT INTO tbl_attendances(event_id,user_id,attendance_date,status,checked_in_at,recorded_by,created_at,updated_at) VALUES(?,?,?,'present',?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
                    ->execute([$a['event_id'],$student['id'],$a['schedule_date'],$now,$officer]);
                $attendanceId=(int)$this->db->lastInsertId();
                $entry=false;
                $inEntry=false;
                $parent=[$inColumn=>null,$outColumn=>null,'checked_in_at'=>$now];
            }
            if($entry)throw new LogicException($mode==='qr'?'This QR was already scanned.':'Time '.$checkpoint.' already recorded for this session.');
            if($mode==='qr'){
                $locked=$this->row('SELECT id,token,phase,schedule_date,expires_at,used_at FROM tbl_attendance_qr_tokens WHERE id=? FOR UPDATE',[$student['qr_id']]);
                if(!$locked||$locked['token']!==$value||$locked['phase']!==$checkpoint||$locked['schedule_date']!==$a['schedule_date']||
                    $locked['used_at']!==null||$locked['expires_at']===null||$locked['expires_at']<=$now)
                    throw new InvalidArgumentException('This QR has expired. Ask the student to refresh their QR.');
            }
            if($checkpoint==='in'){
                if($parent[$inColumn]!==null)throw new LogicException($mode==='qr'?'This QR was already scanned.':'Time in already recorded for this session.');
                $this->db->prepare("UPDATE tbl_attendances SET {$inColumn}=?,checked_in_at=COALESCE(checked_in_at,?),status='present',updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([$now,$now,$attendanceId]);
            }else{
                if($parent[$outColumn]!==null)throw new LogicException($mode==='qr'?'This QR was already scanned.':'Time out already recorded for this session.');
                if($a['session_code']==='afternoon'?($parent[$inColumn]===null&&!$inEntry):($parent[$inColumn]===null&&$parent['checked_in_at']===null&&!$inEntry))
                    throw new LogicException('Record time in before scanning time out.');
                $this->db->prepare("UPDATE tbl_attendances SET {$outColumn}=?,updated_at=CURRENT_TIMESTAMP WHERE id=?")
                    ->execute([$now,$attendanceId]);
            }
            $this->db->prepare("INSERT INTO tbl_attendance_entries
                (attendance_id,event_schedule_id,sbo_event_assignment_id,session_code,phase,activity_id,team_id,recorded_by,scanner_mode_snapshot,scanned_at,status,
                scan_latitude,scan_longitude,location_accuracy_m,distance_from_venue_m,distance_from_venue_box_m,location_status,location_captured_at,location_unavailable_reason,
                venue_location_id,venue_name_snapshot,venue_latitude_snapshot,venue_longitude_snapshot,venue_radius_snapshot_m,created_at,updated_at)
                VALUES(?,?,?,?,?,?,?,?,?,?,'present',?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
                ->execute([$attendanceId,$a['event_schedule_id'],$id,$a['session_code'],$checkpoint,$a['activity_id'],$membership['id'],$officer,$a['scanner_mode']==='general'?'general':'specific',$now,
                    $location['latitude'],$location['longitude'],$location['accuracy_m'],$location['distance_m'],$location['distance_from_box_m'],$location['status'],$location['captured_at'],$location['reason'],
                    $location['venue_id'],$location['venue_name'],$location['venue_latitude'],$location['venue_longitude'],$location['venue_radius_m']]);
            if($mode==='qr'){
                $used=$this->db->prepare('UPDATE tbl_attendance_qr_tokens SET used_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND used_at IS NULL');
                $used->execute([$now,$student['qr_id']]);
                if($used->rowCount()!==1)throw new LogicException('This QR has already been used.');
            }
            $action=$faculty?'faculty_attendance_scanned':($checkpoint==='in'?'sbo_attendance_scanned':'sbo_attendance_time_out');
            $officerAssignmentId=$faculty?null:(int)$a['officer_assignment_id'];
            $this->db->prepare("INSERT INTO tbl_activity_logs(actor_id,event_id,officer_assignment_id,action,acting_role,description,created_at,updated_at) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")
                ->execute([$officer,$a['event_id'],$officerAssignmentId,$action,$faculty?'Faculty':'SBO Officer','Recorded time '.$checkpoint.' for '.$student['id_number'].'.']);
            $this->db->commit();
        }catch(PDOException $e){
            if($this->db->inTransaction())$this->db->rollBack();
            $driverCode=(int)($e->errorInfo[1]??0);
            if($driverCode===1062)throw new LogicException('Attendance already recorded for this session.',0,$e);
            throw $e;
        }
        catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
        return ['student'=>['id_number'=>$student['id_number'],'full_name'=>$this->name($student),'team_id'=>(int)$membership['id'],'team_name'=>$membership['name']],
            'session_name'=>$a['session_name'],'checkpoint'=>$checkpoint,'scanned_at'=>$now,'location'=>$location,
            'recent_scans'=>$faculty?$this->facultyRecent($a):$this->recent($a),
            'counts'=>$faculty?$this->facultyCounts($a):$this->counts($a)];
    }

    private function rateLimit(int $officer):void {
        $now=$this->now()->format('Y-m-d H:i:s');$this->db->beginTransaction();
        try{
            $this->db->prepare('INSERT IGNORE INTO tbl_sbo_scan_rate_limits(officer_user_id,window_started_at,attempts,updated_at) VALUES(?,?,0,?)')->execute([$officer,$now,$now]);
            $r=$this->row('SELECT window_started_at,attempts FROM tbl_sbo_scan_rate_limits WHERE officer_user_id=? FOR UPDATE',[$officer]);
            $reset=$this->now()->getTimestamp()-(new DateTimeImmutable($r['window_started_at'],new DateTimeZone(self::ZONE)))->getTimestamp()>=10;
            $attempts=$reset?1:(int)$r['attempts']+1;
            $this->db->prepare('UPDATE tbl_sbo_scan_rate_limits SET window_started_at=?,attempts=?,updated_at=? WHERE officer_user_id=?')->execute([$reset?$now:$r['window_started_at'],$attempts,$now,$officer]);
            $this->db->commit();
            if($attempts>20)throw new ScanRateLimitException('Too many scans. Wait a few seconds and try again.');
        }catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function venue(int $event):array {
        $r=$this->row('SELECT e.attendance_location_policy,e.location_id,l.name venue_name,l.latitude venue_latitude,l.longitude venue_longitude,l.radius venue_radius FROM tbl_events e LEFT JOIN tbl_locations l ON l.id=e.location_id WHERE e.id=?',[$event]);
        if(!$r)throw new DomainException('Event venue was not found.');
        $q=$this->db->prepare('SELECT l.id,l.name,l.latitude,l.longitude,l.radius,el.is_primary FROM tbl_event_locations el JOIN tbl_locations l ON l.id=el.location_id WHERE el.event_id=? ORDER BY el.is_primary DESC,l.name');
        $q->execute([$event]);$venues=$q->fetchAll();
        if(!$venues&&$r['location_id']!==null)$venues=[['id'=>$r['location_id'],'name'=>$r['venue_name'],'latitude'=>$r['venue_latitude'],'longitude'=>$r['venue_longitude'],'radius'=>$r['venue_radius'],'is_primary'=>1]];
        foreach($venues as &$venue){
            $venue['id']=(int)$venue['id'];$venue['is_primary']=(bool)$venue['is_primary'];
            foreach(['latitude','longitude','radius'] as $key)$venue[$key]=$venue[$key]===null?null:(float)$venue[$key];
        }unset($venue);
        $primary=current(array_filter($venues,static fn(array $venue):bool=>$venue['is_primary']))?:($venues[0]??null);
        return ['location_policy'=>$r['attendance_location_policy']==='strict'?'strict':'warning','venues'=>$venues,
            'venue_name'=>$primary['name']??null,'venue_latitude'=>$primary['latitude']??null,
            'venue_longitude'=>$primary['longitude']??null,'venue_radius_m'=>$primary['radius']??null];
    }
    private function location(array $a,mixed $raw):array {
        $reason=is_array($raw)?trim((string)($raw['unavailable_reason']??'')):'not_provided';
        if(!in_array($reason,['','not_provided','permission_denied','location_timeout','geolocation_unavailable','location_unavailable','stale_location','insecure_context'],true))
            $reason='invalid_location';
        $r=['latitude'=>null,'longitude'=>null,'accuracy_m'=>null,'distance_m'=>null,'distance_from_box_m'=>null,'status'=>'unavailable','captured_at'=>null,'reason'=>$reason?:'not_provided',
            'venue_id'=>null,'venue_name'=>null,'venue_latitude'=>null,'venue_longitude'=>null,'venue_radius_m'=>null,'venue_match'=>null];
        if(is_array($raw)&&isset($raw['latitude'],$raw['longitude'],$raw['accuracy_m'],$raw['timestamp_ms'])){
            $lat=filter_var($raw['latitude'],FILTER_VALIDATE_FLOAT);$lon=filter_var($raw['longitude'],FILTER_VALIDATE_FLOAT);
            $accuracy=filter_var($raw['accuracy_m'],FILTER_VALIDATE_FLOAT);$timestamp=filter_var($raw['timestamp_ms'],FILTER_VALIDATE_INT);
            if($lat!==false&&$lon!==false&&$accuracy!==false&&$timestamp!==false&&$lat>=-90&&$lat<=90&&$lon>=-180&&$lon<=180&&$accuracy>=0&&$accuracy<=10000){
                $age=time()-intdiv($timestamp,1000);
                if($age>=-10&&$age<=30){
                    $r['latitude']=$lat;$r['longitude']=$lon;$r['accuracy_m']=$accuracy;
                    $r['captured_at']=(new DateTimeImmutable('@'.intdiv($timestamp,1000)))->setTimezone(new DateTimeZone(self::ZONE))->format('Y-m-d H:i:s');
                    $r['reason']=null;
                    $inside=[];$nearest=[];
                    foreach(($a['venues']??[]) as $venue){
                        if($venue['latitude']===null||$venue['longitude']===null||$venue['radius']===null)continue;
                        $distance=$this->distance($lat,$lon,$venue['latitude'],$venue['longitude']);
                        $candidate=['venue'=>$venue,'distance'=>$distance];$nearest[]=$candidate;
                        if($this->insideBox($lat,$lon,$venue['latitude'],$venue['longitude'],$venue['radius']))$inside[]=$candidate;
                    }
                    $candidates=$inside?:$nearest;
                    usort($candidates,static fn(array $left,array $right):int=>$left['distance']<=>$right['distance']);
                    $matched=$candidates[0]??null;
                    if($matched){
                        $venue=$matched['venue'];$r['distance_m']=round($matched['distance'],2);$r['status']=$inside?'inside':'outside';
                        $r['distance_from_box_m']=$inside?0:round($this->distanceFromBox($lat,$lon,(float)$venue['latitude'],(float)$venue['longitude'],(float)$venue['radius']),2);
                        $r['venue_id']=$venue['id'];$r['venue_name']=$venue['name'];$r['venue_latitude']=$venue['latitude'];
                        $r['venue_longitude']=$venue['longitude'];$r['venue_radius_m']=$venue['radius'];$r['venue_match']=$inside?'matched':'nearest';
                    }else $r['reason']='venue_not_configured';
                }else $r['reason']='stale_location';
            }else $r['reason']='invalid_location';
        }
        if(in_array($a['location_policy'],['warning','strict'],true)){
            $configured=array_filter($a['venues']??[],static fn(array $venue):bool=>$venue['latitude']!==null&&$venue['longitude']!==null&&$venue['radius']!==null);
            if(!$configured)throw new DomainException('Scan locations are not configured for this event.');
            if($r['status']==='unavailable')throw new InvalidArgumentException('Your location could not be verified. Check your location permission and try again.');
            if($a['location_policy']==='strict'&&$r['status']==='outside')throw new InvalidArgumentException('Scan location is outside all allowed event venues.');
        }
        return $r;
    }
    private function insideBox(float $latitude,float $longitude,float $centerLatitude,float $centerLongitude,float $radius):bool {
        $latitudeDelta=$radius/111320;
        $longitudeScale=max(cos(deg2rad($centerLatitude)),0.000001);
        $longitudeDelta=$radius/(111320*$longitudeScale);
        $longitudeDifference=fmod(abs($longitude-$centerLongitude),360.0);
        if($longitudeDifference>180)$longitudeDifference=360-$longitudeDifference;
        return abs($latitude-$centerLatitude)<=$latitudeDelta&&$longitudeDifference<=$longitudeDelta;
    }
    private function distance(float $a,float $b,float $c,float $d):float {
        $h=sin(deg2rad($c-$a)/2)**2+cos(deg2rad($a))*cos(deg2rad($c))*sin(deg2rad($d-$b)/2)**2;
        return 6371000*2*asin(min(1,sqrt($h)));
    }
    private function distanceFromBox(float $latitude,float $longitude,float $centerLatitude,float $centerLongitude,float $radius):float {
        $northOutside=max(abs($latitude-$centerLatitude)*111320-$radius,0);
        $longitudeDifference=fmod(abs($longitude-$centerLongitude),360.0);
        if($longitudeDifference>180)$longitudeDifference=360-$longitudeDifference;
        $eastOutside=max($longitudeDifference*111320*max(cos(deg2rad($centerLatitude)),0.000001)-$radius,0);
        return sqrt($northOutside**2+$eastOutside**2);
    }
    private function recent(array $a):array {
        $q=$this->db->prepare('SELECT ae.id,ae.phase,ae.scanned_at,ae.status,ae.location_status,ae.venue_name_snapshot,u.id_number,u.first_name,u.middle_name,u.last_name,t.name team_name
            FROM tbl_attendance_entries ae JOIN tbl_attendances atd ON atd.id=ae.attendance_id JOIN tbl_users u ON u.id=atd.user_id JOIN tbl_teams t ON t.id=ae.team_id
            WHERE ae.sbo_event_assignment_id=? AND ae.event_schedule_id=? AND ae.session_code=? ORDER BY ae.scanned_at DESC,ae.id DESC LIMIT 12');
        $q->execute([$a['id'],$a['event_schedule_id'],$a['session_code']]);$rows=$q->fetchAll();
        foreach($rows as &$r){$r['id']=(int)$r['id'];$r['full_name']=$this->name($r);}unset($r);return $rows;
    }
    public function facultyRecent(array $a):array {
        $q=$this->db->prepare('SELECT ae.id,ae.phase,ae.scanned_at,ae.status,ae.location_status,ae.venue_name_snapshot,u.id_number,u.first_name,u.middle_name,u.last_name,t.name team_name
            FROM tbl_attendance_entries ae JOIN tbl_attendances atd ON atd.id=ae.attendance_id JOIN tbl_users u ON u.id=atd.user_id JOIN tbl_teams t ON t.id=ae.team_id
            WHERE ae.event_schedule_id=? AND ae.session_code=? AND ae.team_id=? ORDER BY ae.scanned_at DESC,ae.id DESC LIMIT 12');
        $q->execute([$a['event_schedule_id'],$a['session_code'],$a['scanner_team_id']]);$rows=$q->fetchAll();
        foreach($rows as &$r){$r['id']=(int)$r['id'];$r['full_name']=$this->name($r);}unset($r);return $rows;
    }
    public function facultyCounts(array $a):array {
        $q=$this->db->prepare("SELECT
          (SELECT COUNT(*) FROM tbl_attendance_entries ae WHERE ae.event_schedule_id=? AND ae.session_code=? AND ae.team_id=? AND ae.phase='in') total,
          (SELECT COUNT(*) FROM tbl_attendance_entries ae WHERE ae.event_schedule_id=? AND ae.session_code=? AND ae.team_id=? AND ae.phase='out') checked_out,
          (SELECT COUNT(DISTINCT ms.user_id) FROM tbl_event_membership_snapshots ms
             WHERE ms.event_id=? AND ms.team_id=?
             AND NOT EXISTS(SELECT 1 FROM tbl_attendances atd JOIN tbl_attendance_entries ae ON ae.attendance_id=atd.id
               WHERE atd.user_id=ms.user_id AND atd.event_id=? AND ae.event_schedule_id=? AND ae.session_code=? AND ae.phase='in')) remaining");
        $q->execute([$a['event_schedule_id'],$a['session_code'],$a['scanner_team_id'],
            $a['event_schedule_id'],$a['session_code'],$a['scanner_team_id'],
            $a['event_id'],$a['scanner_team_id'],$a['event_id'],$a['event_schedule_id'],$a['session_code']]);
        $r=$q->fetch();return ['total'=>(int)$r['total'],'checked_out'=>(int)$r['checked_out'],'remaining'=>(int)$r['remaining']];
    }
    private function counts(array $a):array {
        $q=$this->db->prepare("SELECT
          (SELECT COUNT(*) FROM tbl_attendance_entries ae WHERE ae.sbo_event_assignment_id=? AND ae.event_schedule_id=? AND ae.session_code=? AND ae.phase='in') total,
          (SELECT COUNT(*) FROM tbl_attendance_entries ae WHERE ae.sbo_event_assignment_id=? AND ae.event_schedule_id=? AND ae.session_code=? AND ae.phase='out') checked_out,
          (SELECT COUNT(DISTINCT ms.user_id) FROM tbl_event_membership_snapshots ms
             WHERE ms.event_id=? AND ms.team_id IS NOT NULL
             AND (?='general' OR ms.team_id=?)
             AND NOT EXISTS(SELECT 1 FROM tbl_attendances atd JOIN tbl_attendance_entries ae ON ae.attendance_id=atd.id
               WHERE atd.user_id=ms.user_id AND atd.event_id=? AND ae.event_schedule_id=? AND ae.session_code=? AND ae.phase='in')) remaining");
        $q->execute([$a['id'],$a['event_schedule_id'],$a['session_code'],$a['id'],$a['event_schedule_id'],$a['session_code'],
            $a['event_id'],$a['scanner_mode'],$a['scanner_team_id'],
            $a['event_id'],$a['event_schedule_id'],$a['session_code']]);
        $r=$q->fetch();return ['total'=>(int)$r['total'],'checked_out'=>(int)$r['checked_out'],'remaining'=>(int)$r['remaining']];
    }
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))!==__FILE__)return;
$actor=AuthGuard::requireRole('SBO Officer');$db=(new Database())->connection();
$repository=new SboAttendanceRepository($db,new SboAuthorization($db));
try{
    if($_SERVER['REQUEST_METHOD']==='GET')JsonResponse::send(['success'=>true,'message'=>'Attendance context loaded.','data'=>$repository->dashboard((int)$actor['id'],(int)($_GET['assignment_id']??0))]);
    if($_SERVER['REQUEST_METHOD']!=='POST')JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
    if(!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null))JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
    $input=json_decode(file_get_contents('php://input'),true);if(!is_array($input))$input=$_POST;
    $result=$repository->scan((int)$actor['id'],$input);
    JsonResponse::send(['success'=>true,'message'=>$result['checkpoint']==='out'?'Time out recorded successfully.':'Time in recorded successfully.','data'=>$result]);
}catch(ScanRateLimitException $e){header('Retry-After: 10');JsonResponse::send(['success'=>false,'message'=>$e->getMessage(),'code'=>'rate_limited'],429);}
catch(DomainException $e){
    $unauthorized=$e->getMessage()==='Unauthorized officer assignment.';
    JsonResponse::send(['success'=>false,'message'=>$unauthorized?'You are not authorized to record attendance for this team.':$e->getMessage(),'code'=>$unauthorized?'unauthorized':'event_unavailable'],403);
}
catch(InvalidArgumentException $e){JsonResponse::send(['success'=>false,'message'=>$e->getMessage(),'code'=>'scan_rejected'],422);}
catch(LogicException $e){JsonResponse::send(['success'=>false,'message'=>$e->getMessage(),'code'=>'duplicate'],409);}
catch(Throwable $e){error_log($e->getMessage());JsonResponse::send(['success'=>false,'message'=>'Attendance scan failed.'],500);}
