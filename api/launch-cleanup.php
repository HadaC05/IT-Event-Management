<?php

declare(strict_types=1);

// This is a one-time operator command. It must never be callable through Apache.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__.'/db_connect.php';

final class LaunchCleanup
{
    private const POST_NOTIFICATION = "CASE WHEN JSON_VALID(data) THEN JSON_EXTRACT(data, '$.post_id') IS NOT NULL ELSE 0 END";

    public function __construct(private readonly PDO $db) {}

    public static function connection(): PDO
    {
        $optionFile = '/etc/cite-events/mysql-backup.cnf';
        if (!is_file($optionFile)) return (new Database())->connection();

        $groups = parse_ini_file($optionFile, true, INI_SCANNER_RAW);
        if (!is_array($groups) || !is_array($groups['client'] ?? null)) {
            throw new RuntimeException('The production MySQL client configuration is invalid.');
        }
        $options = $groups['client'];
        $user = (string) ($options['user'] ?? '');
        if ($user === '') throw new RuntimeException('The production MySQL client user is missing.');
        $dsn = isset($options['socket'])
            ? 'mysql:unix_socket='.$options['socket'].';dbname=event_db;charset=utf8mb4'
            : 'mysql:host='.($options['host'] ?? 'localhost').';port='.($options['port'] ?? '3306').';dbname=event_db;charset=utf8mb4';

        return new PDO($dsn, $user, (string) ($options['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function report(): array
    {
        $locations = $this->db->query('SELECT id,name,type,parent_location_id FROM tbl_locations ORDER BY id')->fetchAll();
        $counts = [];
        foreach (['tbl_users', 'tbl_events', 'tbl_event_activities', 'tbl_posts', 'tbl_post_comments', 'tbl_post_reactions', 'tbl_post_audits'] as $table) {
            $counts[$table] = (int) $this->db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        }
        $counts['post_notifications'] = (int) $this->db->query('SELECT COUNT(*) FROM tbl_notifications WHERE '.self::POST_NOTIFICATION)->fetchColumn();

        $references = $this->locationReferences();
        foreach ($locations as &$location) {
            $location['id'] = (int) $location['id'];
            $location['parent_location_id'] = $location['parent_location_id'] === null ? null : (int) $location['parent_location_id'];
            $location['expected_keep'] = $this->keepKind((string) $location['name']);
            $location['references'] = [];
            foreach ($references as [$table, $column]) {
                $statement = $this->db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` = ?");
                $statement->execute([$location['id']]);
                $count = (int) $statement->fetchColumn();
                if ($count > 0) $location['references']["{$table}.{$column}"] = $count;
            }
        }
        unset($location);

        return ['counts' => $counts, 'locations' => $locations];
    }

    public function apply(array $keepIds): array
    {
        if (count($keepIds) !== 2 || count(array_unique($keepIds)) !== 2) {
            throw new RuntimeException('Provide two distinct location IDs with --keep=ID,ID.');
        }

        $this->db->beginTransaction();
        try {
            $locations = $this->db->query('SELECT id,name,parent_location_id FROM tbl_locations ORDER BY id FOR UPDATE')->fetchAll();
            $byId = [];
            $kinds = [];
            foreach ($locations as $location) {
                $id = (int) $location['id'];
                $byId[$id] = $location;
                $kind = $this->keepKind((string) $location['name']);
                if ($kind !== null) $kinds[$kind][] = $id;
            }
            foreach (['campus', 'senior_high_school'] as $kind) {
                if (count($kinds[$kind] ?? []) !== 1 || !in_array($kinds[$kind][0], $keepIds, true)) {
                    throw new RuntimeException('The two kept IDs must uniquely identify PHINMA COC Campus and Senior High School. Run --report and review the names.');
                }
            }
            foreach ($keepIds as $id) {
                if (!isset($byId[$id])) throw new RuntimeException("Kept location {$id} no longer exists.");
                $parentId = $byId[$id]['parent_location_id'];
                if ($parentId !== null && !in_array((int) $parentId, $keepIds, true)) {
                    throw new RuntimeException("Kept location {$id} depends on parent location {$parentId}, which would be deleted.");
                }
            }

            $deleteIds = array_values(array_diff(array_keys($byId), $keepIds));
            $references = $this->locationReferences();
            foreach ($references as [$table, $column]) {
                if ($deleteIds === []) break;
                $marks = implode(',', array_fill(0, count($deleteIds), '?'));
                $statement = $this->db->prepare("SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` IN ({$marks})");
                $statement->execute($deleteIds);
                $count = (int) $statement->fetchColumn();
                if ($count > 0) {
                    throw new RuntimeException("{$count} {$table}.{$column} rows still point to venues being deleted. No changes were made; resolve these references first.");
                }
            }

            $notifications = $this->db->exec('DELETE FROM tbl_notifications WHERE '.self::POST_NOTIFICATION);
            $posts = $this->db->exec('DELETE FROM tbl_posts');
            // All rows being detached are scheduled for deletion. Kept rows are unchanged.
            $marks = implode(',', array_fill(0, count($keepIds), '?'));
            $this->db->prepare("UPDATE tbl_locations SET parent_location_id = NULL WHERE id NOT IN ({$marks}) AND parent_location_id IS NOT NULL")
                ->execute($keepIds);
            $statement = $this->db->prepare("DELETE FROM tbl_locations WHERE id NOT IN ({$marks})");
            $statement->execute($keepIds);
            $deletedLocations = $statement->rowCount();

            $remaining = $this->db->query('SELECT id FROM tbl_locations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
            $remaining = array_map('intval', $remaining);
            sort($remaining);
            $expected = $keepIds;
            sort($expected);
            if ($remaining !== $expected || (int) $this->db->query('SELECT COUNT(*) FROM tbl_posts')->fetchColumn() !== 0) {
                throw new RuntimeException('Cleanup verification failed; all changes were rolled back.');
            }

            $this->db->commit();
            return ['deleted_posts' => $posts, 'deleted_post_notifications' => $notifications, 'deleted_locations' => $deletedLocations, 'kept_location_ids' => $remaining];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function locationReferences(): array
    {
        $statement = $this->db->query("SELECT TABLE_NAME,COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'tbl_locations' AND TABLE_NAME <> 'tbl_locations'");
        $known = ['tbl_events.location_id', 'tbl_event_locations.location_id', 'tbl_attendance_entries.venue_location_id'];
        foreach ($statement->fetchAll() as $row) {
            $table = (string) $row['TABLE_NAME'];
            $column = (string) $row['COLUMN_NAME'];
            if (!in_array("{$table}.{$column}", $known, true)) {
                throw new RuntimeException("Unexpected location reference {$table}.{$column}; review the current schema before cleanup.");
            }
        }
        // Check these columns even if a foreign key was omitted on an older install.
        return [
            ['tbl_events', 'location_id'],
            ['tbl_event_locations', 'location_id'],
            ['tbl_attendance_entries', 'venue_location_id'],
        ];
    }

    private function keepKind(string $name): ?string
    {
        if (preg_match('/\bSenior\s+High\s+School\b/i', $name)) return 'senior_high_school';
        if (preg_match('/\bPHINMA\s+COC\b.*\bCampus\b/i', $name)) return 'campus';
        return null;
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

try {
    $mode = $argv[1] ?? '';
    if (!in_array($mode, ['--report', '--apply'], true)) {
        throw new InvalidArgumentException('Usage: php api/launch-cleanup.php --report | --apply --keep=CAMPUS_ID,SHS_ID --backup=/absolute/path/event_db_TIMESTAMP.sql.gz');
    }
    if ($mode === '--report' && count($argv) !== 2) throw new InvalidArgumentException('--report takes no other arguments.');

    $keepIds = [];
    if ($mode === '--apply') {
        if (count($argv) !== 4 || !preg_match('/^--keep=([1-9][0-9]*),([1-9][0-9]*)$/', $argv[2], $matches)) {
            throw new InvalidArgumentException('--apply requires --keep=CAMPUS_ID,SHS_ID and --backup=/absolute/path/event_db_TIMESTAMP.sql.gz.');
        }
        $keepIds = [(int) $matches[1], (int) $matches[2]];
        if (!str_starts_with($argv[3], '--backup=')) throw new InvalidArgumentException('A fresh database backup path is required.');
        $backup = substr($argv[3], strlen('--backup='));
        $realBackup = realpath($backup);
        if ($realBackup === false || !preg_match('~^/var/backups/cite-events/event_db_[0-9]{8}T[0-9]{6}Z\.sql\.gz$~', $realBackup)
            || !is_file($realBackup) || !is_file($realBackup.'.sha256') || time() - filemtime($realBackup) > 1800) {
            throw new RuntimeException('Run the production database backup first, then pass its fresh .sql.gz path.');
        }
        $checksum = trim((string) file_get_contents($realBackup.'.sha256'));
        if (!preg_match('/^([a-f0-9]{64})\s+/', $checksum, $matches) || !hash_equals($matches[1], hash_file('sha256', $realBackup))) {
            throw new RuntimeException('The backup checksum does not match; no changes were made.');
        }
    }

    $cleanup = new LaunchCleanup(LaunchCleanup::connection());
    $result = $mode === '--report' ? $cleanup->report() : $cleanup->apply($keepIds);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
}
