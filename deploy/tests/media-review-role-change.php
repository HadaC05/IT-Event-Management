<?php

declare(strict_types=1);

require_once __DIR__.'/../../api/MediaRepository.php';

// SQLite keeps this regression isolated from real account and post data.
final class ReviewTestDatabase extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(str_replace(' FOR UPDATE', '', $query), $options);
    }
}

$db = new ReviewTestDatabase('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE tbl_roles (id INTEGER PRIMARY KEY, name TEXT)');
$db->exec('CREATE TABLE tbl_user_statuses (id INTEGER PRIMARY KEY, label TEXT)');
$db->exec('CREATE TABLE tbl_users (id INTEGER PRIMARY KEY, role_id INTEGER, status INTEGER, first_name TEXT, middle_name TEXT, last_name TEXT, profile_photo_path TEXT)');
$db->exec('CREATE TABLE tbl_events (id INTEGER PRIMARY KEY, title TEXT)');
$db->exec('CREATE TABLE tbl_posts (id INTEGER PRIMARY KEY, user_id INTEGER, event_id INTEGER, content TEXT, image_path TEXT, video_path TEXT, status TEXT, rejection_reason TEXT, reviewed_by INTEGER, reviewed_at TEXT, created_at TEXT, updated_at TEXT, deleted_at TEXT)');
$db->exec('CREATE TABLE tbl_post_audits (post_id INTEGER, actor_id INTEGER, action TEXT, from_status TEXT, to_status TEXT, notes TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE tbl_notifications (id TEXT, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, created_at TEXT, updated_at TEXT)');
$db->exec("INSERT INTO tbl_roles(id,name) VALUES(1,'Student'),(2,'Faculty'),(3,'SBO Adviser')");
$db->exec("INSERT INTO tbl_user_statuses(id,label) VALUES(1,'active')");
$db->exec("INSERT INTO tbl_users(id,role_id,status,first_name,last_name) VALUES(1,2,1,'Former','Student'),(2,3,1,'SBO','Adviser'),(3,1,1,'Current','Student'),(4,2,1,'Current','Faculty')");
$db->exec("INSERT INTO tbl_posts(id,user_id,content,status,created_at,updated_at) VALUES(10,1,'Waiting after student edit','pending','2026-09-23 08:00:00','2026-09-23 08:00:00')");
$db->exec("INSERT INTO tbl_posts(id,user_id,content,status,reviewed_by,reviewed_at,created_at,updated_at) VALUES(11,4,'Faculty original','approved',4,'2026-09-22 08:00:00','2026-09-22 08:00:00','2026-09-22 08:00:00'),(12,3,'Student original','approved',2,'2026-09-22 09:00:00','2026-09-22 07:00:00','2026-09-22 09:00:00')");

$repository = new MediaRepository($db);
$moderator = ['id' => 2, 'role' => 'SBO Adviser'];
$promotedAuthor = ['id' => 1, 'role' => 'SBO Adviser'];
$queue = $repository->moderationPage($moderator);
if ($queue['total'] !== 1 || $queue['posts'][0]['id'] !== 10) {
    throw new RuntimeException('A pending post must stay in the review queue after its author changes roles.');
}
try {
    $repository->review($promotedAuthor, 10, 'approved', '');
    throw new RuntimeException('An author must not be able to approve their own pending post.');
} catch (MediaForbiddenException $expected) {
    // The self-review guard must be enforced on the server.
}
$repository->review($moderator, 10, 'approved', '');
if ($repository->moderationPage($moderator)['total'] !== 0) {
    throw new RuntimeException('A reviewed post must leave the pending queue.');
}
if ($db->query('SELECT status FROM tbl_posts WHERE id=10')->fetchColumn() !== 'approved') {
    throw new RuntimeException('The moderator decision was not saved.');
}
$repository->update(['id' => 4, 'role' => 'Faculty'], ['id' => 11, 'content' => 'Faculty edited'], []);
$facultyPost = $db->query('SELECT status,reviewed_at FROM tbl_posts WHERE id=11')->fetch();
if ($facultyPost['status'] !== 'approved' || $facultyPost['reviewed_at'] !== '2026-09-22 08:00:00') {
    throw new RuntimeException('A Faculty edit must not move the published post to the top of the feed.');
}
$repository->update(['id' => 3, 'role' => 'Student'], ['id' => 12, 'content' => 'Student edited'], []);
$studentPost = $db->query('SELECT status,reviewed_by,reviewed_at FROM tbl_posts WHERE id=12')->fetch();
if ($studentPost['status'] !== 'pending' || $studentPost['reviewed_by'] !== null || $studentPost['reviewed_at'] !== null) {
    throw new RuntimeException('An approved Student post edit must lose approval and return to Pending Review.');
}
if ($repository->moderationPage($moderator)['posts'][0]['id'] !== 12) {
    throw new RuntimeException('The Student edit must appear in the moderator queue.');
}
if ((new MediaPermissions('Student'))->automaticStatus() !== 'pending' || (new MediaPermissions('Faculty'))->automaticStatus() !== 'approved') {
    throw new RuntimeException('The Student-only review policy changed unexpectedly.');
}
echo "PASS: Student edits return to review; Faculty edits keep feed order; pending posts survive role changes; self-review is blocked\n";
