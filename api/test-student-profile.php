<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/MediaRepository.php';

$db = (new Database())->connection();
$actor = $db->query("SELECT u.id,u.first_name,u.middle_name,u.last_name,r.name role
    FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id
    WHERE r.name='Student' ORDER BY u.id LIMIT 1")->fetch();

if (!$actor) {
    fwrite(STDERR, "SKIP: no student account is available for the profile regression test.\n");
    exit(0);
}

$actor['full_name'] = trim(implode(' ', array_filter([
    $actor['first_name'],
    $actor['middle_name'],
    $actor['last_name'],
])));
$data = (new MediaRepository($db))->studentProfileData($actor);

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    echo 'PASS: '.$message.PHP_EOL;
};

$assert((int) $data['profile']['id'] === (int) $actor['id'], 'profile data is scoped to the signed-in student');
$assert($data['viewer']['role'] === 'Student', 'profile media permissions use the Student role');
$assert(isset($data['post_counts']['approved'], $data['post_counts']['pending'], $data['post_counts']['rejected']), 'profile post status counts are available');
$assert(array_reduce($data['posts'], static fn (bool $valid, array $post): bool => $valid && (int) $post['user_id'] === (int) $actor['id'], true), 'published timeline contains only the student own posts');

echo "Student profile regression test completed.\n";
