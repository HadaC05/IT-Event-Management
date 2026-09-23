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
    foreach (['tbl_roles','tbl_users','tbl_posts','tbl_post_comments'] as $table) $live->exec("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`");
    $live->exec("INSERT INTO `$scratch`.`tbl_roles` SELECT * FROM `$source`.`tbl_roles`");
    $db = (new Database(null, $scratch))->connection();
    $migration = file_get_contents(__DIR__.'/../database/deploy_migrations/20260923_0005_post_comment_replies.sql');
    if ($migration === false) throw new RuntimeException('Reply migration is missing.');
    foreach ([1,2] as $attempt) foreach (preg_split('/;\s*(?:\r?\n|$)/', $migration) as $statement) {
        if (trim($statement) !== '') $db->query($statement)->closeCursor();
    }
    $facultyRole = (int)$db->query("SELECT id FROM tbl_roles WHERE name='Faculty'")->fetchColumn();
    if (!$facultyRole) throw new RuntimeException('Faculty role is unavailable.');
    $user = $db->prepare("INSERT INTO tbl_users(role_id,first_name,last_name,username,password,email,profile_photo_path,created_at,updated_at) VALUES(?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    foreach ([['Micah','Lago','micah','micah@test.invalid',null],['Domingo','Ancog','domingo','domingo@test.invalid',null],['Brian','Ragasi','brian','brian@test.invalid',null]] as $person) {
        $user->execute([$facultyRole,$person[0],$person[1],$person[2],'test',$person[3],$person[4]]);
        $ids[$person[2]] = (int)$db->lastInsertId();
    }
    $post = $db->prepare("INSERT INTO tbl_posts(user_id,content,status,created_at,updated_at) VALUES(?,?,'approved',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    $post->execute([$ids['micah'],'Hey']); $postId = (int)$db->lastInsertId();
    $post->execute([$ids['micah'],'Another post']); $otherPostId = (int)$db->lastInsertId();
    $repo = new MediaRepository($db);
    $repo->saveComment($ids['domingo'],$postId,'Yow',null);
    $rootId = (int)$db->lastInsertId();
    $repo->saveComment($ids['brian'],$postId,'tabang lord',null,$rootId);
    $replyId = (int)$db->lastInsertId();
    $photo = $db->prepare('UPDATE tbl_users SET profile_photo_path=? WHERE id=?');
    $photo->execute(['assets/uploads/domingo.jpg',$ids['domingo']]);
    $photo->execute(['assets/uploads/brian.jpg',$ids['brian']]);
    $page = $repo->commentsPage($postId);
    $assert(count($page['comments']) === 1 && count($page['comments'][0]['replies']) === 1, 'Reply appears beneath its parent comment');
    $assert($page['comments'][0]['profile_photo_path'] === 'assets/uploads/domingo.jpg', 'Existing comments show a profile photo added later');
    $assert($page['comments'][0]['replies'][0]['id'] === $replyId && $page['comments'][0]['replies'][0]['profile_photo_path'] === 'assets/uploads/brian.jpg', 'Reply includes the author profile photo');
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
} finally {
    $live->exec("DROP DATABASE IF EXISTS `$scratch`");
}
