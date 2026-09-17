<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';

final class StudentHomeRepository
{
    private const IMAGE_DIRECTORY = 'assets/uploads/posts';
    private const VIDEO_DIRECTORY = 'assets/uploads/post-videos';

    public function __construct(private readonly PDO $db)
    {
    }

    public function pageData(int $userId): array
    {
        return [
            'student' => $this->student($userId),
            'current_event' => $this->currentEvent($userId),
            'featured_events' => $this->featuredEvents(),
            'posts' => $this->approvedPosts($userId),
            'submissions' => $this->submissions($userId),
            'notifications' => $this->notifications($userId),
            'unread_notifications' => $this->unreadNotifications($userId),
        ];
    }

    public function markNotificationsRead(int $userId, ?string $notificationId = null): int
    {
        $sql = "UPDATE tbl_notifications
                SET read_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                WHERE notifiable_id = ? AND read_at IS NULL";
        $parameters = [$userId];

        if ($notificationId !== null && $notificationId !== '') {
            $sql .= ' AND id = ?';
            $parameters[] = $notificationId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement->rowCount();
    }

    public function createPost(int $userId, array $input, array $files): int
    {
        $content = trim((string) ($input['content'] ?? ''));
        if ($content === '') {
            throw new InvalidArgumentException('Write something before submitting your post.');
        }
        if (mb_strlen($content) > 3000) {
            throw new InvalidArgumentException('The post may not exceed 3,000 characters.');
        }

        $image = $files['image'] ?? null;
        $video = $files['video'] ?? null;
        if ($this->hasUpload($image) && $this->hasUpload($video)) {
            throw new InvalidArgumentException('Attach either one photo or one video, not both.');
        }
        $this->validateImage($image);
        $this->validateVideo($video);
        $imagePath = $this->storeImage($image);
        $videoPath = $this->storeVideo($video);

        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                "INSERT INTO tbl_posts
                    (user_id, event_id, category, content, image_path, video_path, status, is_official, created_at, updated_at)
                 VALUES (?, NULL, 'general', ?, ?, ?, 'pending', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $statement->execute([$userId, $content, $imagePath, $videoPath]);
            $postId = (int) $this->db->lastInsertId();

            $audit = $this->db->prepare(
                "INSERT INTO tbl_post_audits
                    (post_id, actor_id, action, from_status, to_status, created_at, updated_at)
                 VALUES (?, ?, 'submitted', NULL, 'pending', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $audit->execute([$postId, $userId]);

            $activity = $this->db->prepare(
                "INSERT INTO tbl_activity_logs
                    (actor_id, action, acting_role, description, created_at, updated_at)
                 VALUES (?, 'post_submitted', 'Student', ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
            );
            $activity->execute([$userId, "Post #{$postId} was submitted for adviser review."]);

            $this->notifyAdvisers($postId, $userId);
            $this->db->commit();

            return $postId;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            if ($imagePath !== null) {
                $this->deleteImage($imagePath);
            }
            if ($videoPath !== null) {
                $this->deleteVideo($videoPath);
            }
            throw $exception;
        }
    }

    public function toggleReaction(int $userId, int $postId, string $type): void
    {
        $this->approvedPost($postId);
        if (!in_array($type, ['like', 'love', 'celebrate', 'support'], true)) {
            throw new InvalidArgumentException('Choose a valid reaction.');
        }
        $current = $this->db->prepare('SELECT type FROM tbl_post_reactions WHERE post_id=? AND user_id=?');
        $current->execute([$postId, $userId]);
        if ($current->fetchColumn() === $type) {
            $this->db->prepare('DELETE FROM tbl_post_reactions WHERE post_id=? AND user_id=?')->execute([$postId, $userId]);
            return;
        }
        $this->db->prepare('INSERT INTO tbl_post_reactions(post_id,user_id,type,created_at,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE type=VALUES(type),updated_at=CURRENT_TIMESTAMP')->execute([$postId, $userId, $type]);
    }

    public function saveComment(int $userId, int $postId, string $body, ?int $commentId = null): void
    {
        $this->approvedPost($postId);
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 1000) throw new InvalidArgumentException('Comment must contain 1 to 1,000 characters.');
        if ($commentId === null) {
            $this->db->prepare('INSERT INTO tbl_post_comments(post_id,user_id,body,created_at,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([$postId, $userId, $body]);
            return;
        }
        $this->ownedComment($userId, $postId, $commentId);
        $this->db->prepare('UPDATE tbl_post_comments SET body=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND post_id=? AND user_id=?')->execute([$body, $commentId, $postId, $userId]);
    }

    public function deleteComment(int $userId, int $postId, int $commentId): void
    {
        $this->approvedPost($postId);
        $this->ownedComment($userId, $postId, $commentId);
        $this->db->prepare('DELETE FROM tbl_post_comments WHERE id=? AND post_id=? AND user_id=?')->execute([$commentId, $postId, $userId]);
    }

    public function pinComment(int $userId, int $postId, int $commentId, bool $pin): void
    {
        $post = $this->approvedPost($postId);
        if ((int) $post['user_id'] !== $userId) throw new InvalidArgumentException('Only the post author can pin a comment.');
        $this->ownedComment($userId, $postId, $commentId);
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM tbl_posts WHERE id=? FOR UPDATE');
            $lock->execute([$postId]);
            if ($pin) $this->db->prepare('UPDATE tbl_post_comments SET is_pinned=0 WHERE post_id=?')->execute([$postId]);
            $this->db->prepare('UPDATE tbl_post_comments SET is_pinned=? WHERE id=? AND post_id=? AND user_id=?')->execute([$pin ? 1 : 0, $commentId, $postId, $userId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function approvedPost(int $postId): array
    {
        $statement = $this->db->prepare("SELECT id,user_id FROM tbl_posts WHERE id=? AND status='approved' AND deleted_at IS NULL");
        $statement->execute([$postId]);
        $post = $statement->fetch();
        if (!$post) throw new InvalidArgumentException('This post is not available for comments or reactions.');
        return $post;
    }

    private function ownedComment(int $userId, int $postId, int $commentId): void
    {
        $statement = $this->db->prepare('SELECT id FROM tbl_post_comments WHERE id=? AND post_id=? AND user_id=?');
        $statement->execute([$commentId, $postId, $userId]);
        if (!$statement->fetch()) throw new InvalidArgumentException('You can change only your own comments.');
    }

    private function student(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT u.id, u.first_name, u.middle_name, u.last_name, u.email,
                    u.profile_photo_path, yl.label AS year_level
             FROM tbl_users u
             LEFT JOIN tbl_year_levels yl ON yl.id = u.year_level
             WHERE u.id = ?"
        );
        $statement->execute([$userId]);
        $student = $statement->fetch();
        if (!$student) {
            throw new RuntimeException('Student account not found.');
        }

        $student['id'] = (int) $student['id'];
        $student['full_name'] = $this->fullName($student);
        $student['initials'] = $this->initials($student);
        return $student;
    }

    private function currentEvent(int $userId): ?array
    {
        $statement = $this->db->prepare(
            "SELECT e.id, e.title, e.location, e.start_at, e.end_at, e.poster_path,
                    CASE
                        WHEN e.start_at > CURRENT_TIMESTAMP THEN 'upcoming'
                        WHEN e.end_at < CURRENT_TIMESTAMP THEN 'completed'
                        ELSE 'ongoing'
                    END AS schedule_state
             FROM tbl_events e
             WHERE e.deleted_at IS NULL
               AND e.end_at >= CURRENT_TIMESTAMP
               AND (
                    e.audience_type = 'all_students'
                    OR EXISTS (
                        SELECT 1 FROM tbl_event_user eu
                        WHERE eu.event_id = e.id AND eu.user_id = ?
                    )
                    OR EXISTS (
                        SELECT 1 FROM tbl_event_participants ep
                        WHERE ep.event_id = e.id AND ep.user_id = ?
                    )
                    OR EXISTS (
                        SELECT 1 FROM tbl_event_year_level eyl
                        JOIN tbl_users audience_user ON audience_user.year_level = eyl.year_level_id
                        WHERE eyl.event_id = e.id AND audience_user.id = ?
                    )
                    OR EXISTS (
                        SELECT 1 FROM tbl_event_team et
                        JOIN tbl_team_user tu ON tu.team_id = et.team_id
                        WHERE et.event_id = e.id AND tu.user_id = ?
                    )
               )
             ORDER BY e.start_at
             LIMIT 1"
        );
        $statement->execute([$userId, $userId, $userId, $userId]);
        $event = $statement->fetch();
        if (!$event) {
            return null;
        }

        $event['id'] = (int) $event['id'];
        return $event;
    }

    private function featuredEvents(): array
    {
        $statement = $this->db->query(
            "SELECT e.id, e.title, e.description, e.location, e.start_at, e.end_at,
                    e.poster_path, et.label AS type,
                    CASE
                        WHEN e.start_at > CURRENT_TIMESTAMP THEN 'upcoming'
                        WHEN e.end_at < CURRENT_TIMESTAMP THEN 'completed'
                        ELSE 'ongoing'
                    END AS schedule_state
             FROM tbl_events e
             LEFT JOIN tbl_event_types et ON et.id = e.event_type_id
             WHERE e.deleted_at IS NULL
               AND e.is_featured = 1
               AND e.end_at >= CURRENT_TIMESTAMP
               AND (e.featured_until IS NULL OR e.featured_until >= CURRENT_TIMESTAMP)
             ORDER BY e.featured_order IS NULL, e.featured_order, e.start_at
             LIMIT 6"
        );
        $events = $statement->fetchAll();
        foreach ($events as &$event) {
            $event['id'] = (int) $event['id'];
        }
        unset($event);
        return $events;
    }

    private function approvedPosts(int $viewerId): array
    {
        $statement = $this->db->prepare(
            "SELECT p.id, p.user_id, p.content, p.image_path, p.video_path, p.reviewed_at, p.created_at,
                    p.is_official, u.first_name, u.middle_name, u.last_name,
                    u.profile_photo_path, r.name AS author_role,
                    (SELECT pr.type FROM tbl_post_reactions pr WHERE pr.post_id=p.id AND pr.user_id=? LIMIT 1) viewer_reaction,
                    (SELECT COUNT(*) FROM tbl_post_reactions pr WHERE pr.post_id = p.id) AS reactions_count,
                    (SELECT COUNT(*) FROM tbl_post_comments pc WHERE pc.post_id = p.id) AS comments_count
             FROM tbl_posts p
             JOIN tbl_users u ON u.id = p.user_id
             LEFT JOIN tbl_roles r ON r.id = u.role_id
             WHERE p.status = 'approved' AND p.deleted_at IS NULL
             ORDER BY COALESCE(p.reviewed_at, p.created_at) DESC, p.id DESC
             LIMIT 20"
        );
        $statement->execute([$viewerId]);
        $posts = $statement->fetchAll();
        if (!$posts) return [];
        $ids = array_column($posts, 'id');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $reactionRows = $this->db->prepare("SELECT post_id,type,COUNT(*) total FROM tbl_post_reactions WHERE post_id IN ($marks) GROUP BY post_id,type");
        $reactionRows->execute($ids);
        $counts = [];
        foreach ($reactionRows->fetchAll() as $reaction) $counts[(int) $reaction['post_id']][$reaction['type']] = (int) $reaction['total'];
        $commentRows = $this->db->prepare("SELECT c.id,c.post_id,c.user_id,c.body,c.is_pinned,c.created_at,c.updated_at,u.first_name,u.middle_name,u.last_name,u.profile_photo_path,r.name author_role FROM tbl_post_comments c JOIN tbl_users u ON u.id=c.user_id LEFT JOIN tbl_roles r ON r.id=u.role_id WHERE c.post_id IN ($marks) ORDER BY c.post_id,c.is_pinned DESC,c.created_at,c.id");
        $commentRows->execute($ids);
        $comments = [];
        foreach ($commentRows->fetchAll() as $comment) {
            $comment['id'] = (int) $comment['id'];
            $comment['post_id'] = (int) $comment['post_id'];
            $comment['user_id'] = (int) $comment['user_id'];
            $comment['is_pinned'] = (bool) $comment['is_pinned'];
            $comment['author_name'] = $this->fullName($comment);
            $comment['author_initials'] = $this->initials($comment);
            foreach (['first_name','middle_name','last_name'] as $field) unset($comment[$field]);
            $comments[$comment['post_id']][] = $comment;
        }
        foreach ($posts as &$post) {
            $post['id'] = (int) $post['id'];
            $post['user_id'] = (int) $post['user_id'];
            $post['is_official'] = (bool) $post['is_official'];
            $post['reactions_count'] = (int) $post['reactions_count'];
            $post['comments_count'] = (int) $post['comments_count'];
            $post['author_name'] = $this->fullName($post);
            $post['author_initials'] = $this->initials($post);
            $post['reaction_counts'] = $counts[$post['id']] ?? [];
            $post['comments'] = $comments[$post['id']] ?? [];
            foreach (['first_name','middle_name','last_name'] as $field) unset($post[$field]);
        }
        unset($post);
        return $posts;
    }

    private function submissions(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT id, content, image_path, video_path, status, rejection_reason, created_at
             FROM tbl_posts
             WHERE user_id = ? AND status IN ('pending', 'rejected') AND deleted_at IS NULL
             ORDER BY created_at DESC"
        );
        $statement->execute([$userId]);
        $posts = $statement->fetchAll();
        foreach ($posts as &$post) {
            $post['id'] = (int) $post['id'];
        }
        unset($post);
        return $posts;
    }

    private function unreadNotifications(int $userId): int
    {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM tbl_notifications WHERE notifiable_id = ? AND read_at IS NULL'
        );
        $statement->execute([$userId]);
        return (int) $statement->fetchColumn();
    }

    private function notifications(int $userId): array
    {
        $statement = $this->db->prepare(
            "SELECT id, type, data, read_at, created_at
             FROM tbl_notifications
             WHERE notifiable_id = ?
             ORDER BY created_at DESC
             LIMIT 20"
        );
        $statement->execute([$userId]);
        $notifications = $statement->fetchAll();

        foreach ($notifications as &$notification) {
            $data = json_decode((string) $notification['data'], true);
            $notification['message'] = is_array($data) && isset($data['message'])
                ? (string) $data['message']
                : 'You have a new update.';
            $notification['is_read'] = $notification['read_at'] !== null;
            unset($notification['data']);
        }
        unset($notification);

        return $notifications;
    }

    private function notifyAdvisers(int $postId, int $studentId): void
    {
        $advisers = $this->db->query(
            "SELECT u.id
             FROM tbl_users u
             JOIN tbl_roles r ON r.id = u.role_id
             JOIN tbl_user_statuses s ON s.id = u.status
             WHERE r.name = 'SBO Adviser' AND s.label = 'active'"
        )->fetchAll(PDO::FETCH_COLUMN);
        if (!$advisers) {
            return;
        }

        $student = $this->student($studentId);
        $statement = $this->db->prepare(
            "INSERT INTO tbl_notifications
                (id, type, notifiable_type, notifiable_id, data, created_at, updated_at)
             VALUES (?, 'post_submitted', 'App\\Models\\User', ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        $data = json_encode([
            'post_id' => $postId,
            'student_id' => $studentId,
            'message' => $student['full_name'].' submitted a post for review.',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        foreach ($advisers as $adviserId) {
            $statement->execute([$this->uuid(), (int) $adviserId, $data]);
        }
    }

    private function validateImage(mixed $file): void
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The image could not be uploaded.');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Choose an image no larger than 5 MB.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new InvalidArgumentException('Use a JPG, PNG, or WebP image.');
        }
        $dimensions = @getimagesize((string) $file['tmp_name']);
        if (!$dimensions) {
            throw new InvalidArgumentException('Upload a valid image.');
        }
        if ($dimensions[0] > 4096 || $dimensions[1] > 4096) {
            throw new InvalidArgumentException('The image dimensions may not exceed 4096 × 4096 pixels.');
        }
    }

    private function validateVideo(mixed $file): void
    {
        if (!$this->hasUpload($file)) {
            return;
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('The video could not be uploaded.');
        }
        if (($file['size'] ?? 0) > 25 * 1024 * 1024) {
            throw new InvalidArgumentException('Choose a video no larger than 25 MB.');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        if (!in_array($mime, ['video/mp4', 'video/webm', 'video/quicktime'], true)) {
            throw new InvalidArgumentException('Use an MP4, WebM, or MOV video.');
        }
    }

    private function storeImage(mixed $file): ?string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extension = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ][$mime] ?? null;
        if ($extension === null) {
            return null;
        }

        $directory = dirname(__DIR__).DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, self::IMAGE_DIRECTORY);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('The post image directory could not be created.');
        }

        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $destination = $directory.DIRECTORY_SEPARATOR.$filename;
        if (!move_uploaded_file((string) $file['tmp_name'], $destination)) {
            throw new RuntimeException('The post image could not be stored.');
        }
        return self::IMAGE_DIRECTORY.'/'.$filename;
    }

    private function deleteImage(string $path): void
    {
        if (!str_starts_with($path, self::IMAGE_DIRECTORY.'/')) {
            return;
        }
        $file = dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($file)) {
            unlink($file);
        }
    }

    private function storeVideo(mixed $file): ?string
    {
        if (!$this->hasUpload($file)) {
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extension = [
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
        ][$mime] ?? null;
        if ($extension === null) {
            return null;
        }

        $directory = dirname(__DIR__).DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, self::VIDEO_DIRECTORY);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('The post video directory could not be created.');
        }

        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        if (!move_uploaded_file((string) $file['tmp_name'], $directory.DIRECTORY_SEPARATOR.$filename)) {
            throw new RuntimeException('The post video could not be stored.');
        }
        return self::VIDEO_DIRECTORY.'/'.$filename;
    }

    private function deleteVideo(string $path): void
    {
        if (!str_starts_with($path, self::VIDEO_DIRECTORY.'/')) {
            return;
        }
        $file = dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (is_file($file)) {
            unlink($file);
        }
    }

    private function hasUpload(mixed $file): bool
    {
        return is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
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
        $first = mb_substr((string) ($person['first_name'] ?? ''), 0, 1);
        $last = mb_substr((string) ($person['last_name'] ?? ''), 0, 1);
        return mb_strtoupper($first.$last);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4)
            .'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

$actor = AuthGuard::requireRole('Student');
$repository = new StudentHomeRepository((new Database())->connection());

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        JsonResponse::send([
            'success' => true,
            'data' => $repository->pageData((int) $actor['id']),
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        JsonResponse::send(['success' => false, 'message' => 'Your session expired.'], 403);
    }

    $input = $_POST;
    if (!$input) {
        $decoded = json_decode(file_get_contents('php://input'), true);
        $input = is_array($decoded) ? $decoded : [];
    }

    if (($input['action'] ?? '') === 'mark_notifications_read') {
        $updated = $repository->markNotificationsRead(
            (int) $actor['id'],
            isset($input['notification_id']) ? (string) $input['notification_id'] : null
        );
        JsonResponse::send([
            'success' => true,
            'updated' => $updated,
            'message' => 'Notifications marked as read.',
        ]);
    }

    $action = (string) ($input['action'] ?? '');
    $userId = (int) $actor['id'];
    $postId = (int) ($input['post_id'] ?? 0);
    if ($action === 'reaction_toggle') {
        $repository->toggleReaction($userId, $postId, (string) ($input['type'] ?? ''));
        JsonResponse::send(['success' => true, 'message' => 'Reaction updated.']);
    }
    if ($action === 'comment_create' || $action === 'comment_update') {
        $repository->saveComment($userId, $postId, (string) ($input['body'] ?? ''), $action === 'comment_update' ? (int) ($input['comment_id'] ?? 0) : null);
        JsonResponse::send(['success' => true, 'message' => $action === 'comment_create' ? 'Comment added.' : 'Comment updated.']);
    }
    if ($action === 'comment_delete') {
        $repository->deleteComment($userId, $postId, (int) ($input['comment_id'] ?? 0));
        JsonResponse::send(['success' => true, 'message' => 'Comment deleted.']);
    }
    if ($action === 'comment_pin') {
        $repository->pinComment($userId, $postId, (int) ($input['comment_id'] ?? 0), !empty($input['pin']));
        JsonResponse::send(['success' => true, 'message' => !empty($input['pin']) ? 'Comment pinned.' : 'Comment unpinned.']);
    }
    if ($action !== '' && $action !== 'create') throw new InvalidArgumentException('Unknown feed action.');

    $postId = $repository->createPost((int) $actor['id'], $input, $_FILES);
    JsonResponse::send([
        'success' => true,
        'id' => $postId,
        'message' => 'Post submitted. Waiting for adviser approval.',
    ], 201);
} catch (InvalidArgumentException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'The student home request failed.'], 500);
}
