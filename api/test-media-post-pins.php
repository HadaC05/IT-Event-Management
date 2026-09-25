<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/MediaRepository.php';

$live = (new Database())->connection();
$source = (string) $live->query('SELECT DATABASE()')->fetchColumn();
$user = $live->query('SELECT id,role_id FROM tbl_users ORDER BY id LIMIT 1')->fetch();
if (!$user) throw new RuntimeException('A user fixture is required.');
$scratch = 'media_pin_test_'.bin2hex(random_bytes(4));
$live->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    foreach (['tbl_roles','tbl_users','tbl_events','tbl_posts','tbl_post_images','tbl_post_audits','tbl_post_reactions','tbl_post_comments'] as $table) {
        $live->exec("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`");
    }
    $live->prepare("INSERT INTO `$scratch`.`tbl_roles` SELECT * FROM `$source`.`tbl_roles` WHERE id=?")->execute([(int) $user['role_id']]);
    $live->prepare("INSERT INTO `$scratch`.`tbl_users` SELECT * FROM `$source`.`tbl_users` WHERE id=?")->execute([(int) $user['id']]);
    $db = (new Database(null, $scratch))->connection();
    $repository = new MediaRepository($db);
    $author = ['id' => (int) $user['id'], 'role' => 'Faculty'];
    $adviser = ['id' => (int) $user['id'], 'role' => 'SBO Adviser'];
    $ids = [];
    for ($i = 1; $i <= 22; $i++) $ids[] = $repository->create($author, ['content' => "Post $i"], []);
    $repository->pinPost($adviser, $ids[0], true);

    $first = $repository->postsPage($author);
    if (count($first['posts']) !== 20 || $first['posts'][0]['id'] !== $ids[0] || !$first['posts'][0]['is_pinned'] || !$first['next_cursor']) {
        throw new RuntimeException('Pinned post did not appear first on page one.');
    }
    $second = $repository->postsPage($author, null, $first['next_cursor']);
    $allIds = array_merge(array_column($first['posts'], 'id'), array_column($second['posts'], 'id'));
    if (count($allIds) !== 22 || count(array_unique($allIds)) !== 22 || $second['next_cursor'] !== null) {
        throw new RuntimeException('Pinned feed pagination skipped or repeated posts.');
    }

    for ($i = 1; $i <= 20; $i++) $repository->pinPost($adviser, $ids[$i], true);
    $first = $repository->postsPage($author);
    $second = $repository->postsPage($author, null, $first['next_cursor']);
    $allIds = array_merge(array_column($first['posts'], 'id'), array_column($second['posts'], 'id'));
    if (count($allIds) !== 22 || count(array_unique($allIds)) !== 22 ||
        count(array_filter($first['posts'], static fn ($post) => !$post['is_pinned'])) !== 0 ||
        count(array_filter($second['posts'], static fn ($post) => $post['is_pinned'])) !== 1) {
        throw new RuntimeException('Pagination across pinned and regular posts failed.');
    }

    try {
        $repository->pinPost(['id' => (int) $user['id'], 'role' => 'Student'], $ids[1], true);
        throw new RuntimeException('A non-adviser could pin a post.');
    } catch (MediaForbiddenException $expected) {}

    for ($i = 1; $i <= 20; $i++) $repository->pinPost($adviser, $ids[$i], false);
    $repository->pinPost($adviser, $ids[0], false);
    $unpinned = $repository->postsPage($author);
    if ($unpinned['posts'][0]['id'] !== $ids[21] || $unpinned['posts'][0]['is_pinned']) {
        throw new RuntimeException('Unpinned post stayed at the top.');
    }
    echo "PASS: adviser-only pins, pinned-first ordering, pagination, and unpinning\n";
} finally {
    $live->exec("DROP DATABASE `$scratch`");
}
