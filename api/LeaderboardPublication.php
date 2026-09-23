<?php

declare(strict_types=1);

final class LeaderboardPublication
{
    public function __construct(private readonly PDO $db) {}

    public function studentVisible(): bool
    {
        $statement = $this->db->prepare('SELECT student_visible FROM tbl_leaderboard_publication WHERE id=?');
        $statement->execute([1]);
        return (int) $statement->fetchColumn() === 1;
    }

    public function setStudentVisible(bool $visible, int $actorId): bool
    {
        $this->db->beginTransaction();
        try {
            $current = $this->studentVisible();
            if ($current !== $visible) {
                $update = $this->db->prepare('UPDATE tbl_leaderboard_publication SET student_visible=?,updated_by=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
                $update->execute([$visible ? 1 : 0, $actorId, 1]);
                if ($update->rowCount() !== 1) throw new RuntimeException('Leaderboard publication setting is missing.');
                $log = $this->db->prepare('INSERT INTO tbl_activity_logs(actor_id,action,description,created_at,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
                $log->execute([$actorId, $visible ? 'leaderboard_revealed' : 'leaderboard_hidden', $visible ? 'Student leaderboard was revealed.' : 'Student leaderboard was hidden.']);
            }
            $this->db->commit();
            return $visible;
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }
}
