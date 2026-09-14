<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class AnnouncementValidationException extends RuntimeException
{
    public function __construct(private readonly array $validationErrors)
    {
        parent::__construct('Please correct the highlighted announcement fields.');
    }

    public function errors(): array
    {
        return $this->validationErrors;
    }
}

final class AnnouncementRepository
{
    private const PAGE_SIZE = 10;
    private const IMAGE_DIRECTORY = 'assets/uploads/posts';

    public function __construct(private readonly PDO $db) {}

    public function index(array $filters): array
    {
        $status = (string) ($filters['status'] ?? 'all');
        if (!in_array($status, ['all', 'draft', 'published', 'archived'], true)) {
            throw new InvalidArgumentException('Choose a valid announcement status.');
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if (mb_strlen($search) > 100) throw new InvalidArgumentException('Search may not exceed 100 characters.');
        $scope = (string) ($filters['scope'] ?? '');
        if (!in_array($scope, ['', 'event'], true)) throw new InvalidArgumentException('Choose a valid announcement scope.');
        $eventId = $this->optionalEventId($filters['event_id'] ?? null);
        $page = max(1, (int) ($filters['page'] ?? 1));

        $where = ['p.is_official=1'];
        $params = [];
        $where[] = $status === 'archived' ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL';
        if ($status === 'draft') $where[] = "p.status='draft'";
        if ($status === 'published') $where[] = "p.status='approved'";
        if ($scope === 'event') $where[] = 'p.event_id IS NOT NULL';
        if ($eventId) {$where[] = 'p.event_id=?'; $params[] = $eventId;}
        if ($search !== '') {$where[] = "p.content LIKE ? ESCAPE '\\\\'"; $params[] = '%'.$this->escapeLike($search).'%';}
        $whereSql = implode(' AND ', $where);

        $total = (int) $this->scalar("SELECT COUNT(*) FROM tbl_posts p WHERE $whereSql", $params);
        $lastPage = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * self::PAGE_SIZE;
        $statement = $this->db->prepare(
            "SELECT p.*,e.title event_title,u.first_name,u.middle_name,u.last_name,u.username
             FROM tbl_posts p LEFT JOIN tbl_events e ON e.id=p.event_id LEFT JOIN tbl_users u ON u.id=p.user_id
             WHERE $whereSql ORDER BY p.updated_at DESC,p.id DESC LIMIT ? OFFSET ?"
        );
        $index = 1;
        foreach ($params as $value) $statement->bindValue($index++, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $statement->bindValue($index++, self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->bindValue($index, $offset, PDO::PARAM_INT);
        $statement->execute();
        $announcements = $statement->fetchAll();
        foreach ($announcements as &$announcement) $announcement = $this->normalize($announcement);
        unset($announcement);

        return [
            'announcements' => $announcements,
            'events' => $this->events(),
            'pending_posts' => (int) $this->scalar("SELECT COUNT(*) FROM tbl_posts WHERE is_official=0 AND status='pending' AND deleted_at IS NULL"),
            'summary' => [
                'published' => (int) $this->scalar("SELECT COUNT(*) FROM tbl_posts WHERE is_official=1 AND status='approved' AND deleted_at IS NULL"),
                'drafts' => (int) $this->scalar("SELECT COUNT(*) FROM tbl_posts WHERE is_official=1 AND status='draft' AND deleted_at IS NULL"),
                'events' => (int) $this->scalar("SELECT COUNT(*) FROM tbl_posts WHERE is_official=1 AND event_id IS NOT NULL AND deleted_at IS NULL"),
                'archived' => (int) $this->scalar('SELECT COUNT(*) FROM tbl_posts WHERE is_official=1 AND deleted_at IS NOT NULL'),
            ],
            'pagination' => ['page' => $page, 'last_page' => $lastPage, 'per_page' => self::PAGE_SIZE, 'total' => $total],
            'filters' => ['search' => $search, 'status' => $status, 'event_id' => $eventId, 'scope' => $scope],
        ];
    }

    public function create(array $input, array $files, int $actorId): array
    {
        $data = $this->validate($input, $files);
        $imagePath = $this->storeImage($files['image'] ?? null);
        try {
            $this->db->beginTransaction();
            $status = $data['intent'] === 'publish' ? 'approved' : 'draft';
            $statement = $this->db->prepare(
                "INSERT INTO tbl_posts(user_id,event_id,category,is_official,content,image_path,status,reviewed_by,reviewed_at,created_at,updated_at)
                 VALUES(?,?,'announcement',1,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)"
            );
            $statement->execute([$actorId, $data['event_id'], $data['content'], $imagePath, $status, $status === 'approved' ? $actorId : null, $status === 'approved' ? date('Y-m-d H:i:s') : null]);
            $id = (int) $this->db->lastInsertId();
            $action = $status === 'approved' ? 'announcement_published' : 'announcement_drafted';
            $this->audit($id, $actorId, $action, null, $status);
            $this->log($actorId, $data['event_id'], $action, "Official announcement #$id was ".($status === 'approved' ? 'published.' : 'drafted.'));
            $this->db->commit();
            return ['id' => $id, 'message' => $status === 'approved' ? 'Announcement published to the student feed.' : 'Announcement saved as a draft.'];
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($imagePath) $this->deleteImage($imagePath);
            throw $exception;
        }
    }

    public function update(int $id, array $input, array $files, int $actorId): string
    {
        $announcement = $this->official($id);
        $data = $this->validate($input, $files);
        $newImage = $this->storeImage($files['image'] ?? null);
        $oldImage = (string) ($announcement['image_path'] ?? '');
        $imagePath = $newImage ?: ($this->boolean($input['remove_image'] ?? false) ? null : ($oldImage ?: null));
        try {
            $this->db->beginTransaction();
            $status = $data['intent'] === 'publish' ? 'approved' : 'draft';
            $statement = $this->db->prepare("UPDATE tbl_posts SET event_id=?,content=?,image_path=?,status=?,rejection_reason=NULL,reviewed_by=?,reviewed_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND is_official=1 AND deleted_at IS NULL");
            $statement->execute([$data['event_id'], $data['content'], $imagePath, $status, $status === 'approved' ? $actorId : null, $status === 'approved' ? date('Y-m-d H:i:s') : null, $id]);
            $action = $status === 'approved' ? 'announcement_published' : 'announcement_drafted';
            $this->audit($id, $actorId, $action, (string) $announcement['status'], $status);
            $this->log($actorId, $data['event_id'], $action, "Official announcement #$id was ".($status === 'approved' ? 'published.' : 'drafted.'));
            $this->db->commit();
            if (($newImage || $imagePath === null) && $oldImage && $oldImage !== $imagePath) $this->deleteImage($oldImage);
            return $status === 'approved' ? 'Announcement updated and published.' : 'Announcement updated and moved to drafts.';
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($newImage) $this->deleteImage($newImage);
            throw $exception;
        }
    }

    public function updateStatus(int $id, string $status, int $actorId): string
    {
        if (!in_array($status, ['draft', 'approved'], true)) throw new AnnouncementValidationException(['status' => ['Choose draft or approved.']]);
        $announcement = $this->official($id);
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare("UPDATE tbl_posts SET status=?,reviewed_by=?,reviewed_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=?");
            $statement->execute([$status, $status === 'approved' ? $actorId : null, $status === 'approved' ? date('Y-m-d H:i:s') : null, $id]);
            $action = $status === 'approved' ? 'announcement_published' : 'announcement_unpublished';
            $this->audit($id, $actorId, $action, (string) $announcement['status'], $status);
            $this->log($actorId, $announcement['event_id'] === null ? null : (int) $announcement['event_id'], $action, "Official announcement #$id was ".($status === 'approved' ? 'published.' : 'unpublished.'));
            $this->db->commit();
            return $status === 'approved' ? 'Announcement published.' : 'Announcement returned to drafts.';
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function archive(int $id, int $actorId): string
    {
        $announcement = $this->official($id);
        $this->db->beginTransaction();
        try {
            $this->audit($id, $actorId, 'announcement_archived', (string) $announcement['status'], null);
            $this->log($actorId, $announcement['event_id'] === null ? null : (int) $announcement['event_id'], 'announcement_archived', "Official announcement #$id was archived.");
            $this->db->prepare('UPDATE tbl_posts SET deleted_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$id]);
            $this->db->commit();
            return 'Announcement archived.';
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function restore(int $id, int $actorId): string
    {
        $announcement = $this->official($id, true);
        if ($announcement['deleted_at'] === null) throw new AnnouncementValidationException(['announcement' => ['Announcement is not archived.']]);
        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE tbl_posts SET deleted_at=NULL,status='draft',reviewed_by=NULL,reviewed_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$id]);
            $this->audit($id, $actorId, 'announcement_restored', null, 'draft');
            $this->log($actorId, $announcement['event_id'] === null ? null : (int) $announcement['event_id'], 'announcement_restored', "Official announcement #$id was restored.");
            $this->db->commit();
            return 'Announcement restored as a draft.';
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function validate(array $input, array $files): array
    {
        $errors = [];
        $content = trim((string) ($input['content'] ?? ''));
        if ($content === '') $errors['content'][] = 'The announcement message is required.';
        elseif (mb_strlen($content) > 3000) $errors['content'][] = 'The announcement message may not exceed 3,000 characters.';
        $intent = (string) ($input['intent'] ?? '');
        if (!in_array($intent, ['draft', 'publish'], true)) $errors['intent'][] = 'Choose whether to save or publish the announcement.';
        try {$eventId = $this->optionalEventId($input['event_id'] ?? null);} catch (InvalidArgumentException) {$eventId = null; $errors['event_id'][] = 'Choose a valid event feed.';}
        $this->validateImage($files['image'] ?? null, $errors);
        if ($errors) throw new AnnouncementValidationException($errors);
        return ['content' => $content, 'intent' => $intent, 'event_id' => $eventId];
    }

    private function validateImage(mixed $file, array &$errors): void
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return;
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {$errors['image'][] = 'The image could not be uploaded.'; return;}
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) $errors['image'][] = 'The image may not exceed 5 MB.';
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) $errors['image'][] = 'Use a JPG, PNG, or WebP image.';
        $dimensions = @getimagesize((string) $file['tmp_name']);
        if (!$dimensions) $errors['image'][] = 'Upload a valid image.';
        elseif ($dimensions[0] > 4096 || $dimensions[1] > 4096) $errors['image'][] = 'The image dimensions may not exceed 4,096 × 4,096 pixels.';
    }

    private function storeImage(mixed $file): ?string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$extension) return null;
        $directory = dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::IMAGE_DIRECTORY);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('Announcement image directory could not be created.');
        $name = bin2hex(random_bytes(16)).'.'.$extension;
        if (!move_uploaded_file((string) $file['tmp_name'], $directory.DIRECTORY_SEPARATOR.$name)) throw new RuntimeException('Announcement image could not be stored.');
        return self::IMAGE_DIRECTORY.'/'.$name;
    }

    private function deleteImage(string $path): void
    {
        if (!str_starts_with($path, self::IMAGE_DIRECTORY.'/')) return;
        $file = dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($file)) unlink($file);
    }

    private function official(int $id, bool $includeArchived = false): array
    {
        $sql = 'SELECT * FROM tbl_posts WHERE id=? AND is_official=1'.($includeArchived ? '' : ' AND deleted_at IS NULL');
        $statement = $this->db->prepare($sql);
        $statement->execute([$id]);
        $row = $statement->fetch();
        if (!$row) throw new AnnouncementValidationException(['announcement' => ['Official announcement not found.']]);
        return $row;
    }

    private function events(): array
    {
        $rows = $this->db->query('SELECT id,title,start_at FROM tbl_events WHERE deleted_at IS NULL ORDER BY start_at DESC,id DESC')->fetchAll();
        foreach ($rows as &$row) $row['id'] = (int) $row['id'];
        unset($row);
        return $rows;
    }

    private function optionalEventId(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        if (!ctype_digit((string) $value) || (int) $value < 1 || !(bool) $this->scalar('SELECT id FROM tbl_events WHERE id=? AND deleted_at IS NULL', [(int) $value])) {
            throw new InvalidArgumentException('Choose a valid event feed.');
        }
        return (int) $value;
    }

    private function normalize(array $row): array
    {
        foreach (['id', 'user_id', 'event_id', 'reviewed_by'] as $field) $row[$field] = $row[$field] === null ? null : (int) $row[$field];
        $row['is_official'] = (bool) $row['is_official'];
        $row['is_archived'] = $row['deleted_at'] !== null;
        $row['author_name'] = trim(implode(' ', array_filter([$row['first_name'], $row['middle_name'], $row['last_name']]))) ?: $row['username'];
        unset($row['first_name'], $row['middle_name'], $row['last_name'], $row['username']);
        return $row;
    }

    private function audit(int $postId, int $actorId, string $action, ?string $from, ?string $to): void
    {
        $statement = $this->db->prepare('INSERT INTO tbl_post_audits(post_id,actor_id,action,from_status,to_status,created_at,updated_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $statement->execute([$postId, $actorId, $action, $from, $to]);
    }

    private function log(int $actorId, ?int $eventId, string $action, string $description): void
    {
        $statement = $this->db->prepare("INSERT INTO tbl_activity_logs(actor_id,event_id,action,acting_role,description,created_at,updated_at) VALUES(?,?,?,'SBO Adviser',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
        $statement->execute([$actorId, $eventId, $action, $description]);
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }

    private function escapeLike(string $value): string { return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value); }
    private function boolean(mixed $value): bool { return in_array($value, [true, 1, '1', 'true', 'on'], true); }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

$actor = AuthGuard::requireRole('SBO Adviser');
$repository = new AnnouncementRepository((new Database())->connection());
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') JsonResponse::send(['success' => true, 'data' => $repository->index($_GET)]);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) JsonResponse::send(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 403);
    $input = $_POST;
    if (!$input) {$decoded = json_decode(file_get_contents('php://input'), true); $input = is_array($decoded) ? $decoded : [];}
    $action = (string) ($input['action'] ?? 'create');
    $actorId = (int) $actor['id'];
    $result = match ($action) {
        'create' => $repository->create($input, $_FILES, $actorId),
        'update' => ['message' => $repository->update((int) ($input['id'] ?? 0), $input, $_FILES, $actorId)],
        'status' => ['message' => $repository->updateStatus((int) ($input['id'] ?? 0), (string) ($input['status'] ?? ''), $actorId)],
        'archive' => ['message' => $repository->archive((int) ($input['id'] ?? 0), $actorId)],
        'restore' => ['message' => $repository->restore((int) ($input['id'] ?? 0), $actorId)],
        default => throw new AnnouncementValidationException(['action' => ['Unknown announcement action.']]),
    };
    JsonResponse::send(['success' => true] + $result);
} catch (AnnouncementValidationException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage(), 'errors' => $exception->errors()], 422);
} catch (InvalidArgumentException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'Announcement request failed.'], 500);
}
