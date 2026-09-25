<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/MediaRepository.php';

$live = (new Database())->connection();
$source = (string) $live->query('SELECT DATABASE()')->fetchColumn();
$scratch = 'media_notice_test_'.bin2hex(random_bytes(4));
$live->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    foreach (['tbl_posts','tbl_notifications'] as $table) $live->exec("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`");
    $db = (new Database(null, $scratch))->connection();
    $db->prepare("INSERT INTO tbl_posts(user_id,category,content,status,created_at,updated_at) VALUES(1,'general',?,'approved',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute(['Hackathon recap is now live.']);
    $postId = (int) $db->lastInsertId();
    $id = '00000000-0000-4000-8000-000000000001';
    $db->prepare('INSERT INTO tbl_notifications(id,type,notifiable_type,notifiable_id,data,created_at,updated_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')->execute([
        $id, 'PostReviewed', 'App\\Models\\User', 1,
        json_encode(['post_id'=>$postId,'message'=>'Your post was approved.'], JSON_THROW_ON_ERROR),
    ]);
    $repository = new MediaRepository($db);
    $before = $repository->notificationData(1);
    if ($before['unread_notifications'] !== 1 || $before['notifications'][0]['title'] !== 'Your post was approved' ||
        $before['notifications'][0]['detail'] !== 'Hackathon recap is now live.') {
        throw new RuntimeException('The unread notification card has the wrong content.');
    }
    $repository->markNotificationsRead(1);
    $after = $repository->notificationData(1);
    if ($after['unread_notifications'] !== 0 || count($after['notifications']) !== 1 || !$after['notifications'][0]['is_read']) {
        throw new RuntimeException('Viewing notifications did not clear the count while retaining the item.');
    }
    echo "PASS: notification title, post preview, unread count, and retained viewed item\n";
} finally {
    $live->exec("DROP DATABASE `$scratch`");
}
