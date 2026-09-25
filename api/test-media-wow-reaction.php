<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/MediaRepository.php';

$live = (new Database())->connection();
$source = (string) $live->query('SELECT DATABASE()')->fetchColumn();
$user = $live->query('SELECT id,role_id FROM tbl_users ORDER BY id LIMIT 1')->fetch();
if (!$user) throw new RuntimeException('A user fixture is required.');
$scratch = 'media_wow_test_'.bin2hex(random_bytes(4));
$live->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    foreach (['tbl_roles', 'tbl_users', 'tbl_posts', 'tbl_post_images', 'tbl_post_audits', 'tbl_post_reactions', 'tbl_post_comments', 'tbl_comment_reactions'] as $table) {
        $live->exec("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`");
    }
    $live->prepare("INSERT INTO `$scratch`.`tbl_roles` SELECT * FROM `$source`.`tbl_roles` WHERE id=?")->execute([(int) $user['role_id']]);
    $live->prepare("INSERT INTO `$scratch`.`tbl_users` SELECT * FROM `$source`.`tbl_users` WHERE id=?")->execute([(int) $user['id']]);
    $db = (new Database(null, $scratch))->connection();
    $repository = new MediaRepository($db);
    $userId = (int) $user['id'];
    $postId = $repository->create(['id' => $userId, 'role' => 'Faculty'], ['content' => 'Reaction test'], []);

    $repository->toggleReaction($userId, $postId, 'wow', true);
    $postReaction = $db->prepare('SELECT type FROM tbl_post_reactions WHERE post_id=? AND user_id=?');
    $postReaction->execute([$postId, $userId]);
    if ($postReaction->fetchColumn() !== 'wow') throw new RuntimeException('Wow post reaction was not stored.');
    $list = $repository->reactionUsers($postId, null, 'wow');
    if (($list['counts']['wow'] ?? 0) != 1 || count($list['reactors'] ?? []) !== 1) throw new RuntimeException('Wow post reaction was not listed.');

    $db->prepare('INSERT INTO tbl_post_comments(post_id,user_id,body,created_at,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([$postId, $userId, 'A comment']);
    $commentId = (int) $db->lastInsertId();
    $repository->toggleCommentReaction($userId, $postId, $commentId, 'wow', true);
    $commentReaction = $db->prepare('SELECT type FROM tbl_comment_reactions WHERE comment_id=? AND user_id=?');
    $commentReaction->execute([$commentId, $userId]);
    if ($commentReaction->fetchColumn() !== 'wow') throw new RuntimeException('Wow comment reaction was not stored.');
    $list = $repository->reactionUsers($postId, $commentId, 'wow');
    if (($list['counts']['wow'] ?? 0) != 1 || count($list['reactors'] ?? []) !== 1) throw new RuntimeException('Wow comment reaction was not listed.');

    echo "PASS: Wow post and comment reactions can be stored and listed\n";
} finally {
    $live->exec("DROP DATABASE `$scratch`");
}
