<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/MediaRepository.php';

$live = (new Database())->connection();
$source = (string) $live->query('SELECT DATABASE()')->fetchColumn();
$scratch = 'media_photo_test_'.bin2hex(random_bytes(4));
$live->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    foreach (['tbl_posts', 'tbl_post_images', 'tbl_post_audits'] as $table) {
        $live->exec("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`");
    }
    $db = (new Database(null, $scratch))->connection();
    $repository = new MediaRepository($db);
    $actor = ['id' => 1, 'role' => 'Faculty'];
    $postId = $repository->create($actor, ['content' => 'Two photo post'], []);
    $first = 'assets/uploads/posts/test-first-'.bin2hex(random_bytes(8)).'.png';
    $second = 'assets/uploads/posts/test-second-'.bin2hex(random_bytes(8)).'.png';
    $db->prepare('UPDATE tbl_posts SET image_path=? WHERE id=?')->execute([$first, $postId]);
    $insert = $db->prepare('INSERT INTO tbl_post_images(post_id,image_path,sort_order) VALUES(?,?,?)');
    $insert->execute([$postId, $first, 0]);
    $insert->execute([$postId, $second, 1]);

    $repository->update($actor, ['id' => $postId, 'content' => '', 'remove_image_paths' => json_encode([$first])], []);
    $paths = $db->query("SELECT image_path FROM tbl_post_images WHERE post_id=$postId ORDER BY sort_order")->fetchAll(PDO::FETCH_COLUMN);
    if ($paths !== [$second] || $db->query("SELECT image_path FROM tbl_posts WHERE id=$postId")->fetchColumn() !== $second) {
        throw new RuntimeException('Removing one photo did not keep the other photo.');
    }

    try {
        $repository->update($actor, ['id' => $postId, 'content' => 'Invalid removal', 'remove_image_paths' => json_encode([$first])], []);
        throw new RuntimeException('An already removed photo was accepted.');
    } catch (InvalidArgumentException $expected) {
        if (!str_contains($expected->getMessage(), 'no longer part')) throw $expected;
    }

    try {
        $repository->update($actor, ['id' => $postId, 'content' => '', 'remove_image_paths' => json_encode([$second])], []);
        throw new RuntimeException('An empty post was accepted.');
    } catch (InvalidArgumentException $expected) {
        if (!str_contains($expected->getMessage(), 'Write a post')) throw $expected;
    }
    $repository->update($actor, ['id' => $postId, 'content' => 'No photos', 'remove_image_paths' => json_encode([$second])], []);
    if ($db->query("SELECT COUNT(*) FROM tbl_post_images WHERE post_id=$postId")->fetchColumn() != 0 ||
        $db->query("SELECT image_path FROM tbl_posts WHERE id=$postId")->fetchColumn() !== null) {
        throw new RuntimeException('Removing the last photo did not clear the post media.');
    }
    echo "PASS: individual photo removal, photo-only post, invalid removal, and empty-post validation\n";
} finally {
    $live->exec("DROP DATABASE `$scratch`");
}
