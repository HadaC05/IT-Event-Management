<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/AttendanceScanWindows.php';

final class StudentAttendanceQrRepository
{
    public function __construct(private readonly PDO $db) {}

    public function issue(int $studentId): array
    {
        $now = AttendanceScanWindows::now();
        $today = $now->format('Y-m-d');
        $query = $this->db->prepare("SELECT e.id AS event_id,e.title,e.audience_type,s.*,
                m.code AS session_mode
            FROM tbl_events e
            JOIN tbl_event_attendance_schedules s ON s.event_id=e.id
            JOIN tbl_attendance_session_modes m ON m.id=s.attendance_session_mode_id
            WHERE e.deleted_at IS NULL AND s.schedule_date=?
            ORDER BY e.start_at,s.id");
        $query->execute([$today]);
        $cards = [];
        foreach ($query->fetchAll() as $schedule) {
            if (!$this->eligible($studentId, $schedule)) continue;
            $sessions = match ($schedule['session_mode']) {
                'whole_day' => ['whole_day'],
                'two_sessions' => ['morning','afternoon'],
                default => [],
            };
            foreach ($sessions as $session) {
                try { $windows = AttendanceScanWindows::forSession($schedule, $session); }
                catch (DomainException $exception) { continue; }
                $record = $this->attendance((int) $schedule['event_id'], $studentId, $today);
                $inColumn = $session === 'afternoon' ? 'afternoon_in_at' : 'morning_in_at';
                $outColumn = $session === 'afternoon' ? 'afternoon_out_at' : 'morning_out_at';
                $inAt = $record[$inColumn] ?? ($session === 'afternoon' ? null : ($record['checked_in_at'] ?? null));
                $outAt = $record[$outColumn] ?? null;
                $phase = null;
                $state = 'closed';
                if ($outAt) $state = 'already_out';
                elseif (AttendanceScanWindows::isOpen($windows['out'], $now)) {
                    $state = $inAt ? 'out_open' : 'out_open_without_in';
                    $phase = 'out';
                } elseif ($inAt) $state = 'waiting_out';
                elseif (AttendanceScanWindows::isOpen($windows['in'], $now)) {
                    $state = 'in_open'; $phase = 'in';
                } elseif ($now < $windows['in']['opens']) $state = 'waiting_in';
                elseif ($now < $windows['out']['opens']) $state = 'time_in_closed';
                $token = $phase ? $this->token((int) $schedule['event_id'], $studentId, $session, $phase, $today, $now, $inColumn, $outColumn) : null;
                if ($phase && !$token) { $state = $phase === 'in' ? 'already_in' : 'already_out'; $phase = null; }
                $cards[] = [
                    'event_name' => $schedule['title'], 'schedule_date' => $today, 'session' => $session,
                    'state' => $state, 'phase' => $phase, 'token' => $token['value'] ?? null,
                    'token_expires_at' => $token['expires_at'] ?? null,
                    'in_at' => $inAt, 'out_at' => $outAt,
                    'in_opens_at' => $windows['in']['opens']->format('Y-m-d H:i:s'),
                    'in_closes_at' => $windows['in']['closes']->format('Y-m-d H:i:s'),
                    'out_opens_at' => $windows['out']['opens']->format('Y-m-d H:i:s'),
                    'out_closes_at' => $windows['out']['closes']->format('Y-m-d H:i:s'),
                ];
            }
        }
        return ['sessions' => $cards, 'server_now' => $now->format('Y-m-d H:i:s')];
    }

    private function token(int $eventId,int $studentId,string $session,string $phase,string $date,
        DateTimeImmutable $now,string $inColumn,string $outColumn): ?array
    {
        $stamp = $now->format('Y-m-d H:i:s');
        $this->db->beginTransaction();
        try {
            $userLock=$this->db->prepare('SELECT id FROM tbl_users WHERE id=? FOR UPDATE');
            $userLock->execute([$studentId]);
            $find = $this->db->prepare('SELECT id,token,schedule_date,expires_at,used_at FROM tbl_attendance_qr_tokens
                WHERE event_id=? AND user_id=? AND session=? AND phase=? FOR UPDATE');
            $find->execute([$eventId,$studentId,$session,$phase]);
            $saved = $find->fetch();
            $attendance = $this->db->prepare("SELECT {$inColumn},{$outColumn},checked_in_at FROM tbl_attendances
                WHERE event_id=? AND user_id=? AND attendance_date=? FOR UPDATE");
            $attendance->execute([$eventId,$studentId,$date]);
            $record = $attendance->fetch();
            if ($record && ($record[$phase === 'in' ? $inColumn : $outColumn] !== null ||
                ($phase === 'in' && $session !== 'afternoon' && $record['checked_in_at'] !== null && $record[$inColumn] === null))) {
                $this->db->commit(); return null;
            }
            if ($saved && $saved['schedule_date'] === $date && !$saved['used_at'] && $saved['expires_at'] > $stamp) {
                $this->db->commit(); return ['value' => (string) $saved['token'], 'expires_at' => (string) $saved['expires_at']];
            }
            $newToken = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
            $expires = $now->modify('+60 seconds')->format('Y-m-d H:i:s');
            if ($saved) $this->db->prepare('UPDATE tbl_attendance_qr_tokens SET token=?,schedule_date=?,issued_at=?,expires_at=?,used_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?')
                ->execute([$newToken,$date,$stamp,$expires,$saved['id']]);
            else $this->db->prepare('INSERT INTO tbl_attendance_qr_tokens(event_id,user_id,session,phase,schedule_date,token,issued_at,expires_at,created_at,updated_at)
                VALUES(?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')
                ->execute([$eventId,$studentId,$session,$phase,$date,$newToken,$stamp,$expires]);
            $this->db->commit(); return ['value' => $newToken, 'expires_at' => $expires];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function attendance(int $eventId,int $studentId,string $date): array|false
    {
        $find=$this->db->prepare('SELECT checked_in_at,morning_in_at,morning_out_at,afternoon_in_at,afternoon_out_at
            FROM tbl_attendances WHERE event_id=? AND user_id=? AND attendance_date=?');
        $find->execute([$eventId,$studentId,$date]);
        return $find->fetch();
    }

    private function eligible(int $student,array $event): bool
    {
        $query=$this->db->prepare('SELECT COUNT(*) FROM tbl_event_membership_snapshots WHERE event_id=? AND user_id=?');
        $query->execute([(int)$event['event_id'],$student]);
        return (bool)$query->fetchColumn();
    }
}

if (realpath((string)($_SERVER['SCRIPT_FILENAME']??'')) !== __FILE__) return;
$actor=AuthGuard::requireRole('Student');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'],405);
if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??null)) JsonResponse::send(['success'=>false,'message'=>'Your session expired.'],403);
try {
    $result=(new StudentAttendanceQrRepository((new Database())->connection()))->issue((int)$actor['id']);
    JsonResponse::send(['success'=>true,'message'=>'Attendance QR state updated.','data'=>$result]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success'=>false,'message'=>'Attendance QR could not be prepared.'],500);
}
