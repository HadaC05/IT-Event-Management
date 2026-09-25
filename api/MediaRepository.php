<?php

declare(strict_types=1);

require_once __DIR__.'/UserBadge.php';
require_once __DIR__.'/MediaVideoDuration.php';

final class MediaForbiddenException extends DomainException {}

final class MediaPermissions
{
    private const ALLOWED_ROLES = ['Admin', 'SBO', 'SBO Adviser', 'SBO Officer', 'Faculty', 'Student'];

    public function __construct(private readonly string $role) {}

    public static function roles(): array { return self::ALLOWED_ROLES; }
    public function role(): string { return $this->role; }
    public function isStudent(): bool { return $this->role === 'Student'; }
    public function isOfficer(): bool { return $this->role === 'SBO Officer'; }
    public function isAdmin(): bool { return in_array($this->role, ['Admin', 'SBO'], true); }
    public function canModerate(): bool { return $this->isAdmin() || $this->role === 'SBO Adviser'; }
    public function canManageCarousel(): bool { return $this->canModerate() || $this->isOfficer(); }
    public function automaticStatus(): string { return $this->isStudent() ? 'pending' : 'approved'; }

    public function values(): array
    {
        return [
            'create' => true,
            'moderate' => $this->canModerate(),
            'hide' => $this->canModerate(),
            'manage_carousel' => $this->canManageCarousel(),
            'automatic_approval' => !$this->isStudent(),
        ];
    }
}

final class MediaRepository
{
    private const IMAGE_DIRECTORY = 'assets/uploads/posts';
    private const VIDEO_DIRECTORY = 'assets/uploads/post-videos';
    private const CAROUSEL_DIRECTORY = 'assets/uploads/event-posters';

    public function __construct(private readonly PDO $db) {}

    public function pageData(array $actor, ?int $eventId = null): array
    {
        $userId = (int) $actor['id'];
        $permissions = new MediaPermissions((string) $actor['role']);
        $activeEvents = $this->activeEvents(4);
        $feed = $this->postsPage($actor, $eventId);
        return [
            'viewer' => [
                'id' => $userId,
                'role' => $permissions->role(),
                'full_name' => trim((string) ($actor['full_name'] ?? ($actor['first_name'] ?? '').' '.($actor['last_name'] ?? ''))),
                'initials' => $this->initials($actor),
                'profile_photo_path' => $actor['profile_photo_path'] ?? null,
                'special_tag' => UserBadge::forIdNumber($actor['id_number'] ?? null),
            ],
            'permissions' => $permissions->values(),
            'active_events' => $activeEvents,
            'post_events' => $permissions->isOfficer() ? $this->officerEvents($userId) : $this->activeEvents(null),
            'posts' => $feed['posts'],
            'next_cursor' => $feed['next_cursor'],
            'own_posts' => $this->ownPosts($userId),
            'event_program' => $this->eventProgram(),
            'carousel_events' => $permissions->canManageCarousel()
                ? ($permissions->isOfficer() ? $this->officerEvents($userId) : $this->activeEvents(null))
                : [],
            'featured_events' => $this->carouselEvents(),
        ];
    }

    public function notificationData(int $userId): array
    {
        $statement = $this->db->prepare('SELECT id,type,data,read_at,created_at FROM tbl_notifications WHERE notifiable_id=? ORDER BY created_at DESC LIMIT 20');
        $statement->execute([$userId]);
        $notifications = $statement->fetchAll();
        $postIds = [];
        foreach ($notifications as &$notification) {
            $data = json_decode((string) $notification['data'], true);
            $message = is_array($data) && isset($data['message']) ? (string) $data['message'] : 'You have a new update.';
            $notification['message'] = $message;
            $notification['title'] = rtrim($message, '. ');
            $notification['is_read'] = $notification['read_at'] !== null;
            $postId = is_array($data) ? filter_var($data['post_id'] ?? null, FILTER_VALIDATE_INT) : false;
            $notification['post_id'] = $postId !== false && $postId > 0 ? $postId : null;
            if ($notification['post_id']) $postIds[] = $notification['post_id'];
            unset($notification['data']);
        }
        unset($notification);
        $previews = [];
        if ($postIds) {
            $ids = array_values(array_unique($postIds));
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $posts = $this->db->prepare("SELECT id,content,image_path,video_path FROM tbl_posts WHERE id IN ($marks)");
            $posts->execute($ids);
            foreach ($posts->fetchAll() as $post) {
                $content = trim(preg_replace('/\s+/u', ' ', (string) $post['content']) ?? '');
                if ($content === '') $content = $post['image_path'] ? 'Photo post' : ($post['video_path'] ? 'Video post' : '');
                $previews[(int) $post['id']] = mb_strlen($content) > 110 ? mb_substr($content, 0, 107).'…' : $content;
            }
        }
        foreach ($notifications as &$notification) {
            $notification['detail'] = $notification['post_id'] ? ($previews[$notification['post_id']] ?? '') : '';
            unset($notification['post_id']);
        }
        unset($notification);
        $count = $this->db->prepare('SELECT COUNT(*) FROM tbl_notifications WHERE notifiable_id=? AND read_at IS NULL');
        $count->execute([$userId]);
        return ['notifications'=>$notifications,'unread_notifications'=>(int) $count->fetchColumn()];
    }

    public function markNotificationsRead(int $userId, ?string $notificationId = null): int
    {
        $sql = 'UPDATE tbl_notifications SET read_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE notifiable_id=? AND read_at IS NULL';
        $parameters = [$userId];
        if ($notificationId !== null && $notificationId !== '') { $sql .= ' AND id=?'; $parameters[] = $notificationId; }
        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        return $statement->rowCount();
    }

    public function searchAuthors(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2 || mb_strlen($query) > 80) throw new InvalidArgumentException('Search with 2 to 80 characters.');
        $statement = $this->db->prepare("SELECT u.id,u.first_name,u.middle_name,u.last_name,u.profile_photo_path,r.name role_name,(SELECT COUNT(*) FROM tbl_posts p WHERE p.user_id=u.id AND p.status='approved' AND p.deleted_at IS NULL) post_count,(SELECT t.color FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1 WHERE tu.user_id=u.id ORDER BY t.school_year_id DESC,tu.id DESC LIMIT 1) team_color,(SELECT t.name FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1 WHERE tu.user_id=u.id ORDER BY t.school_year_id DESC,tu.id DESC LIMIT 1) team_name FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id JOIN tbl_user_statuses s ON s.id=u.status AND s.label='active' WHERE (CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) LIKE ? OR u.username LIKE ? OR u.id_number LIKE ?) ORDER BY CASE WHEN CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name)=? THEN 0 WHEN CONCAT_WS(' ',u.first_name,u.middle_name,u.last_name) LIKE ? THEN 1 ELSE 2 END,u.last_name,u.first_name,u.id LIMIT 20");
        $statement->execute(['%'.$query.'%','%'.$query.'%','%'.$query.'%',$query,$query.'%']);
        $users = $statement->fetchAll();
        foreach ($users as &$user) {
            $user['id'] = (int) $user['id'];
            $user['post_count'] = (int) $user['post_count'];
            $user['full_name'] = $this->name($user);
            $user['initials'] = $this->initials($user);
            unset($user['first_name'], $user['middle_name'], $user['last_name']);
        }
        unset($user);
        return $users;
    }

    public function publicAuthorProfile(array $actor, int $authorId, ?string $cursor = null): array
    {
        if ($authorId < 1) throw new InvalidArgumentException('Choose a valid account.');
        $statement = $this->db->prepare("SELECT u.id,u.first_name,u.middle_name,u.last_name,u.bio,u.profile_photo_path,r.name role_name,yl.label year_level_label,(SELECT COUNT(*) FROM tbl_posts p WHERE p.user_id=u.id AND p.status='approved' AND p.deleted_at IS NULL) post_count,(SELECT t.color FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1 WHERE tu.user_id=u.id ORDER BY t.school_year_id DESC,tu.id DESC LIMIT 1) team_color,(SELECT t.name FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1 WHERE tu.user_id=u.id ORDER BY t.school_year_id DESC,tu.id DESC LIMIT 1) team_name FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id JOIN tbl_user_statuses s ON s.id=u.status AND s.label='active' LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level WHERE u.id=? LIMIT 1");
        $statement->execute([$authorId]);
        $profile = $statement->fetch();
        if (!$profile) throw new InvalidArgumentException('This account is unavailable.');
        $profile['id'] = (int) $profile['id'];
        $profile['post_count'] = (int) $profile['post_count'];
        $profile['full_name'] = $this->name($profile);
        $profile['initials'] = $this->initials($profile);
        unset($profile['first_name'], $profile['middle_name'], $profile['last_name']);
        $page = $this->postsPage($actor, null, $cursor, false, $authorId);
        return ['profile'=>$profile,'posts'=>$page['posts'],'next_cursor'=>$page['next_cursor']];
    }

    public function moderationPage(array $actor, int $requestedPage = 1): array
    {
        $permissions = new MediaPermissions((string) $actor['role']);
        if (!$permissions->canModerate()) throw new MediaForbiddenException('Only an Admin or SBO Adviser may review posts.');
        if ($requestedPage < 1) throw new InvalidArgumentException('Choose a valid review page.');

        $perPage = 20;
        $counts = $this->moderationCounts();
        $total = $counts['pending'];
        $pageCount = max(1, (int) ceil($total / $perPage));
        $page = min($requestedPage, $pageCount);
        return [
            'posts' => $this->moderationQueue($perPage, ($page - 1) * $perPage),
            'counts' => $counts,
            'total' => $total,
            'page' => $page,
            'page_count' => $pageCount,
            'per_page' => $perPage,
        ];
    }

    public function studentProfileData(array $actor): array
    {
        $userId = (int) $actor['id'];
        $permissions = new MediaPermissions((string) $actor['role']);
        if (!$permissions->isStudent()) throw new MediaForbiddenException('Only students can open this profile.');
        $profileStatement = $this->db->prepare("SELECT
                u.id,u.id_number,u.first_name,u.middle_name,u.last_name,u.bio,u.profile_photo_path,u.created_at,
                yl.label year_level_label,
                (SELECT t.name FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1 WHERE tu.user_id=u.id ORDER BY t.school_year_id DESC,tu.id DESC LIMIT 1) team_name,
                (SELECT t.color FROM tbl_team_user tu JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1 WHERE tu.user_id=u.id ORDER BY t.school_year_id DESC,tu.id DESC LIMIT 1) team_color
            FROM tbl_users u
            LEFT JOIN tbl_year_levels yl ON yl.id=u.year_level
            WHERE u.id=? LIMIT 1");
        $profileStatement->execute([$userId]);
        $profile = $profileStatement->fetch();
        if (!$profile) throw new InvalidArgumentException('Your student profile was not found.');
        $profile['id'] = (int) $profile['id'];
        $profile['full_name'] = $this->name($profile);
        $profile['initials'] = $this->initials($profile);
        $profile['special_tag'] = UserBadge::forIdNumber($profile['id_number']);

        $counts = ['approved'=>0,'pending'=>0,'rejected'=>0,'hidden'=>0];
        $countStatement = $this->db->prepare("SELECT status,COUNT(*) total FROM tbl_posts WHERE user_id=? AND deleted_at IS NULL GROUP BY status");
        $countStatement->execute([$userId]);
        foreach ($countStatement->fetchAll() as $row) $counts[(string) $row['status']] = (int) $row['total'];

        $feed = $this->postsPage($actor, null, null, true);
        return [
            'viewer' => [
                'id' => $userId,
                'role' => $permissions->role(),
                'full_name' => $profile['full_name'],
                'initials' => $profile['initials'],
                'profile_photo_path' => $profile['profile_photo_path'],
                'special_tag' => $profile['special_tag'],
            ],
            'profile' => $profile,
            'post_counts' => $counts,
            'permissions' => $permissions->values(),
            'post_events' => $this->activeEvents(null),
            'posts' => $feed['posts'],
            'next_cursor' => $feed['next_cursor'],
            'own_posts' => $this->ownPosts($userId),
        ];
    }

    public function create(array $actor, array $input, array $files): int
    {
        $permissions = new MediaPermissions((string) $actor['role']);
        $userId = (int) $actor['id'];
        $content = $this->content($input, $this->hasUpload($files['images'] ?? ($files['image'] ?? null)) || $this->hasUpload($files['video'] ?? null));
        $eventId = $this->eventId($input, $userId, $permissions);
        [$imagePaths, $videoPath] = $this->storeMedia($files);
        $imagePath = $imagePaths[0] ?? null;
        $status = $permissions->automaticStatus();
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare("INSERT INTO tbl_posts(user_id,event_id,category,content,image_path,video_path,status,is_official,reviewed_by,reviewed_at,created_at,updated_at) VALUES(?,?,'general',?,?,?,?,0,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
            $reviewer = $status === 'approved' ? $userId : null;
            $reviewedAt = $status === 'approved' ? date('Y-m-d H:i:s') : null;
            $statement->execute([$userId, $eventId, $content, $imagePath, $videoPath, $status, $reviewer, $reviewedAt]);
            $postId = (int) $this->db->lastInsertId();
            $this->replacePostImages($postId, $imagePaths);
            $this->audit($postId, $userId, 'submitted', null, $status);
            if ($permissions->isStudent()) $this->notifyModerators($postId, $actor);
            $this->db->commit();
            return $postId;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            foreach ($imagePaths as $path) $this->removeUpload($path);
            $this->removeUpload($videoPath);
            throw $exception;
        }
    }

    public function update(array $actor, array $input, array $files): void
    {
        $userId = (int) $actor['id'];
        $permissions = new MediaPermissions((string) $actor['role']);
        $postId = (int) ($input['id'] ?? 0);
        $post = $this->ownedPost($postId, $userId);
        if ($permissions->isStudent() && !in_array($post['status'], ['pending', 'approved', 'rejected'], true)) {
            throw new MediaForbiddenException('This post can no longer be edited.');
        }
        if (!$permissions->isStudent() && $post['status'] === 'hidden') {
            throw new MediaForbiddenException('A hidden post cannot be edited by its author.');
        }
        $content = $this->content($input, true);
        $eventId = $this->eventId($input, $userId, $permissions);
        [$newImages, $newVideo] = $this->storeMedia($files);
        $removeMedia = !empty($input['remove_media']);
        $removedImagePaths = [];
        if (isset($input['remove_image_paths'])) {
            $removedImagePaths = json_decode((string) $input['remove_image_paths'], true);
            if (!is_array($removedImagePaths) || array_values($removedImagePaths) !== $removedImagePaths || count($removedImagePaths) > 30 || count(array_filter($removedImagePaths, 'is_string')) !== count($removedImagePaths)) {
                foreach ($newImages as $path) $this->removeUpload($path);
                $this->removeUpload($newVideo);
                throw new InvalidArgumentException('Choose valid photos to remove.');
            }
        }
        $status = $permissions->isStudent() ? 'pending' : 'approved';
        $imagePath = $videoPath = null;
        $oldImagePaths = [];
        $oldVideoPath = null;
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT * FROM tbl_posts WHERE id=? AND user_id=? AND deleted_at IS NULL FOR UPDATE');
            $lock->execute([$postId, $userId]);
            $post = $lock->fetch();
            if (!$post) throw new MediaForbiddenException('You can change only your own posts.');
            if ($permissions->isStudent() && !in_array($post['status'], ['pending', 'approved', 'rejected'], true)) {
                throw new MediaForbiddenException('This post can no longer be edited.');
            }
            if (!$permissions->isStudent() && $post['status'] === 'hidden') {
                throw new MediaForbiddenException('A hidden post cannot be edited by its author.');
            }
            $eventId = $this->eventId($input, $userId, $permissions);
            $oldImagePaths = $this->postImagePaths($postId, $post['image_path']);
            $oldVideoPath = $post['video_path'];
            if (count($removedImagePaths) !== count(array_unique($removedImagePaths)) || array_diff($removedImagePaths, $oldImagePaths)) {
                throw new InvalidArgumentException('A selected photo is no longer part of this post. Refresh and try again.');
            }
            $imagePaths = $removeMedia ? [] : $oldImagePaths;
            $videoPath = $removeMedia ? null : $oldVideoPath;
            if ($removedImagePaths) $imagePaths = array_values(array_diff($imagePaths, $removedImagePaths));
            if ($newImages) { $imagePaths = array_merge($imagePaths, $newImages); $videoPath = null; }
            if ($newVideo !== null) { $videoPath = $newVideo; $imagePaths = []; }
            if (count($imagePaths) > 30) throw new InvalidArgumentException('Choose up to 30 photos per post.');
            if ($content === '' && !$imagePaths && $videoPath === null) throw new InvalidArgumentException('Write a post or attach a photo or video.');
            $imagePath = $imagePaths[0] ?? null;
            // An edit to an already-published non-student post must not move it to the top of the feed.
            $reviewedBy = $status === 'approved' ? ($post['status'] === 'approved' ? $post['reviewed_by'] : $userId) : null;
            $reviewedAt = $status === 'approved' ? ($post['status'] === 'approved' ? $post['reviewed_at'] : date('Y-m-d H:i:s')) : null;
            $statement = $this->db->prepare('UPDATE tbl_posts SET event_id=?,content=?,image_path=?,video_path=?,status=?,rejection_reason=NULL,reviewed_by=?,reviewed_at=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=? AND deleted_at IS NULL');
            $statement->execute([$eventId, $content, $imagePath, $videoPath, $status, $reviewedBy, $reviewedAt, $postId, $userId]);
            if ($statement->rowCount() !== 1) throw new RuntimeException('The post was not updated.');
            if ($status !== 'approved') $this->db->prepare('UPDATE tbl_posts SET is_pinned=0,pinned_at=NULL WHERE id=?')->execute([$postId]);
            if ($removeMedia || $removedImagePaths || $newImages || $newVideo !== null) $this->replacePostImages($postId, $imagePaths);
            $this->audit($postId, $userId, 'edited', (string) $post['status'], $status, $permissions->isStudent() ? 'Student edit requires a new review.' : null);
            if ($permissions->isStudent()) $this->notifyModerators($postId, $actor);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            foreach ($newImages as $path) $this->removeUpload($path);
            $this->removeUpload($newVideo);
            throw $exception;
        }
        foreach (array_diff($oldImagePaths, $imagePaths) as $path) $this->removeUpload($path);
        if ($videoPath !== $oldVideoPath) $this->removeUpload($oldVideoPath);
    }

    public function delete(array $actor, int $postId): void
    {
        $userId = (int) $actor['id'];
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT status,deleted_at FROM tbl_posts WHERE id=? AND user_id=? FOR UPDATE');
            $lock->execute([$postId, $userId]);
            $post = $lock->fetch();
            if (!$post) throw new MediaForbiddenException('You can change only your own posts.');
            if ($post['status'] === 'hidden') throw new MediaForbiddenException('A hidden post cannot be deleted by its author.');
            if ($post['deleted_at'] !== null) { $this->db->commit(); return; }
            $statement = $this->db->prepare('UPDATE tbl_posts SET deleted_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=? AND user_id=? AND deleted_at IS NULL');
            $statement->execute([$postId, $userId]);
            if ($statement->rowCount() !== 1) throw new RuntimeException('The post was not deleted.');
            $this->audit($postId, $userId, 'deleted', (string) $post['status'], null, 'Soft deleted by the author.');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function pinPost(array $actor, int $postId, bool $pin): void
    {
        if ((string) $actor['role'] !== 'SBO Adviser') throw new MediaForbiddenException('Only an SBO Adviser can pin posts.');
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('SELECT status,deleted_at,is_pinned FROM tbl_posts WHERE id=? FOR UPDATE');
            $statement->execute([$postId]);
            $post = $statement->fetch();
            if (!$post || $post['status'] !== 'approved' || $post['deleted_at'] !== null) throw new InvalidArgumentException('Only published posts can be pinned.');
            if ((bool) $post['is_pinned'] === $pin) { $this->db->commit(); return; }
            $sql = $pin
                ? 'UPDATE tbl_posts SET is_pinned=1,pinned_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=?'
                : 'UPDATE tbl_posts SET is_pinned=0,pinned_at=NULL,updated_at=CURRENT_TIMESTAMP WHERE id=?';
            $this->db->prepare($sql)->execute([$postId]);
            $this->audit($postId, (int) $actor['id'], $pin ? 'pinned' : 'unpinned', 'approved', 'approved');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function review(array $actor, int $postId, string $status, string $reason): void
    {
        $permissions = new MediaPermissions((string) $actor['role']);
        if (!$permissions->canModerate()) throw new MediaForbiddenException('Only an Admin or SBO Adviser may review posts.');
        if (!in_array($status, ['approved', 'rejected'], true)) throw new InvalidArgumentException('Choose approve or reject.');
        $reason = trim($reason);
        if ($status === 'rejected' && $reason === '') throw new InvalidArgumentException('Enter a rejection reason for the author.');
        if (mb_strlen($reason) > 1000) throw new InvalidArgumentException('The rejection reason may not exceed 1,000 characters.');
        $actorId = (int) $actor['id'];
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('SELECT p.* FROM tbl_posts p WHERE p.id=? AND p.deleted_at IS NULL FOR UPDATE');
            $statement->execute([$postId]);
            $post = $statement->fetch();
            if (!$post) throw new InvalidArgumentException('Post not found.');
            if ((int) $post['user_id'] === $actorId) throw new MediaForbiddenException('You cannot review your own post.');
            if ($post['status'] === $status && ($status !== 'rejected' || (string) ($post['rejection_reason'] ?? '') === $reason)) {
                $this->db->commit();
                return;
            }
            if ($post['status'] !== 'pending') throw new InvalidArgumentException('This post is no longer pending.');
            $update = $this->db->prepare("UPDATE tbl_posts SET status=?,rejection_reason=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='pending'");
            $update->execute([$status, $status === 'rejected' ? $reason : null, $actorId, $postId]);
            if ($update->rowCount() !== 1) throw new InvalidArgumentException('This post has already been reviewed.');
            $this->audit($postId, $actorId, $status, 'pending', $status, $status === 'rejected' ? $reason : null);
            $this->notifyPostOwner((int) $post['user_id'], $postId, $status, $status === 'rejected' ? $reason : null);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function hide(array $actor, int $postId, string $reason): void
    {
        $permissions = new MediaPermissions((string) $actor['role']);
        if (!$permissions->canModerate()) throw new MediaForbiddenException('Only an Admin or SBO Adviser may hide posts.');
        $reason = trim($reason);
        if ($reason === '') throw new InvalidArgumentException('Enter a reason for hiding this post.');
        if (mb_strlen($reason) > 1000) throw new InvalidArgumentException('The reason may not exceed 1,000 characters.');
        $actorId = (int) $actor['id'];
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('SELECT id,user_id,status,rejection_reason,deleted_at FROM tbl_posts WHERE id=? FOR UPDATE');
            $statement->execute([$postId]);
            $post = $statement->fetch();
            if (!$post || $post['deleted_at'] !== null) throw new InvalidArgumentException('Only an approved public post can be hidden.');
            if ($post['status'] === 'hidden' && (string) ($post['rejection_reason'] ?? '') === $reason) { $this->db->commit(); return; }
            if ($post['status'] !== 'approved') throw new InvalidArgumentException('Only an approved public post can be hidden.');
            $update = $this->db->prepare("UPDATE tbl_posts SET status='hidden',is_pinned=0,pinned_at=NULL,rejection_reason=?,reviewed_by=?,reviewed_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=? AND status='approved'");
            $update->execute([$reason, $actorId, $postId]);
            if ($update->rowCount() !== 1) throw new RuntimeException('The post was not hidden.');
            $this->audit($postId, $actorId, 'hidden', 'approved', 'hidden', $reason);
            $this->notifyPostOwner((int) $post['user_id'], $postId, 'hidden', $reason);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function toggleReaction(int $userId, int $postId, string $type, ?bool $desiredActive = null): void
    {
        if (!in_array($type, ['like','love','laugh','wow'], true)) throw new InvalidArgumentException('Choose Like, Love, Laugh, or Wow.');
        $this->db->beginTransaction();
        try {
            $post = $this->db->prepare("SELECT id,user_id FROM tbl_posts WHERE id=? AND status='approved' AND deleted_at IS NULL FOR UPDATE");
            $post->execute([$postId]);
            $post = $post->fetch();
            if (!$post) throw new InvalidArgumentException('This post is not publicly available.');
            $statement = $this->db->prepare('SELECT type FROM tbl_post_reactions WHERE post_id=? AND user_id=? FOR UPDATE');
            $statement->execute([$postId, $userId]);
            $current = $statement->fetchColumn() === $type;
            $active = $desiredActive ?? !$current;
            if ($active === $current) { $this->db->commit(); return; }
            if (!$active) {
                $this->db->prepare('DELETE FROM tbl_post_reactions WHERE post_id=? AND user_id=?')->execute([$postId, $userId]);
            } else {
                $this->db->prepare('INSERT INTO tbl_post_reactions(post_id,user_id,type,created_at,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE type=VALUES(type),updated_at=CURRENT_TIMESTAMP')->execute([$postId, $userId, $type]);
                if ((int) $post['user_id'] !== $userId) {
                    $name = $this->actorName($userId);
                    $verb = ['like'=>'liked','love'=>'loved','laugh'=>'laughed at','wow'=>'reacted with Wow to'][$type];
                    $this->notification((int) $post['user_id'], ['post_id'=>$postId,'actor_id'=>$userId,'action'=>'reaction','reaction'=>$type,'message'=>$name.' '.$verb.' your post.'], 'media_activity');
                }
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function toggleCommentReaction(int $userId, int $postId, int $commentId, string $type, ?bool $desiredActive = null): void
    {
        if (!in_array($type, ['like','love','laugh','wow'], true)) throw new InvalidArgumentException('Choose Like, Love, Laugh, or Wow.');
        $this->db->beginTransaction();
        try {
            $post = $this->db->prepare("SELECT id FROM tbl_posts WHERE id=? AND status='approved' AND deleted_at IS NULL FOR UPDATE");
            $post->execute([$postId]);
            if (!$post->fetchColumn()) throw new InvalidArgumentException('This post is not publicly available.');
            $commentStatement = $this->db->prepare('SELECT id,user_id FROM tbl_post_comments WHERE id=? AND post_id=? FOR UPDATE');
            $commentStatement->execute([$commentId,$postId]);
            $comment = $commentStatement->fetch();
            if (!$comment) throw new InvalidArgumentException('This comment is no longer available.');
            $statement = $this->db->prepare('SELECT type FROM tbl_comment_reactions WHERE comment_id=? AND user_id=? FOR UPDATE');
            $statement->execute([$commentId,$userId]);
            $current = $statement->fetchColumn() === $type;
            $active = $desiredActive ?? !$current;
            if ($active === $current) { $this->db->commit(); return; }
            if (!$active) {
                $this->db->prepare('DELETE FROM tbl_comment_reactions WHERE comment_id=? AND user_id=?')->execute([$commentId,$userId]);
            } else {
                $this->db->prepare('INSERT INTO tbl_comment_reactions(comment_id,user_id,type,created_at,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE type=VALUES(type),updated_at=CURRENT_TIMESTAMP')->execute([$commentId,$userId,$type]);
                if ((int) $comment['user_id'] !== $userId) {
                    $verb = ['like'=>'liked','love'=>'loved','laugh'=>'laughed at','wow'=>'reacted with Wow to'][$type];
                    $name = $this->actorName($userId);
                    $this->notification((int) $comment['user_id'], ['post_id'=>$postId,'comment_id'=>$commentId,'actor_id'=>$userId,'action'=>'comment_reaction','reaction'=>$type,'message'=>$name.' '.$verb.' your comment.'], 'media_activity');
                }
            }
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function saveComment(int $userId, int $postId, string $body, ?int $commentId, ?int $parentCommentId = null): void
    {
        $post = $this->publicPost($postId);
        $body = trim($body);
        if ($body === '' || mb_strlen($body) > 1000) throw new InvalidArgumentException('Comment must contain 1 to 1,000 characters.');
        if ($commentId === null) {
            if ($parentCommentId !== null) {
                if ($parentCommentId < 1) throw new InvalidArgumentException('Choose a valid comment to reply to.');
                $parent = $this->db->prepare('SELECT id FROM tbl_post_comments WHERE id=? AND post_id=? AND parent_comment_id IS NULL');
                $parent->execute([$parentCommentId, $postId]);
                if (!$parent->fetchColumn()) throw new InvalidArgumentException('The comment you are replying to is unavailable.');
            }
            $this->db->beginTransaction();
            try {
                $this->db->prepare('INSERT INTO tbl_post_comments(post_id,parent_comment_id,user_id,body,created_at,updated_at) VALUES(?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([$postId, $parentCommentId, $userId, $body]);
                $commentId = (int) $this->db->lastInsertId();
                $name = $this->actorName($userId);
                $recipients = [];
                if ($parentCommentId !== null) {
                    $parentOwner = $this->db->prepare('SELECT user_id FROM tbl_post_comments WHERE id=? AND post_id=?');
                    $parentOwner->execute([$parentCommentId, $postId]);
                    $parentUserId = (int) $parentOwner->fetchColumn();
                    if ($parentUserId > 0 && $parentUserId !== $userId) $recipients[$parentUserId] = $name.' replied to your comment.';
                    if ((int) $post['user_id'] !== $userId && (int) $post['user_id'] !== $parentUserId) $recipients[(int) $post['user_id']] = $name.' replied to a comment on your post.';
                } elseif ((int) $post['user_id'] !== $userId) {
                    $recipients[(int) $post['user_id']] = $name.' commented on your post.';
                }
                foreach ($recipients as $recipientId => $message) $this->notification((int) $recipientId, ['post_id'=>$postId,'comment_id'=>$commentId,'actor_id'=>$userId,'action'=>$parentCommentId !== null ? 'reply' : 'comment','message'=>$message], 'media_activity');
                $this->db->commit();
            } catch (Throwable $exception) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                throw $exception;
            }
            return;
        }
        $this->ownedComment($userId, $postId, $commentId);
        $this->db->prepare('UPDATE tbl_post_comments SET body=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND post_id=? AND user_id=?')->execute([$body, $commentId, $postId, $userId]);
    }

    public function deleteComment(int $userId, int $postId, int $commentId): void
    {
        $this->publicPost($postId);
        $this->ownedComment($userId, $postId, $commentId);
        $this->db->prepare('DELETE FROM tbl_post_comments WHERE id=? AND post_id=? AND user_id=?')->execute([$commentId, $postId, $userId]);
    }

    public function pinComment(int $userId, int $postId, int $commentId, bool $pin): void
    {
        $this->db->beginTransaction();
        try {
            $post = $this->db->prepare("SELECT id,user_id FROM tbl_posts WHERE id=? AND status='approved' AND deleted_at IS NULL FOR UPDATE");
            $post->execute([$postId]);
            $post = $post->fetch();
            if (!$post) throw new InvalidArgumentException('This post is not publicly available.');
            if ((int) $post['user_id'] !== $userId) throw new MediaForbiddenException('Only the post author can pin a comment.');
            $statement = $this->db->prepare('SELECT id,is_pinned FROM tbl_post_comments WHERE id=? AND post_id=? AND parent_comment_id IS NULL FOR UPDATE');
            $statement->execute([$commentId, $postId]);
            $comment = $statement->fetch();
            if (!$comment) throw new InvalidArgumentException('Comment not found.');
            if ((bool) $comment['is_pinned'] === $pin) { $this->db->commit(); return; }
            if ($pin) $this->db->prepare('UPDATE tbl_post_comments SET is_pinned=0 WHERE post_id=? AND parent_comment_id IS NULL')->execute([$postId]);
            $update = $this->db->prepare('UPDATE tbl_post_comments SET is_pinned=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND post_id=?');
            $update->execute([$pin ? 1 : 0, $commentId, $postId]);
            if ($update->rowCount() !== 1) throw new RuntimeException('The comment pin was not updated.');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $exception;
        }
    }

    public function updateCarousel(array $actor, array $input, array $files): void
    {
        $permissions = new MediaPermissions((string) $actor['role']);
        if (!$permissions->canManageCarousel()) throw new MediaForbiddenException('You cannot manage event carousel content.');
        $eventId = (int) ($input['event_id'] ?? 0);
        if ($permissions->isOfficer()) $this->assertOfficerEvent((int) $actor['id'], $eventId);
        $statement = $this->db->prepare('SELECT id,poster_path,end_at FROM tbl_events WHERE id=? AND deleted_at IS NULL');
        $statement->execute([$eventId]);
        $event = $statement->fetch();
        if (!$event) throw new InvalidArgumentException('Choose a valid event.');
        $featured = filter_var($input['is_featured'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($featured && strtotime((string) $event['end_at']) < time()) throw new InvalidArgumentException('Completed events cannot be added to the active carousel.');
        $file = $files['carousel_image'] ?? null;
        $newPath = $this->storeImage($file, self::CAROUSEL_DIRECTORY, 8 * 1024 * 1024);
        $oldPath = null;
        try {
            $this->db->beginTransaction();
            if ($permissions->isOfficer()) $this->assertOfficerEvent((int) $actor['id'], $eventId);
            $lock = $this->db->prepare('SELECT poster_path,end_at FROM tbl_events WHERE id=? AND deleted_at IS NULL FOR UPDATE');
            $lock->execute([$eventId]);
            $event = $lock->fetch();
            if (!$event) throw new InvalidArgumentException('Choose a valid event.');
            if ($featured && strtotime((string) $event['end_at']) < time()) throw new InvalidArgumentException('Completed events cannot be added to the active carousel.');
            $oldPath = $event['poster_path'];
            $posterPath = $newPath ?? $oldPath;
            if ($featured && !$posterPath) throw new InvalidArgumentException('Add an event carousel image before featuring this event.');
            $this->db->prepare('UPDATE tbl_events SET poster_path=?,is_featured=?,featured_until=CASE WHEN ?=1 THEN end_at ELSE NULL END,updated_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$posterPath, $featured ? 1 : 0, $featured ? 1 : 0, $eventId]);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->removeUpload($newPath);
            throw $exception;
        }
        if ($newPath && $oldPath && $newPath !== $oldPath) $this->removeUpload($oldPath);
    }

    public function postsPage(array $actor, ?int $eventId = null, ?string $cursor = null, bool $mine = false, ?int $authorId = null): array
    {
        if ($mine && (string) $actor['role'] !== 'Student') throw new MediaForbiddenException('Only students can open their profile posts.');
        $where = "p.status='approved' AND p.deleted_at IS NULL";
        $params = [(int) $actor['id']];
        if ($eventId !== null && $eventId > 0) { $where .= ' AND p.event_id=?'; $params[] = $eventId; }
        if ($mine) { $where .= ' AND p.user_id=?'; $params[] = (int) $actor['id']; }
        if ($authorId !== null) { $where .= ' AND p.user_id=?'; $params[] = $authorId; }
        $sortTime = 'CASE WHEN p.is_pinned=1 THEN COALESCE(p.pinned_at,p.reviewed_at,p.created_at) ELSE COALESCE(p.reviewed_at,p.created_at) END';
        if ($cursor !== null && $cursor !== '') {
            $position = $this->decodeCursor($cursor, ['pin','at','id']);
            if (!in_array($position['pin'], [0, 1], true) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $position['at']) || !filter_var($position['id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]])) {
                throw new InvalidArgumentException('Choose a valid feed position.');
            }
            $where .= " AND (p.is_pinned < ? OR (p.is_pinned=? AND ($sortTime < ? OR ($sortTime = ? AND p.id < ?))))";
            array_push($params, $position['pin'], $position['pin'], $position['at'], $position['at'], (int) $position['id']);
        }
        $statement = $this->db->prepare("SELECT p.id,p.user_id,p.event_id,p.category,p.content,p.image_path,p.video_path,'approved' status,p.created_at,p.updated_at,p.reviewed_at,p.is_official,p.is_pinned,p.pinned_at,$sortTime sort_at,e.title event_title,e.end_at,u.id_number,u.first_name,u.middle_name,u.last_name,u.profile_photo_path,r.name author_role,(SELECT pr.type FROM tbl_post_reactions pr WHERE pr.post_id=p.id AND pr.user_id=? LIMIT 1) viewer_reaction,(SELECT COUNT(*) FROM tbl_post_reactions pr WHERE pr.post_id=p.id) reactions_count,(SELECT COUNT(*) FROM tbl_post_comments pc WHERE pc.post_id=p.id) comments_count FROM tbl_posts p JOIN tbl_users u ON u.id=p.user_id JOIN tbl_roles r ON r.id=u.role_id LEFT JOIN tbl_events e ON e.id=p.event_id WHERE $where ORDER BY p.is_pinned DESC,sort_at DESC,p.id DESC LIMIT 21");
        $statement->execute($params);
        $rows = $statement->fetchAll();
        $hasMore = count($rows) > 20;
        if ($hasMore) array_pop($rows);
        $last = $rows ? $rows[count($rows)-1] : null;
        $nextCursor = $hasMore && $last ? $this->encodeCursor(['pin'=>(int)$last['is_pinned'],'at'=>$last['sort_at'],'id'=>(int)$last['id']]) : null;
        return ['posts'=>$this->hydratePosts($rows),'next_cursor'=>$nextCursor];
    }

    public function approvedPost(array $actor, int $postId): array
    {
        if ($postId < 1) throw new InvalidArgumentException('Choose a valid post.');
        $statement = $this->db->prepare("SELECT p.id,p.user_id,p.event_id,p.category,p.content,p.image_path,p.video_path,'approved' status,p.created_at,p.updated_at,p.reviewed_at,p.is_official,p.is_pinned,p.pinned_at,e.title event_title,e.end_at,u.id_number,u.first_name,u.middle_name,u.last_name,u.profile_photo_path,r.name author_role,(SELECT pr.type FROM tbl_post_reactions pr WHERE pr.post_id=p.id AND pr.user_id=? LIMIT 1) viewer_reaction,(SELECT COUNT(*) FROM tbl_post_reactions pr WHERE pr.post_id=p.id) reactions_count,(SELECT COUNT(*) FROM tbl_post_comments pc WHERE pc.post_id=p.id) comments_count FROM tbl_posts p JOIN tbl_users u ON u.id=p.user_id JOIN tbl_roles r ON r.id=u.role_id LEFT JOIN tbl_events e ON e.id=p.event_id WHERE p.id=? AND p.status='approved' AND p.deleted_at IS NULL LIMIT 1");
        $statement->execute([(int)$actor['id'],$postId]);
        $posts = $this->hydratePosts($statement->fetchAll());
        if (!$posts) throw new InvalidArgumentException('This post is no longer available.');
        return $posts[0];
    }

    public function commentsPage(int $postId, int|string|null $viewerId = null, ?string $cursor = null): array
    {
        if (!is_int($viewerId)) {
            $cursor = is_string($viewerId) ? $viewerId : $cursor;
            $viewerId = 0;
        }
        if ($postId < 1) throw new InvalidArgumentException('Choose a valid post.');
        $exists = $this->db->prepare("SELECT id FROM tbl_posts WHERE id=? AND status='approved' AND deleted_at IS NULL LIMIT 1");
        $exists->execute([$postId]);
        if (!$exists->fetchColumn()) throw new InvalidArgumentException('This post is no longer available.');
        $where = 'c.post_id=? AND c.parent_comment_id IS NULL';
        $params = [$postId];
        if ($cursor !== null && $cursor !== '') {
            $position = $this->decodeCursor($cursor, ['pin','at','id']);
            if (!in_array($position['pin'], [0,1], true) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string)$position['at']) || !filter_var($position['id'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]])) {
                throw new InvalidArgumentException('Choose a valid comment position.');
            }
            $where .= ' AND (c.is_pinned < ? OR (c.is_pinned=? AND (c.created_at > ? OR (c.created_at=? AND c.id > ?))))';
            array_push($params,$position['pin'],$position['pin'],$position['at'],$position['at'],(int)$position['id']);
        }
        $statement = $this->db->prepare("SELECT c.id,c.post_id,c.parent_comment_id,c.user_id,c.body,c.is_pinned,c.created_at,c.updated_at,u.id_number,u.first_name,u.middle_name,u.last_name,u.profile_photo_path,r.name author_role FROM tbl_post_comments c JOIN tbl_users u ON u.id=c.user_id JOIN tbl_roles r ON r.id=u.role_id WHERE $where ORDER BY c.is_pinned DESC,c.created_at,c.id LIMIT 21");
        $statement->execute($params);
        $rows = $statement->fetchAll();
        $hasMore = count($rows) > 20;
        if ($hasMore) array_pop($rows);
        $last = $rows ? $rows[count($rows)-1] : null;
        $nextCursor = $hasMore && $last ? $this->encodeCursor(['pin'=>(int)$last['is_pinned'],'at'=>$last['created_at'],'id'=>(int)$last['id']]) : null;
        $this->hydrateComments($rows, $viewerId);
        if ($rows) {
            $ids = array_column($rows, 'id');
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $replies = $this->db->prepare("SELECT c.id,c.post_id,c.parent_comment_id,c.user_id,c.body,c.is_pinned,c.created_at,c.updated_at,u.id_number,u.first_name,u.middle_name,u.last_name,u.profile_photo_path,r.name author_role FROM tbl_post_comments c JOIN tbl_users u ON u.id=c.user_id JOIN tbl_roles r ON r.id=u.role_id WHERE c.post_id=? AND c.parent_comment_id IN ($marks) ORDER BY c.created_at,c.id");
            $replies->execute([$postId, ...$ids]);
            $replyRows = $replies->fetchAll();
            $this->hydrateComments($replyRows, $viewerId);
            $positions = array_flip($ids);
            foreach ($rows as &$comment) $comment['replies'] = [];
            unset($comment);
            foreach ($replyRows as $reply) $rows[$positions[$reply['parent_comment_id']]]['replies'][] = $reply;
        }
        return ['comments'=>$rows,'next_cursor'=>$nextCursor];
    }

    public function reactionUsers(int $postId, ?int $commentId = null, string $type = 'all', int $page = 1): array
    {
        if (!in_array($type, ['all','like','love','laugh','wow'], true)) throw new InvalidArgumentException('Choose a valid reaction filter.');
        if ($page < 1 || $page > 1000) throw new InvalidArgumentException('Choose a valid reaction page.');
        $this->publicPost($postId);
        if ($commentId !== null) {
            $comment = $this->db->prepare('SELECT id FROM tbl_post_comments WHERE id=? AND post_id=? LIMIT 1');
            $comment->execute([$commentId,$postId]);
            if (!$comment->fetchColumn()) throw new InvalidArgumentException('This comment is no longer available.');
            $table = 'tbl_comment_reactions';
            $entityColumn = 'comment_id';
            $entityId = $commentId;
        } else {
            $table = 'tbl_post_reactions';
            $entityColumn = 'post_id';
            $entityId = $postId;
        }
        $countsQuery = $this->db->prepare("SELECT type,COUNT(*) total FROM $table WHERE $entityColumn=? GROUP BY type");
        $countsQuery->execute([$entityId]);
        $counts = [];
        foreach ($countsQuery->fetchAll() as $row) $counts[$row['type']] = (int)$row['total'];
        $total = $type === 'all' ? array_sum($counts) : (int)($counts[$type] ?? 0);
        $filter = $type === 'all' ? '' : ' AND reaction.type=?';
        $parameters = $type === 'all' ? [$entityId] : [$entityId,$type];
        $query = $this->db->prepare("SELECT reaction.type,reaction.created_at,u.id user_id,u.first_name,u.middle_name,u.last_name,u.profile_photo_path,rl.name role_name FROM $table reaction JOIN tbl_users u ON u.id=reaction.user_id JOIN tbl_roles rl ON rl.id=u.role_id WHERE reaction.$entityColumn=?$filter ORDER BY reaction.created_at DESC,u.last_name,u.first_name,u.id LIMIT ? OFFSET ?");
        foreach ($parameters as $index => $value) $query->bindValue($index + 1, $value);
        $query->bindValue(count($parameters) + 1, 51, PDO::PARAM_INT);
        $query->bindValue(count($parameters) + 2, ($page - 1) * 50, PDO::PARAM_INT);
        $query->execute();
        $reactors = $query->fetchAll();
        $hasMore = count($reactors) > 50;
        if ($hasMore) array_pop($reactors);
        foreach ($reactors as &$reactor) {
            $reactor['user_id'] = (int)$reactor['user_id'];
            $reactor['full_name'] = $this->name($reactor);
            $reactor['initials'] = $this->initials($reactor);
            unset($reactor['first_name'],$reactor['middle_name'],$reactor['last_name']);
        }
        unset($reactor);
        return ['counts'=>$counts,'total'=>$total,'reactors'=>$reactors,'page'=>$page,'has_more'=>$hasMore];
    }

    private function hydrateComments(array &$comments, int $viewerId): void
    {
        if (!$comments) return;
        $ids = array_map('intval', array_column($comments, 'id'));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $reactions = $this->db->prepare("SELECT comment_id,type,COUNT(*) total,SUM(CASE WHEN user_id=? THEN 1 ELSE 0 END) viewer_total FROM tbl_comment_reactions WHERE comment_id IN ($marks) GROUP BY comment_id,type");
        $reactions->execute([$viewerId,...$ids]);
        $counts = $viewers = [];
        foreach ($reactions->fetchAll() as $row) {
            $commentId = (int) $row['comment_id'];
            $counts[$commentId][$row['type']] = (int) $row['total'];
            if ((int) $row['viewer_total'] > 0) $viewers[$commentId] = $row['type'];
        }
        foreach ($comments as &$comment) {
            foreach (['id','post_id','user_id'] as $field) $comment[$field] = (int)$comment[$field];
            $comment['parent_comment_id'] = $comment['parent_comment_id'] === null ? null : (int)$comment['parent_comment_id'];
            $comment['is_pinned'] = (bool)$comment['is_pinned'];
            $comment['reaction_counts'] = $counts[$comment['id']] ?? [];
            $comment['reactions_count'] = array_sum($comment['reaction_counts']);
            $comment['viewer_reaction'] = $viewers[$comment['id']] ?? null;
            $comment['special_tag'] = UserBadge::forIdNumber($comment['id_number']);
            unset($comment['id_number']);
            $comment['author_name'] = $this->name($comment);
            $comment['author_initials'] = $this->initials($comment);
            foreach (['first_name','middle_name','last_name'] as $field) unset($comment[$field]);
        }
        unset($comment);
    }

    private function encodeCursor(array $position): string
    {
        return rtrim(strtr(base64_encode(json_encode($position, JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    private function decodeCursor(string $cursor, array $keys): array
    {
        if (strlen($cursor) > 200 || !preg_match('/^[A-Za-z0-9_-]+$/', $cursor)) throw new InvalidArgumentException('Choose a valid page position.');
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        $position = $decoded === false ? null : json_decode($decoded, true);
        if (!is_array($position) || array_keys($position) !== $keys) throw new InvalidArgumentException('Choose a valid page position.');
        return $position;
    }

    private function hydratePosts(array $posts): array
    {
        if (!$posts) return [];
        $posts = $this->attachPostImages($posts);
        $ids = array_map('intval', array_column($posts, 'id'));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $reactions = $this->db->prepare("SELECT post_id,type,COUNT(*) total FROM tbl_post_reactions WHERE post_id IN ($marks) GROUP BY post_id,type");
        $reactions->execute($ids);
        $reactionCounts = [];
        foreach ($reactions->fetchAll() as $row) $reactionCounts[(int) $row['post_id']][$row['type']] = (int) $row['total'];
        foreach ($posts as &$post) {
            foreach (['id','user_id','event_id'] as $field) $post[$field] = $post[$field] === null ? null : (int) $post[$field];
            $post['is_official'] = (bool) $post['is_official'];
            $post['is_pinned'] = (bool) ($post['is_pinned'] ?? false);
            $post['reactions_count'] = (int) $post['reactions_count'];
            $post['comments_count'] = (int) $post['comments_count'];
            $post['author_name'] = $this->name($post);
            $post['author_initials'] = $this->initials($post);
            $post['special_tag'] = UserBadge::forIdNumber($post['id_number']);
            unset($post['id_number']);
            $post['reaction_counts'] = $reactionCounts[$post['id']] ?? [];
            unset($post['sort_at']);
            foreach (['first_name','middle_name','last_name'] as $field) unset($post[$field]);
        }
        unset($post);
        return $posts;
    }

    private function ownPosts(int $userId): array
    {
        $statement = $this->db->prepare("SELECT p.id,p.event_id,p.content,p.image_path,p.video_path,p.status,p.rejection_reason,p.created_at,p.updated_at,e.title event_title FROM tbl_posts p LEFT JOIN tbl_events e ON e.id=p.event_id WHERE p.user_id=? AND p.deleted_at IS NULL AND p.status<>'approved' ORDER BY p.created_at DESC LIMIT 50");
        $statement->execute([$userId]);
        $posts = $this->attachPostImages($statement->fetchAll());
        foreach ($posts as &$post) { $post['id'] = (int) $post['id']; $post['event_id'] = $post['event_id'] === null ? null : (int) $post['event_id']; }
        unset($post);
        return $posts;
    }

    private function moderationQueue(int $limit, int $offset): array
    {
        $statement = $this->db->prepare("SELECT p.id,p.user_id,p.event_id,p.content,p.image_path,p.video_path,p.status,p.created_at,e.title event_title,u.id_number,u.first_name,u.middle_name,u.last_name,u.profile_photo_path FROM tbl_posts p JOIN tbl_users u ON u.id=p.user_id LEFT JOIN tbl_events e ON e.id=p.event_id WHERE p.deleted_at IS NULL AND p.status='pending' ORDER BY p.created_at ASC,p.id ASC LIMIT ? OFFSET ?");
        $statement->bindValue(1, $limit, PDO::PARAM_INT);
        $statement->bindValue(2, $offset, PDO::PARAM_INT);
        $statement->execute();
        $posts = $this->attachPostImages($statement->fetchAll());
        foreach ($posts as &$post) { foreach (['id','user_id','event_id'] as $field) $post[$field] = $post[$field] === null ? null : (int) $post[$field]; $post['author_name'] = $this->name($post); $post['author_initials'] = $this->initials($post); $post['special_tag'] = UserBadge::forIdNumber($post['id_number']); unset($post['id_number']); foreach (['first_name','middle_name','last_name'] as $field) unset($post[$field]); }
        unset($post);
        return $posts;
    }

    private function moderationCounts(): array
    {
        $counts = ['pending'=>0,'rejected'=>0,'hidden'=>0];
        $rows = $this->db->query("SELECT p.status,COUNT(*) total FROM tbl_posts p WHERE p.deleted_at IS NULL AND p.status IN ('pending','rejected','hidden') GROUP BY p.status")->fetchAll();
        foreach ($rows as $row) $counts[$row['status']] = (int) $row['total'];
        return $counts;
    }

    private function activeEvents(?int $limit): array
    {
        $sql = "SELECT id,title,start_at,end_at,poster_path,is_featured FROM tbl_events WHERE deleted_at IS NULL AND end_at>=CURRENT_TIMESTAMP ORDER BY CASE WHEN start_at<=CURRENT_TIMESTAMP THEN 0 ELSE 1 END,start_at,id".($limit ? ' LIMIT '.(int) $limit : '');
        return $this->normalizeEvents($this->db->query($sql)->fetchAll());
    }

    private function featuredEvents(): array
    {
        return $this->normalizeEvents($this->db->query("SELECT id,title,start_at,end_at,location,description,poster_path,is_featured FROM tbl_events WHERE deleted_at IS NULL AND is_featured=1 AND end_at>=CURRENT_TIMESTAMP AND (featured_until IS NULL OR featured_until>=CURRENT_TIMESTAMP) ORDER BY featured_order IS NULL,featured_order,start_at LIMIT 6")->fetchAll());
    }

    private function carouselEvents(): array
    {
        $photoStatement = $this->db->prepare("SELECT id,title,start_at,end_at,location,description,poster_path,is_featured FROM tbl_events WHERE deleted_at IS NULL AND poster_path IS NOT NULL AND poster_path<>'' ORDER BY CASE WHEN is_featured=1 AND end_at>=CURRENT_TIMESTAMP AND (featured_until IS NULL OR featured_until>=CURRENT_TIMESTAMP) THEN 0 ELSE 1 END,(end_at>=CURRENT_TIMESTAMP) DESC,CASE WHEN start_at<=CURRENT_TIMESTAMP AND end_at>=CURRENT_TIMESTAMP THEN 0 ELSE 1 END,start_at DESC,id DESC LIMIT 6");
        $photoStatement->execute();
        $photos = $this->normalizeEvents($photoStatement->fetchAll());
        if ($photos) return $photos;
        $featured = $this->featuredEvents();
        if ($featured) return $featured;
        return $this->normalizeEvents($this->db->query("SELECT id,title,start_at,end_at,location,description,poster_path,is_featured FROM tbl_events WHERE deleted_at IS NULL ORDER BY (end_at>=CURRENT_TIMESTAMP) DESC,CASE WHEN start_at<=CURRENT_TIMESTAMP AND end_at>=CURRENT_TIMESTAMP THEN 0 ELSE 1 END,start_at DESC,id DESC LIMIT 6")->fetchAll());
    }

    private function eventProgram(): array
    {
        $events = $this->db->query("SELECT id,title,start_at,end_at,location FROM tbl_events WHERE deleted_at IS NULL AND end_at>=CURRENT_TIMESTAMP ORDER BY CASE WHEN start_at<=CURRENT_TIMESTAMP THEN 0 ELSE 1 END,start_at,id LIMIT 6")->fetchAll();
        if (!$events) $events = $this->db->query("SELECT id,title,start_at,end_at,location FROM tbl_events WHERE deleted_at IS NULL ORDER BY end_at DESC,id DESC LIMIT 4")->fetchAll();
        if (!$events) return [];
        $eventIds = array_map('intval', array_column($events, 'id'));
        $marks = implode(',', array_fill(0, count($eventIds), '?'));
        $statement = $this->db->prepare("SELECT ea.id,ea.event_id,ea.name,a.description FROM tbl_event_activities ea INNER JOIN tbl_activities a ON a.id=ea.activity_id WHERE ea.status<>'inactive' AND ea.event_id IN ($marks) ORDER BY ea.event_id,ea.id");
        $statement->execute($eventIds);
        $activities = [];
        foreach ($statement->fetchAll() as $activity) {
            $activity['id'] = (int) $activity['id'];
            $activity['event_id'] = (int) $activity['event_id'];
            $activities[$activity['event_id']][] = $activity;
        }
        foreach ($events as &$event) {
            $event['id'] = (int) $event['id'];
            $event['activities'] = $activities[$event['id']] ?? [];
        }
        unset($event);
        return $events;
    }

    private function officerEvents(int $userId): array
    {
        $statement = $this->db->prepare("SELECT DISTINCT e.id,e.title,e.start_at,e.end_at,e.poster_path,e.is_featured FROM tbl_sbo_event_assignments sea JOIN tbl_officer_responsibilities r ON r.id=sea.responsibility_id AND r.code='media' JOIN tbl_sbo_officer_assignments oa ON oa.id=sea.officer_assignment_id AND oa.status='Active' JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id JOIN tbl_events e ON e.id=s.event_id AND e.deleted_at IS NULL WHERE oa.officer_user_id=? AND sea.status='active' AND e.end_at>=CURRENT_TIMESTAMP ORDER BY e.start_at,e.id");
        $statement->execute([$userId]);
        return $this->normalizeEvents($statement->fetchAll());
    }

    private function normalizeEvents(array $events): array
    {
        foreach ($events as &$event) { $event['id'] = (int) $event['id']; $event['is_featured'] = (bool) ($event['is_featured'] ?? false); }
        unset($event);
        return $events;
    }

    private function eventId(array $input, int $userId, MediaPermissions $permissions): ?int
    {
        $eventId = filter_var($input['event_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($eventId === null) return null;
        $statement = $this->db->prepare('SELECT id FROM tbl_events WHERE id=? AND deleted_at IS NULL AND end_at>=CURRENT_TIMESTAMP');
        $statement->execute([$eventId]);
        if (!$statement->fetchColumn()) throw new InvalidArgumentException('Choose an active event.');
        if ($permissions->isOfficer()) $this->assertOfficerEvent($userId, $eventId);
        return $eventId;
    }

    private function assertOfficerEvent(int $userId, int $eventId): void
    {
        $statement = $this->db->prepare("SELECT 1 FROM tbl_sbo_event_assignments sea JOIN tbl_officer_responsibilities r ON r.id=sea.responsibility_id AND r.code='media' JOIN tbl_sbo_officer_assignments oa ON oa.id=sea.officer_assignment_id AND oa.status='Active' JOIN tbl_event_attendance_schedules s ON s.id=sea.event_schedule_id WHERE oa.officer_user_id=? AND s.event_id=? AND sea.status='active' LIMIT 1");
        $statement->execute([$userId, $eventId]);
        if (!$statement->fetchColumn()) throw new MediaForbiddenException('You may manage media only for an event assigned to you.');
    }

    private function content(array $input, bool $allowEmpty = false): string
    {
        $content = trim((string) ($input['content'] ?? ''));
        if ((!$allowEmpty && $content === '') || mb_strlen($content) > 3000) throw new InvalidArgumentException('Write a post or attach a photo or video, up to 3,000 characters.');
        return $content;
    }

    private function ownedPost(int $postId, int $userId): array
    {
        $statement = $this->db->prepare('SELECT * FROM tbl_posts WHERE id=? AND user_id=? AND deleted_at IS NULL');
        $statement->execute([$postId, $userId]);
        $post = $statement->fetch();
        if (!$post) throw new MediaForbiddenException('You can change only your own posts.');
        return $post;
    }

    private function publicPost(int $postId): array
    {
        $statement = $this->db->prepare("SELECT id,user_id FROM tbl_posts WHERE id=? AND status='approved' AND deleted_at IS NULL");
        $statement->execute([$postId]);
        $post = $statement->fetch();
        if (!$post) throw new InvalidArgumentException('This post is not publicly available.');
        return $post;
    }

    private function ownedComment(int $userId, int $postId, int $commentId): void
    {
        $statement = $this->db->prepare('SELECT id FROM tbl_post_comments WHERE id=? AND post_id=? AND user_id=?');
        $statement->execute([$commentId, $postId, $userId]);
        if (!$statement->fetchColumn()) throw new MediaForbiddenException('You can change only your own comments.');
    }

    private function storeMedia(array $files): array
    {
        $images = $this->storeImages($files['images'] ?? ($files['image'] ?? null));
        $video = $files['video'] ?? null;
        if ($images && $this->hasUpload($video)) {
            foreach ($images as $path) $this->removeUpload($path);
            throw new InvalidArgumentException('Attach photos or one video, not both.');
        }
        try { return [$images, $this->storeVideo($video)]; }
        catch (Throwable $exception) { foreach ($images as $path) $this->removeUpload($path); throw $exception; }
    }

    private function storeImages(mixed $upload): array
    {
        if (!$this->hasUpload($upload)) return [];
        if (!is_array($upload['name'] ?? null)) return [$this->storeImage($upload, self::IMAGE_DIRECTORY, 5 * 1024 * 1024)];
        $count = count($upload['name']);
        if ($count > 30) throw new InvalidArgumentException('Choose up to 30 photos per post.');
        if (array_sum(array_map('intval', $upload['size'] ?? [])) > 25 * 1024 * 1024) throw new InvalidArgumentException('The combined photo upload may not exceed 25 MB.');
        $paths = [];
        try {
            for ($index = 0; $index < $count; $index++) {
                $file = [
                    'name' => $upload['name'][$index] ?? '',
                    'type' => $upload['type'][$index] ?? '',
                    'tmp_name' => $upload['tmp_name'][$index] ?? '',
                    'error' => $upload['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $upload['size'][$index] ?? 0,
                ];
                if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                $paths[] = $this->storeImage($file, self::IMAGE_DIRECTORY, 5 * 1024 * 1024);
            }
        } catch (Throwable $exception) {
            foreach ($paths as $path) $this->removeUpload($path);
            throw $exception;
        }
        return $paths;
    }

    private function attachPostImages(array $posts): array
    {
        if (!$posts) return [];
        $ids = array_map('intval', array_column($posts, 'id'));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->db->prepare("SELECT post_id,image_path FROM tbl_post_images WHERE post_id IN ($marks) ORDER BY post_id,sort_order,id");
        $statement->execute($ids);
        $images = [];
        foreach ($statement->fetchAll() as $row) $images[(int) $row['post_id']][] = (string) $row['image_path'];
        foreach ($posts as &$post) {
            $postId = (int) $post['id'];
            $post['images'] = $images[$postId] ?? (!empty($post['image_path']) ? [(string) $post['image_path']] : []);
        }
        unset($post);
        return $posts;
    }

    private function postImagePaths(int $postId, ?string $fallback): array
    {
        $statement = $this->db->prepare('SELECT image_path FROM tbl_post_images WHERE post_id=? ORDER BY sort_order,id');
        $statement->execute([$postId]);
        $paths = array_map('strval', $statement->fetchAll(PDO::FETCH_COLUMN));
        return $paths ?: ($fallback ? [$fallback] : []);
    }

    private function replacePostImages(int $postId, array $paths): void
    {
        $this->db->prepare('DELETE FROM tbl_post_images WHERE post_id=?')->execute([$postId]);
        $insert = $this->db->prepare('INSERT INTO tbl_post_images(post_id,image_path,sort_order,created_at) VALUES(?,?,?,CURRENT_TIMESTAMP)');
        foreach ($paths as $index => $path) $insert->execute([$postId, $path, $index]);
    }

    private function storeImage(mixed $file, string $directory, int $maxBytes): ?string
    {
        if (!$this->hasUpload($file)) return null;
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > $maxBytes) throw new InvalidArgumentException('The image upload failed or exceeds the allowed size.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extension = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
        $dimensions = @getimagesize((string) $file['tmp_name']);
        if (!$extension || !$dimensions || $dimensions[0] > 4096 || $dimensions[1] > 4096) throw new InvalidArgumentException('Use a JPG, PNG, or WebP image up to 4096 × 4096 pixels.');
        return $this->moveUpload($file, $directory, $extension);
    }

    private function storeVideo(mixed $file): ?string
    {
        if (!$this->hasUpload($file)) return null;
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 25 * 1024 * 1024) throw new InvalidArgumentException('Choose a video no larger than 25 MB.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extension = ['video/mp4'=>'mp4','video/webm'=>'webm','video/quicktime'=>'mov'][$mime] ?? null;
        if (!$extension) throw new InvalidArgumentException('Use an MP4, WebM, or MOV video.');
        $duration = MediaVideoDuration::seconds((string) $file['tmp_name'], $mime);
        if ($duration === null) throw new InvalidArgumentException('The video duration could not be verified. Choose a valid video up to 5 minutes.');
        if ($duration > MediaVideoDuration::MAX_SECONDS) throw new InvalidArgumentException('Videos must be 5 minutes or shorter.');
        return $this->moveUpload($file, self::VIDEO_DIRECTORY, $extension);
    }

    private function moveUpload(array $file, string $relativeDirectory, string $extension): string
    {
        $directory = dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativeDirectory);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('The media directory could not be created.');
        $filename = bin2hex(random_bytes(20)).'.'.$extension;
        if (!move_uploaded_file((string) $file['tmp_name'], $directory.DIRECTORY_SEPARATOR.$filename)) throw new RuntimeException('The media file could not be stored.');
        return $relativeDirectory.'/'.$filename;
    }

    private function removeUpload(?string $path): void
    {
        if (!$path || !str_starts_with($path, 'assets/uploads/')) return;
        $root = realpath(dirname(__DIR__).DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'uploads');
        $file = realpath(dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
        if ($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
    }

    private function hasUpload(mixed $file): bool
    {
        if (!is_array($file)) return false;
        $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        return is_array($error)
            ? count(array_filter($error, static fn ($item) => $item !== UPLOAD_ERR_NO_FILE)) > 0
            : $error !== UPLOAD_ERR_NO_FILE;
    }

    private function audit(int $postId, int $actorId, string $action, ?string $from, ?string $to, ?string $notes = null): void
    {
        $statement = $this->db->prepare('INSERT INTO tbl_post_audits(post_id,actor_id,action,from_status,to_status,notes,created_at,updated_at) VALUES(?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $statement->execute([$postId, $actorId, $action, $from, $to, $notes]);
    }

    private function notifyModerators(int $postId, array $actor): void
    {
        $moderators = $this->db->query("SELECT u.id FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id JOIN tbl_user_statuses s ON s.id=u.status WHERE r.name IN ('Admin','SBO','SBO Adviser') AND s.label='active'")->fetchAll(PDO::FETCH_COLUMN);
        $name = trim((string) ($actor['full_name'] ?? ($actor['first_name'] ?? '').' '.($actor['last_name'] ?? ''))) ?: 'A student';
        foreach ($moderators as $moderatorId) $this->notification((int) $moderatorId, ['post_id'=>$postId,'message'=>$name.' submitted a post for review.']);
    }

    private function notifyPostOwner(int $userId, int $postId, string $status, ?string $reason): void
    {
        $message = $status === 'hidden' ? 'Your post was hidden by a moderator.' : 'Your post was '.$status.'.';
        $this->notification($userId, ['post_id'=>$postId,'status'=>$status,'reason'=>$reason,'message'=>$message]);
    }

    private function actorName(int $userId): string
    {
        $statement = $this->db->prepare('SELECT first_name,middle_name,last_name FROM tbl_users WHERE id=? LIMIT 1');
        $statement->execute([$userId]);
        $actor = $statement->fetch() ?: [];
        return $this->name($actor);
    }

    private function notification(int $userId, array $data, string $type = 'App\\Notifications\\PostReviewed'): void
    {
        $statement = $this->db->prepare('INSERT INTO tbl_notifications(id,type,notifiable_type,notifiable_id,data,created_at,updated_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        $statement->execute([$this->uuid(), $type, 'App\\Models\\User', $userId, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
    }

    private function name(array $row): string
    {
        return trim(implode(' ', array_filter([$row['first_name'] ?? null,$row['middle_name'] ?? null,$row['last_name'] ?? null]))) ?: 'CITE user';
    }

    private function initials(array $row): string
    {
        return mb_strtoupper(mb_substr((string) ($row['first_name'] ?? ''),0,1).mb_substr((string) ($row['last_name'] ?? ''),0,1));
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16); $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }
}
