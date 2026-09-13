<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/ApiSupport.php';

final class PostReviewValidationException extends RuntimeException
{
    public function __construct(private readonly array $validationErrors)
    {
        parent::__construct('Please correct the post review fields.');
    }

    public function errors(): array { return $this->validationErrors; }
}

final class PostReviewRepository
{
    private const PAGE_SIZE = 15;

    public function __construct(private readonly PDO $db) {}

    public function index(array $input): array
    {
        $requestedPage = max(1, (int) ($input['page'] ?? 1));
        $total = (int) $this->scalar('SELECT COUNT(*) FROM posts WHERE is_official=0 AND deleted_at IS NULL');
        $lastPage = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = min($requestedPage, $lastPage);
        $statement = $this->db->prepare(
            "SELECT p.*,e.title event_title,
                a.first_name author_first_name,a.middle_name author_middle_name,a.last_name author_last_name,a.username author_username,a.profile_photo_path author_photo,
                r.first_name reviewer_first_name,r.middle_name reviewer_middle_name,r.last_name reviewer_last_name,r.username reviewer_username
             FROM posts p
             LEFT JOIN events e ON e.id=p.event_id
             LEFT JOIN users a ON a.id=p.user_id
             LEFT JOIN users r ON r.id=p.reviewed_by
             WHERE p.is_official=0 AND p.deleted_at IS NULL
             ORDER BY datetime(p.created_at) DESC,p.id DESC LIMIT ? OFFSET ?"
        );
        $statement->bindValue(1, self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->bindValue(2, ($page - 1) * self::PAGE_SIZE, PDO::PARAM_INT);
        $statement->execute();
        $posts = $statement->fetchAll();
        foreach ($posts as &$post) $post = $this->normalize($post);
        unset($post);

        return [
            'posts' => $posts,
            'pending_count' => (int) $this->scalar("SELECT COUNT(*) FROM posts WHERE is_official=0 AND status='pending' AND deleted_at IS NULL"),
            'pagination' => ['page' => $page, 'last_page' => $lastPage, 'per_page' => self::PAGE_SIZE, 'total' => $total],
        ];
    }

    public function review(int $id, array $input, int $actorId): string
    {
        $status = (string) ($input['status'] ?? '');
        $reason = trim((string) ($input['rejection_reason'] ?? ''));
        $errors = [];
        if (!in_array($status, ['approved', 'rejected'], true)) $errors['status'][] = 'Choose approve or reject.';
        if ($status === 'rejected' && $reason === '') $errors['rejection_reason'][] = 'A rejection reason is required.';
        if (mb_strlen($reason) > 1000) $errors['rejection_reason'][] = 'The rejection reason may not exceed 1,000 characters.';
        if ($errors) throw new PostReviewValidationException($errors);

        $statement = $this->db->prepare('SELECT * FROM posts WHERE id=? AND is_official=0 AND deleted_at IS NULL');
        $statement->execute([$id]);
        $post = $statement->fetch();
        if (!$post) throw new PostReviewValidationException(['post' => ['Student post not found.']]);
        if ((int) $post['user_id'] === $actorId) throw new PostReviewValidationException(['post' => ['You cannot review your own post.']]);
        if ($post['status'] !== 'pending') throw new PostReviewValidationException(['post' => ['This post has already been reviewed.']]);

        $this->db->beginTransaction();
        try {
            $reviewedAt = date('Y-m-d H:i:s');
            $update = $this->db->prepare('UPDATE posts SET status=?,rejection_reason=?,reviewed_by=?,reviewed_at=?,updated_at=datetime(\'now\') WHERE id=? AND status=\'pending\'');
            $update->execute([$status, $status === 'rejected' ? $reason : null, $actorId, $reviewedAt, $id]);
            if ($update->rowCount() !== 1) throw new PostReviewValidationException(['post' => ['This post has already been reviewed.']]);

            $audit = $this->db->prepare("INSERT INTO post_audits(post_id,actor_id,action,from_status,to_status,notes,created_at,updated_at) VALUES(?,?,?,'pending',?,?,datetime('now'),datetime('now'))");
            $audit->execute([$id, $actorId, $status, $status, $status === 'rejected' ? $reason : null]);
            $activity = $this->db->prepare("INSERT INTO activity_logs(actor_id,event_id,action,acting_role,description,created_at,updated_at) VALUES(?,?,?,'SBO Adviser',?,datetime('now'),datetime('now'))");
            $activity->execute([$actorId, $post['event_id'], 'post_'.$status, "Post #$id was $status."]);
            $notification = $this->db->prepare("INSERT INTO notifications(id,type,notifiable_type,notifiable_id,data,created_at,updated_at) VALUES(?,?,?,?,?,datetime('now'),datetime('now'))");
            $notification->execute([
                $this->uuid(), 'App\\Notifications\\PostReviewed', 'App\\Models\\User', (int) $post['user_id'],
                json_encode(['post_id' => $id, 'status' => $status, 'reason' => $status === 'rejected' ? $reason : null, 'message' => 'Your post was '.$status.'.'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
            $this->db->commit();
            return 'Post '.$status.'.';
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    private function normalize(array $post): array
    {
        foreach (['id', 'user_id', 'event_id', 'reviewed_by'] as $field) $post[$field] = $post[$field] === null ? null : (int) $post[$field];
        $post['is_official'] = (bool) $post['is_official'];
        $post['author_name'] = $this->name($post, 'author_') ?: 'Deleted user';
        $post['author_initials'] = $this->initials($post['author_first_name'] ?? '', $post['author_last_name'] ?? '', $post['author_username'] ?? '');
        $post['reviewer_name'] = $this->name($post, 'reviewer_') ?: null;
        foreach (['author_first_name','author_middle_name','author_last_name','author_username','reviewer_first_name','reviewer_middle_name','reviewer_last_name','reviewer_username'] as $field) unset($post[$field]);
        return $post;
    }

    private function name(array $row, string $prefix): string
    {
        return trim(implode(' ', array_filter([$row[$prefix.'first_name'] ?? null, $row[$prefix.'middle_name'] ?? null, $row[$prefix.'last_name'] ?? null]))) ?: (string) ($row[$prefix.'username'] ?? '');
    }

    private function initials(string $first, string $last, string $username): string
    {
        $letters = mb_substr(trim($first), 0, 1).mb_substr(trim($last), 0, 1);
        return mb_strtoupper($letters !== '' ? $letters : mb_substr($username, 0, 2));
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function scalar(string $sql, array $params = []): mixed
    {
        $statement = $this->db->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

$actor = AuthGuard::requireRole('SBO Adviser');
$repository = new PostReviewRepository((new Database())->connection());
try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') JsonResponse::send(['success' => true, 'data' => $repository->index($_GET)]);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) JsonResponse::send(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 403);
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = $_POST;
    if (($input['action'] ?? 'review') !== 'review') throw new PostReviewValidationException(['action' => ['Unknown post-review action.']]);
    $message = $repository->review((int) ($input['id'] ?? 0), $input, (int) $actor['id']);
    JsonResponse::send(['success' => true, 'message' => $message]);
} catch (PostReviewValidationException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage(), 'errors' => $exception->errors()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'Post review request failed.'], 500);
}
