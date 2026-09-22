<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/MediaRepository.php';
require_once __DIR__.'/student-home.php';

$live = (new Database())->connection();
$source = (string) $live->query('SELECT DATABASE()')->fetchColumn();
$scratch = 'media_review_test_'.bin2hex(random_bytes(4));
if (!preg_match('/^media_review_test_[0-9a-f]{8}$/', $scratch)) throw new RuntimeException('Invalid scratch database name.');
$assert = static function (bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'PASS: '.$label.PHP_EOL;
};

$live->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    foreach (['tbl_roles','tbl_users','tbl_year_levels','tbl_teams','tbl_team_user','tbl_events','tbl_event_types','tbl_event_user',
        'tbl_event_membership_snapshots','tbl_posts','tbl_post_reactions','tbl_post_comments','tbl_notifications'] as $table) {
        $live->exec("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`");
    }
    $live->exec("INSERT INTO `$scratch`.`tbl_roles` SELECT * FROM `$source`.`tbl_roles`");
    $live->exec("INSERT INTO `$scratch`.`tbl_users` SELECT u.* FROM `$source`.`tbl_users` u JOIN `$source`.`tbl_roles` r ON r.id=u.role_id WHERE r.name IN ('Student','SBO Adviser') ORDER BY u.id LIMIT 2");
    $db = (new Database(null, $scratch))->connection();
    $users = $db->query("SELECT u.id,r.name role FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id")->fetchAll();
    $ids = array_column($users, 'id', 'role');
    if (empty($ids['Student']) || empty($ids['SBO Adviser'])) {
        $db->exec('DELETE FROM tbl_users');
        $live->exec("INSERT INTO `$scratch`.`tbl_users` SELECT u.* FROM `$source`.`tbl_users` u JOIN `$source`.`tbl_roles` r ON r.id=u.role_id WHERE u.id IN ((SELECT MIN(us.id) FROM `$source`.`tbl_users` us JOIN `$source`.`tbl_roles` rs ON rs.id=us.role_id WHERE rs.name='Student'),(SELECT MIN(ua.id) FROM `$source`.`tbl_users` ua JOIN `$source`.`tbl_roles` ra ON ra.id=ua.role_id WHERE ra.name='SBO Adviser'))");
        $users = $db->query("SELECT u.id,r.name role FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id")->fetchAll();
        $ids = array_column($users, 'id', 'role');
    }
    if (empty($ids['Student']) || empty($ids['SBO Adviser'])) throw new RuntimeException('Student and Adviser fixtures are required.');
    $insert = $db->prepare("INSERT INTO tbl_posts(user_id,event_id,category,content,status,is_official,created_at,updated_at) VALUES(?,NULL,'general',?, ?,0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    for ($number = 1; $number <= 106; $number++) $insert->execute([(int)$ids['Student'],'Pending '.$number,'pending']);
    $insert->execute([(int)$ids['Student'],'Rejected example','rejected']);
    $insert->execute([(int)$ids['Student'],'Approved example','approved']);
    $approvedId = (int) $db->lastInsertId();
    $db->prepare('INSERT INTO tbl_post_comments(post_id,user_id,body,created_at,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)')
        ->execute([$approvedId,(int)$ids['Student'],'Existing comment']);
    $commentId = (int) $db->lastInsertId();
    $repo = new MediaRepository($db);
    $adviser = ['id'=>(int)$ids['SBO Adviser'],'role'=>'SBO Adviser'];
    $student = ['id'=>(int)$ids['Student'],'role'=>'Student'];
    $first = $repo->moderationPage($adviser, 1);
    $last = $repo->moderationPage($adviser, 999);
    $assert($first['total'] === 106 && count($first['posts']) === 20, 'First page is limited to 20 pending posts');
    $assert($last['page'] === 6 && count($last['posts']) === 6, 'All pending posts beyond the old 100-post cap remain reachable');
    $assert($first['counts']['rejected'] === 1 && !in_array('rejected', array_column($first['posts'], 'status'), true), 'Rejected posts are counted but not sent in pending results');
    $feed = $repo->pageData($adviser);
    $assert(!array_key_exists('moderation', $feed), 'Feed response excludes the review queue');
    $assert(count($feed['posts']) === 1 && $feed['posts'][0]['comments_count'] === 1 && !array_key_exists('comments', $feed['posts'][0]), 'Approved feed returns comment counts without eager comments');
    $studentHome = (new StudentHomeRepository($db))->pageData((int)$ids['Student']);
    $assert(count($studentHome['posts']) === 1 && $studentHome['posts'][0]['comments_count'] === 1 && !array_key_exists('comments',$studentHome['posts'][0]), 'Legacy student home response also defers comments');
    $studentProfile = $repo->studentProfileData($student);
    $assert(count($studentProfile['posts']) === 1 && !array_key_exists('comments', $studentProfile['posts'][0]), 'Student profile defers comments too');
    $assert(count($repo->commentsPage($approvedId)['comments']) === 1, 'Comments load only from their dedicated request');
    $repo->pinComment((int)$ids['Student'], $approvedId, $commentId, true);
    $assert((int)$db->query('SELECT is_pinned FROM tbl_post_comments WHERE id='.$commentId)->fetchColumn() === 1, 'Existing comments can be pinned after migration');
    $addComment = $db->prepare('INSERT INTO tbl_post_comments(post_id,user_id,body,created_at,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
    for ($number=1;$number<=24;$number++) $addComment->execute([$approvedId,(int)$ids['Student'],'Comment '.$number]);
    $commentsFirst = $repo->commentsPage($approvedId);
    $commentsLast = $repo->commentsPage($approvedId,$commentsFirst['next_cursor']);
    $assert(count($commentsFirst['comments']) === 20 && count($commentsLast['comments']) === 5 && $commentsLast['next_cursor'] === null, 'Busy posts load comments in bounded pages');
    $assert($commentsFirst['comments'][0]['id'] === $commentId && $commentsFirst['comments'][0]['is_pinned'], 'Pinned comments remain first');
    for ($number=1;$number<=104;$number++) $insert->execute([(int)$ids['Student'],'Older approved '.$number,'approved']);
    $cursor = null;$seen=[];
    do {
        $page = $repo->postsPage($adviser,null,$cursor);
        $seen = array_merge($seen,array_column($page['posts'],'id'));
        $cursor = $page['next_cursor'];
        $assert(count($page['posts']) <= 20, 'Approved feed page is bounded to 20 posts');
    } while ($cursor !== null);
    $assert(count($seen) === 105 && count(array_unique($seen)) === 105 && in_array($approvedId,$seen,true), 'Every approved post beyond the former 100-post cap remains reachable once');
    $ownPage=$repo->postsPage($student,null,null,true);
    $assert(count($ownPage['posts']) === 20 && $ownPage['next_cursor'] !== null, 'Student profile can page through their approved posts');
    try {$repo->postsPage($adviser,null,'invalid!');$assert(false,'Malformed feed cursors are rejected');}
    catch (InvalidArgumentException) {$assert(true,'Malformed feed cursors are rejected');}
    $notificationData = (new StudentHomeRepository($db))->notificationData((int)$ids['Student']);
    $assert(array_keys($notificationData) === ['notifications','unread_notifications'], 'Notification request returns only notification data');
    try { $repo->moderationPage($student); $assert(false, 'Student cannot read review queue'); }
    catch (MediaForbiddenException) { $assert(true, 'Student cannot read review queue'); }
} finally {
    $live->exec("DROP DATABASE IF EXISTS `$scratch`");
}
