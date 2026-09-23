<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/MediaRepository.php';

$actor = AuthGuard::requireAnyRole(MediaPermissions::roles());
$repository = new MediaRepository((new Database())->connection());

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $getAction = (string) ($_GET['action'] ?? '');
        if ($getAction === 'moderation') {
            $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
            if ($page === false || $page < 1) throw new InvalidArgumentException('Choose a valid review page.');
            JsonResponse::send(['success'=>true,'data'=>$repository->moderationPage($actor, $page)]);
        }
        if ($getAction === 'comments') {
            $postId = filter_var($_GET['post_id'] ?? null, FILTER_VALIDATE_INT);
            if ($postId === false || $postId === null || $postId < 1) throw new InvalidArgumentException('Choose a valid post.');
            JsonResponse::send(['success'=>true,'data'=>$repository->commentsPage($postId, (int)$actor['id'], isset($_GET['cursor']) ? (string)$_GET['cursor'] : null)]);
        }
        if ($getAction === 'reactions') {
            $postId = filter_var($_GET['post_id'] ?? null, FILTER_VALIDATE_INT);
            $commentId = isset($_GET['comment_id']) ? filter_var($_GET['comment_id'], FILTER_VALIDATE_INT) : null;
            $page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
            if ($postId === false || $postId === null || $postId < 1) throw new InvalidArgumentException('Choose a valid post.');
            if ($commentId === false || ($commentId !== null && $commentId < 1)) throw new InvalidArgumentException('Choose a valid comment.');
            if ($page === false || $page < 1) throw new InvalidArgumentException('Choose a valid reaction page.');
            JsonResponse::send(['success'=>true,'data'=>$repository->reactionUsers($postId,$commentId,(string)($_GET['type'] ?? 'all'),$page)]);
        }
        if ($getAction === 'post') {
            $postId = filter_var($_GET['post_id'] ?? null, FILTER_VALIDATE_INT);
            if ($postId === false || $postId === null || $postId < 1) throw new InvalidArgumentException('Choose a valid post.');
            JsonResponse::send(['success'=>true,'data'=>$repository->approvedPost($actor, $postId)]);
        }
        if ($getAction === 'notifications') JsonResponse::send(['success'=>true,'data'=>$repository->notificationData((int) $actor['id'])]);
        if ($getAction === 'authors') JsonResponse::send(['success'=>true,'data'=>['users'=>$repository->searchAuthors((string) ($_GET['q'] ?? ''))]]);
        if ($getAction === 'author') {
            $authorId = filter_var($_GET['user_id'] ?? null, FILTER_VALIDATE_INT);
            if ($authorId === false || $authorId === null || $authorId < 1) throw new InvalidArgumentException('Choose a valid account.');
            JsonResponse::send(['success'=>true,'data'=>$repository->publicAuthorProfile($actor, $authorId, isset($_GET['cursor']) ? (string) $_GET['cursor'] : null)]);
        }
        $eventId = filter_var($_GET['event_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($getAction === 'posts') {
            JsonResponse::send(['success'=>true,'data'=>$repository->postsPage($actor, $eventId, isset($_GET['cursor']) ? (string)$_GET['cursor'] : null, ($_GET['scope'] ?? '') === 'mine')]);
        }
        if ($getAction !== '') throw new InvalidArgumentException('Unknown media action.');
        JsonResponse::send(['success'=>true,'data'=>$repository->pageData($actor, $eventId)]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'], 405);
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) JsonResponse::send(['success'=>false,'message'=>'Your session expired. Refresh and try again.'], 403);
    $input = $_POST;
    if (!$input) { $decoded = json_decode(file_get_contents('php://input'), true); $input = is_array($decoded) ? $decoded : []; }
    $action = (string) ($input['action'] ?? 'create');
    $userId = (int) $actor['id'];
    $postId = (int) ($input['post_id'] ?? $input['id'] ?? 0);
    if ($action === 'mark_notifications_read') {
        $updated = $repository->markNotificationsRead($userId, isset($input['notification_id']) ? (string) $input['notification_id'] : null);
        JsonResponse::send(['success'=>true,'updated'=>$updated,'message'=>'Notifications marked as read.']);
    }
    $message = match ($action) {
        'create' => (function () use ($repository,$actor,$input) { $repository->create($actor,$input,$_FILES); return (new MediaPermissions((string)$actor['role']))->isStudent() ? 'Post submitted for adviser approval.' : 'Post published.'; })(),
        'update' => (function () use ($repository,$actor,$input) { $repository->update($actor,$input,$_FILES); return (new MediaPermissions((string)$actor['role']))->isStudent() ? 'Post updated and sent for review.' : 'Post updated.'; })(),
        'delete' => (function () use ($repository,$actor,$postId) { $repository->delete($actor,$postId); return 'Post deleted.'; })(),
        'approve','reject' => (function () use ($repository,$actor,$postId,$action,$input) { $repository->review($actor,$postId,$action === 'approve' ? 'approved' : 'rejected',(string)($input['reason'] ?? '')); return $action === 'approve' ? 'Post approved.' : 'Post rejected.'; })(),
        'hide' => (function () use ($repository,$actor,$postId,$input) { $repository->hide($actor,$postId,(string)($input['reason'] ?? '')); return 'Post hidden from the public feed.'; })(),
        'reaction_toggle' => (function () use ($repository,$userId,$postId,$input) {
            $desired = array_key_exists('active', $input) ? filter_var($input['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
            if (array_key_exists('active', $input) && $desired === null) throw new InvalidArgumentException('Choose a valid reaction state.');
            $repository->toggleReaction($userId,$postId,(string)($input['type'] ?? ''),$desired);
            return 'Reaction updated.';
        })(),
        'comment_reaction_toggle' => (function () use ($repository,$userId,$postId,$input) {
            $desired = array_key_exists('active', $input) ? filter_var($input['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
            if (array_key_exists('active', $input) && $desired === null) throw new InvalidArgumentException('Choose a valid reaction state.');
            $commentId = filter_var($input['comment_id'] ?? null, FILTER_VALIDATE_INT);
            if ($commentId === false || $commentId === null || $commentId < 1) throw new InvalidArgumentException('Choose a valid comment.');
            $repository->toggleCommentReaction($userId,$postId,$commentId,(string)($input['type'] ?? ''),$desired);
            return 'Comment reaction updated.';
        })(),
        'comment_create','comment_update' => (function () use ($repository,$userId,$postId,$input,$action) {
            $parentId=$action === 'comment_create' && isset($input['parent_comment_id']) ? filter_var($input['parent_comment_id'],FILTER_VALIDATE_INT) : null;
            if($parentId===false)throw new InvalidArgumentException('Choose a valid comment to reply to.');
            $repository->saveComment($userId,$postId,(string)($input['body'] ?? ''),$action === 'comment_update' ? (int)($input['comment_id'] ?? 0) : null,$parentId);
            if($action === 'comment_update')return 'Comment updated.';
            return $parentId !== null ? 'Reply added.' : 'Comment added.';
        })(),
        'comment_delete' => (function () use ($repository,$userId,$postId,$input) { $repository->deleteComment($userId,$postId,(int)($input['comment_id'] ?? 0)); return 'Comment deleted.'; })(),
        'comment_pin' => (function () use ($repository,$userId,$postId,$input) { $repository->pinComment($userId,$postId,(int)($input['comment_id'] ?? 0),!empty($input['pin'])); return !empty($input['pin']) ? 'Comment pinned.' : 'Comment unpinned.'; })(),
        'carousel_update' => (function () use ($repository,$actor,$input) { $repository->updateCarousel($actor,$input,$_FILES); return 'Event carousel updated.'; })(),
        default => throw new InvalidArgumentException('Unknown media action.'),
    };
    JsonResponse::send(['success'=>true,'message'=>$message]);
} catch (MediaForbiddenException $exception) {
    JsonResponse::send(['success'=>false,'message'=>$exception->getMessage()], 403);
} catch (InvalidArgumentException $exception) {
    JsonResponse::send(['success'=>false,'message'=>$exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success'=>false,'message'=>'The media request failed.'], 500);
}
