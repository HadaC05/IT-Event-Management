<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/MediaRepository.php';

$live = (new Database())->connection();
$source = (string)$live->query('SELECT DATABASE()')->fetchColumn();
$scratch = 'media_replies_test_'.bin2hex(random_bytes(4));
if (!preg_match('/^[A-Za-z0-9_]+$/', $source) || !preg_match('/^media_replies_test_[0-9a-f]{8}$/', $scratch)) throw new RuntimeException('Invalid test database name.');
$assert = static function (bool $valid, string $message): void { if (!$valid) throw new RuntimeException('FAIL: '.$message); echo 'PASS: '.$message.PHP_EOL; };
$reject = static function (callable $operation, string $message) use ($assert): void {
    try { $operation(); $assert(false, $message); }
    catch (InvalidArgumentException $error) { $assert(true, $message); }
};

$live->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    foreach (['tbl_roles','tbl_user_statuses','tbl_users','tbl_events','tbl_posts','tbl_post_audits','tbl_post_comments','tbl_post_reactions','tbl_notifications'] as $table) $live->exec("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`");
    $live->exec("INSERT INTO `$scratch`.`tbl_roles` SELECT * FROM `$source`.`tbl_roles`");
    $live->exec("INSERT INTO `$scratch`.`tbl_user_statuses` SELECT * FROM `$source`.`tbl_user_statuses`");
    $db = (new Database(null, $scratch))->connection();
    $photoMigration = file_get_contents(__DIR__.'/../database/deploy_migrations/20260923_0009_post_multiple_images.sql');
    if ($photoMigration === false) throw new RuntimeException('Post photo migration is missing.');
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $photoMigration) as $statement) {
        if (trim($statement) !== '') $db->exec($statement);
    }
    $migration = file_get_contents(__DIR__.'/../database/deploy_migrations/20260923_0005_post_comment_replies.sql');
    if ($migration === false) throw new RuntimeException('Reply migration is missing.');
    foreach ([1,2] as $attempt) foreach (preg_split('/;\s*(?:\r?\n|$)/', $migration) as $statement) {
        if (trim($statement) !== '') $db->query($statement)->closeCursor();
    }
    $reactionMigration = file_get_contents(__DIR__.'/../database/deploy_migrations/20260924_0010_comment_reactions.sql');
    if ($reactionMigration === false) throw new RuntimeException('Comment reaction migration is missing.');
    $db->exec($reactionMigration);
    $facultyRole = (int)$db->query("SELECT id FROM tbl_roles WHERE name='Faculty'")->fetchColumn();
    if (!$facultyRole) throw new RuntimeException('Faculty role is unavailable.');
    $user = $db->prepare("INSERT INTO tbl_users(role_id,first_name,last_name,username,password,email,profile_photo_path,created_at,updated_at) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    foreach ([['Micah','Lago','micah','micah@test.invalid',null],['Domingo','Ancog','domingo','domingo@test.invalid',null],['Brian','Ragasi','brian','brian@test.invalid',null]] as $person) {
        $user->execute([$facultyRole,$person[0],$person[1],$person[2],'test',$person[3],$person[4]]);
        $ids[$person[2]] = (int)$db->lastInsertId();
    }
    $activeStatus = (int)$db->query("SELECT id FROM tbl_user_statuses WHERE label='active' LIMIT 1")->fetchColumn();
    $db->prepare('UPDATE tbl_users SET status=? WHERE id IN (?,?,?)')->execute([$activeStatus,$ids['micah'],$ids['domingo'],$ids['brian']]);
    $db->prepare('UPDATE tbl_users SET id_number=? WHERE id=?')->execute(['02-2122-030923',$ids['micah']]);
    $db->prepare('UPDATE tbl_users SET id_number=? WHERE id=?')->execute(['02-2324-01129',$ids['brian']]);
    $post = $db->prepare("INSERT INTO tbl_posts(user_id,content,status,created_at,updated_at) VALUES(?,?,'approved',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    $post->execute([$ids['micah'],'Hey']); $postId = (int)$db->lastInsertId();
    $post->execute([$ids['micah'],'Another post']); $otherPostId = (int)$db->lastInsertId();
    $repo = new MediaRepository($db);
    $firstImage = 'assets/uploads/posts/test-first.png';
    $secondImage = 'assets/uploads/posts/test-second.png';
    $db->prepare('UPDATE tbl_posts SET image_path=? WHERE id=?')->execute([$firstImage,$postId]);
    $db->prepare('INSERT INTO tbl_post_images(post_id,image_path,sort_order) VALUES(?,?,0),(?,?,1)')->execute([$postId,$firstImage,$postId,$secondImage]);
    $viewer = ['id'=>$ids['brian'],'role'=>'Faculty'];
    $assert($repo->approvedPost($viewer,$postId)['images'] === [$firstImage,$secondImage], 'Public post returns both photos in order');
    $profile = $repo->publicAuthorProfile($viewer,$ids['micah']);
    $assert($profile['profile']['post_count'] === 2 && count($profile['posts']) === 2, 'Public author profile shows approved posts');
    $assert(count($repo->searchAuthors('Mic')) === 1, 'Author search finds the account with public posts');
    $repo->saveComment($ids['domingo'],$postId,'Yow',null);
    $rootId = (int)$db->query("SELECT id FROM tbl_post_comments WHERE body='Yow' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $repo->saveComment($ids['brian'],$postId,'tabang lord',null,$rootId);
    $replyId = (int)$db->query("SELECT id FROM tbl_post_comments WHERE body='tabang lord' ORDER BY id DESC LIMIT 1")->fetchColumn();
    $domingoNotifications = $repo->notificationData($ids['domingo']);
    $assert($domingoNotifications['unread_notifications'] === 1 && str_contains($domingoNotifications['notifications'][0]['message'], 'replied to your comment'), 'Reply notifies the parent comment author');
    $repo->markNotificationsRead($ids['domingo']);
    $assert($repo->notificationData($ids['domingo'])['unread_notifications'] === 0, 'Comment author can mark the reply notification read');
    $photo = $db->prepare('UPDATE tbl_users SET profile_photo_path=? WHERE id=?');
    $photo->execute(['assets/uploads/domingo.jpg',$ids['domingo']]);
    $photo->execute(['assets/uploads/brian.jpg',$ids['brian']]);
    $page = $repo->commentsPage($postId);
    $assert(count($page['comments']) === 1 && count($page['comments'][0]['replies']) === 1, 'Reply appears beneath its parent comment');
    $assert($page['comments'][0]['special_tag'] === null && $page['comments'][0]['replies'][0]['special_tag'] === 'Dev', 'Dev tag follows the correct reply author');
    $assert($page['comments'][0]['profile_photo_path'] === 'assets/uploads/domingo.jpg', 'Existing comments show a profile photo added later');
    $assert($page['comments'][0]['replies'][0]['id'] === $replyId && $page['comments'][0]['replies'][0]['profile_photo_path'] === 'assets/uploads/brian.jpg', 'Reply includes the author profile photo');
    $repo->toggleReaction($ids['brian'],$postId,'love',true);
    $postWithReaction = $repo->approvedPost($viewer,$postId);
    $assert($postWithReaction['viewer_reaction'] === 'love' && $postWithReaction['reaction_counts']['love'] === 1, 'Love reaction appears on the post');
    $repo->toggleReaction($ids['brian'],$postId,'laugh',true);
    $assert($repo->approvedPost($viewer,$postId)['reaction_counts'] === ['laugh'=>1], 'Changing emoji replaces the previous post reaction');
    $assert($repo->reactionUsers($postId)['reactors'][0]['full_name'] === 'Brian Ragasi', 'Post reaction list identifies the reactor');
    $repo->toggleCommentReaction($ids['micah'],$postId,$rootId,'like',true);
    $repo->toggleCommentReaction($ids['brian'],$postId,$rootId,'love',true);
    $reactedPage = $repo->commentsPage($postId,$ids['brian']);
    $assert($reactedPage['comments'][0]['reaction_counts'] === ['like'=>1,'love'=>1] && $reactedPage['comments'][0]['viewer_reaction'] === 'love', 'Comment shows reaction counts and the viewer emoji');
    $assert($repo->reactionUsers($postId,$rootId,'love')['reactors'][0]['full_name'] === 'Brian Ragasi', 'Comment reaction list filters by emoji');
    $repo->toggleCommentReaction($ids['brian'],$postId,$rootId,'love',false);
    $assert($repo->commentsPage($postId,$ids['brian'])['comments'][0]['reactions_count'] === 1, 'Removing a reaction updates the comment count');
    $reject(fn() => $repo->saveComment($ids['brian'],$otherPostId,'Wrong post',null,$rootId), 'Replies cannot target comments on another post');
    $reject(fn() => $repo->saveComment($ids['brian'],$postId,'Nested',null,$replyId), 'Replies cannot target another reply');
    $reject(fn() => $repo->pinComment($ids['micah'],$postId,$replyId,true), 'Replies cannot be pinned as top-level comments');
    for ($number = 0; $number < 21; $number++) $repo->saveComment($ids['micah'],$postId,'Root '.$number,null);
    $repo->pinComment($ids['micah'],$postId,$rootId,true);
    $first = $repo->commentsPage($postId);
    $second = $repo->commentsPage($postId,$first['next_cursor']);
    $assert(count($first['comments']) === 20 && count($second['comments']) === 2, 'Comment pages count roots while keeping replies attached');
    $assert($first['comments'][0]['id'] === $rootId && count($first['comments'][0]['replies']) === 1, 'Pinned parent keeps its reply on the first page');
    $repo->deleteComment($ids['domingo'],$postId,$rootId);
    $left = $db->prepare('SELECT COUNT(*) FROM tbl_post_comments WHERE id IN (?,?)');
    $left->execute([$rootId,$replyId]);
    $assert((int)$left->fetchColumn() === 0, 'Deleting a parent removes its replies');
    $repo->update(['id'=>$ids['micah'],'role'=>'Faculty'], ['id'=>$postId,'content'=>'Edited text only'], []);
    $assert($repo->approvedPost($viewer,$postId)['images'] === [$firstImage,$secondImage], 'Text-only edit preserves both photos');
    $repo->update(['id'=>$ids['micah'],'role'=>'Faculty'], ['id'=>$postId,'content'=>'Removed photos','remove_media'=>'1'], []);
    $assert($repo->approvedPost($viewer,$postId)['images'] === [], 'Removing media clears all post photos');
} finally {
    $live->exec("DROP DATABASE IF EXISTS `$scratch`");
}
