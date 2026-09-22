<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/MediaRepository.php';

$live = (new Database())->connection();
$source = (string) $live->query('SELECT DATABASE()')->fetchColumn();
$scratch = 'media_feed_test_'.bin2hex(random_bytes(4));
if (!preg_match('/^media_feed_test_[0-9a-f]{8}$/', $scratch)) throw new RuntimeException('Invalid test database name.');
$tables = ['tbl_roles','tbl_user_statuses','tbl_users','tbl_events','tbl_event_attendance_schedules','tbl_event_activities','tbl_teams',
    'tbl_sbo_officer_assignments','tbl_officer_responsibilities','tbl_sbo_event_assignments','tbl_posts','tbl_post_audits','tbl_post_comments','tbl_post_reactions','tbl_notifications'];
$empty = ['tbl_sbo_event_assignments','tbl_posts','tbl_post_audits','tbl_post_comments','tbl_post_reactions','tbl_notifications'];
$assert = static function (bool $ok, string $label): void { if (!$ok) throw new RuntimeException('FAIL: '.$label); echo 'PASS: '.$label.PHP_EOL; };
$expect = static function (callable $action, string $class, string $label) use ($assert): void {
    try { $action(); $assert(false, $label); } catch (Throwable $exception) { $assert($exception instanceof $class, $label); }
};

$live->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    foreach ($tables as $table) {
        $live->exec("CREATE TABLE `$scratch`.`$table` LIKE `$source`.`$table`");
        if (!in_array($table, $empty, true)) $live->exec("INSERT INTO `$scratch`.`$table` SELECT * FROM `$source`.`$table`");
    }
    $db = (new Database(null, $scratch))->connection();
    $actor = static function (PDO $db, string $role): array {
        $statement = $db->prepare('SELECT u.id,u.first_name,u.last_name,r.name role FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id WHERE r.name=? ORDER BY u.id LIMIT 1');
        $statement->execute([$role]);
        $row = $statement->fetch();
        if (!$row) throw new RuntimeException($role.' fixture is unavailable.');
        $row['id'] = (int) $row['id']; $row['full_name'] = trim($row['first_name'].' '.$row['last_name']);
        return $row;
    };
    $student = $actor($db, 'Student'); $faculty = $actor($db, 'Faculty'); $adviser = $actor($db, 'SBO Adviser');
    $officerRow = $db->query("SELECT u.id,u.first_name,u.last_name,r.name role,oa.id assignment_id FROM tbl_sbo_officer_assignments oa JOIN tbl_users u ON u.id=oa.officer_user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='SBO Officer' WHERE oa.status='Active' ORDER BY oa.id LIMIT 1")->fetch();
    if (!$officerRow) throw new RuntimeException('SBO Officer fixture is unavailable.');
    $officer = ['id'=>(int)$officerRow['id'],'first_name'=>$officerRow['first_name'],'last_name'=>$officerRow['last_name'],'full_name'=>trim($officerRow['first_name'].' '.$officerRow['last_name']),'role'=>'SBO Officer'];
    $assignedEvent = (int) $db->query('SELECT e.id FROM tbl_events e JOIN tbl_event_attendance_schedules s ON s.event_id=e.id JOIN tbl_event_activities a ON a.event_id=e.id WHERE e.deleted_at IS NULL ORDER BY e.id LIMIT 1')->fetchColumn();
    $otherStatement = $db->prepare('SELECT id FROM tbl_events WHERE deleted_at IS NULL AND id<>? ORDER BY id LIMIT 1');
    $otherStatement->execute([$assignedEvent]); $otherEvent = (int) $otherStatement->fetchColumn();
    if (!$assignedEvent || !$otherEvent) throw new RuntimeException('Two event fixtures are required.');
    $db->prepare("UPDATE tbl_events SET start_at=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 DAY),end_at=DATE_ADD(CURRENT_TIMESTAMP,INTERVAL 2 DAY),is_featured=0 WHERE id IN (?,?)")->execute([$assignedEvent,$otherEvent]);
    $schedule = (int) $db->query("SELECT id FROM tbl_event_attendance_schedules WHERE event_id=$assignedEvent ORDER BY id LIMIT 1")->fetchColumn();
    $activity = (int) $db->query("SELECT id FROM tbl_event_activities WHERE event_id=$assignedEvent ORDER BY id LIMIT 1")->fetchColumn();
    $team = (int) $db->query('SELECT id FROM tbl_teams ORDER BY id LIMIT 1')->fetchColumn();
    if (!$schedule || !$activity || !$team) throw new RuntimeException('Officer media fixtures are unavailable.');
    $db->prepare("INSERT INTO tbl_sbo_event_assignments(officer_assignment_id,event_schedule_id,session_code,activity_id,team_id,responsibility_id,status,assigned_by,created_at,updated_at) VALUES(?,?,'whole_day',?,?,(SELECT id FROM tbl_officer_responsibilities WHERE code='media'),'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)")->execute([(int)$officerRow['assignment_id'],$schedule,$activity,$team,(int)$adviser['id']]);
    $repo = new MediaRepository($db);
    $assert((new MediaPermissions('Admin'))->canModerate(), 'Admin receives moderation permission');

    $studentPost = $repo->create($student, ['content'=>'Pending student media test','event_id'=>$assignedEvent], []);
    $assert($db->query("SELECT status FROM tbl_posts WHERE id=$studentPost")->fetchColumn() === 'pending', 'Student post defaults to Pending');
    $facultyPost = $repo->create($faculty, ['content'=>'Published faculty media test','event_id'=>$assignedEvent], []);
    $assert($db->query("SELECT status FROM tbl_posts WHERE id=$facultyPost")->fetchColumn() === 'approved', 'Faculty post publishes automatically');
    $fixtureImage = 'assets/uploads/posts/media-edit-smoke-'.bin2hex(random_bytes(8)).'.png';
    $db->prepare('UPDATE tbl_posts SET image_path=? WHERE id=?')->execute([$fixtureImage, $facultyPost]);
    $repo->update($faculty, ['id'=>$facultyPost,'content'=>'Edited faculty text','event_id'=>$assignedEvent], []);
    $assert($db->query("SELECT image_path FROM tbl_posts WHERE id=$facultyPost")->fetchColumn() === $fixtureImage, 'Text-only edit keeps the existing photo');
    $repo->update($faculty, ['id'=>$facultyPost,'content'=>'Edited faculty text','event_id'=>$assignedEvent,'remove_media'=>'1'], []);
    $assert($db->query("SELECT image_path FROM tbl_posts WHERE id=$facultyPost")->fetchColumn() === null, 'Media is removed only when explicitly requested');
    $officerPost = $repo->create($officer, ['content'=>'Published officer media test','event_id'=>$assignedEvent], []);
    $assert($db->query("SELECT status FROM tbl_posts WHERE id=$officerPost")->fetchColumn() === 'approved', 'Assigned Officer post publishes automatically');
    $expect(fn()=>$repo->create($officer,['content'=>'Unauthorized event post','event_id'=>$otherEvent],[]), MediaForbiddenException::class, 'Officer cannot post to an unassigned event');
    $expect(fn()=>$repo->update($faculty,['id'=>$studentPost,'content'=>'Cross-user edit'],[]), MediaForbiddenException::class, 'User cannot edit another user’s post');
    $expect(fn()=>$repo->review($officer,$studentPost,'approved',''), MediaForbiddenException::class, 'Officer cannot approve a student post');
    $expect(fn()=>$repo->hide($faculty,$officerPost,'Not allowed'), MediaForbiddenException::class, 'Faculty cannot hide another public post');
    $expect(fn()=>$repo->updateCarousel($officer,['event_id'=>$otherEvent,'is_featured'=>'0'],[]), MediaForbiddenException::class, 'Officer cannot manage an unassigned event carousel');
    $expect(fn()=>$repo->toggleReaction((int)$faculty['id'],$officerPost,'support'), InvalidArgumentException::class, 'Removed reaction types are rejected');
    $repo->toggleReaction((int)$faculty['id'],$officerPost,'like');
    $assert($db->query("SELECT type FROM tbl_post_reactions WHERE post_id=$officerPost AND user_id=".(int)$faculty['id'])->fetchColumn() === 'like', 'The heart Like reaction is recorded');
    $expect(fn()=>$repo->review($adviser,$studentPost,'rejected',''), InvalidArgumentException::class, 'Rejection requires a reason');
    $repo->review($adviser,$studentPost,'approved','');
    $assert($db->query("SELECT status FROM tbl_posts WHERE id=$studentPost")->fetchColumn() === 'approved', 'Adviser can approve a pending student post');
    $deletableStudentPost = $repo->create($student, ['content'=>'Approved post deletion test','event_id'=>$assignedEvent], []);
    $repo->review($adviser,$deletableStudentPost,'approved','');
    $repo->delete($student,$deletableStudentPost);
    $assert($db->query("SELECT deleted_at IS NOT NULL FROM tbl_posts WHERE id=$deletableStudentPost")->fetchColumn() == 1, 'Student can soft-delete their own approved post');
    $expect(fn()=>$repo->delete($faculty,$officerPost), MediaForbiddenException::class, 'User cannot delete another user’s post');
    $repo->update($student,['id'=>$studentPost,'content'=>'Edited approved student post','event_id'=>$assignedEvent],[]);
    $assert($db->query("SELECT status FROM tbl_posts WHERE id=$studentPost")->fetchColumn() === 'pending', 'Editing an approved student post returns it to Pending');
    $db->prepare('UPDATE tbl_events SET end_at=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 1 DAY) WHERE id=?')->execute([$assignedEvent]);
    $page = $repo->pageData($faculty);
    $assert((bool) array_filter($page['posts'], fn(array $post): bool => $post['id'] === $facultyPost), 'Completed-event posts remain in the mixed feed');
    $assert(!(bool) array_filter($page['active_events'], fn(array $event): bool => $event['id'] === $assignedEvent), 'Completed events leave active filter buttons');
    $assert(count($page['active_events']) <= 4, 'Active event filters are limited to four buttons');
    $assert(!(bool) array_filter($page['own_posts'], fn(array $post): bool => $post['status'] === 'approved'), 'Approved posts are excluded from the personal status area');
    $studentPage = $repo->pageData($student);
    $assert((bool) array_filter($studentPage['own_posts'], fn(array $post): bool => $post['status'] === 'pending'), 'Pending student posts remain in the personal status area');
    $assert(count($page['featured_events']) > 0, 'The feed carousel falls back to available events');
    $assert(array_key_exists('event_program', $page), 'The feed provides active event activities for the sidebar');
    $expect(fn()=>$repo->moderationPage($faculty), MediaForbiddenException::class, 'Faculty cannot access the moderation queue');
    $insertPending = $db->prepare("INSERT INTO tbl_posts(user_id,event_id,category,content,status,is_official,created_at,updated_at) VALUES(?,?,'general',?,'pending',0,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    for ($number = 1; $number <= 105; $number++) $insertPending->execute([(int)$student['id'],$otherEvent,'Queue pagination test '.$number]);
    $firstQueue = $repo->moderationPage($adviser, 1);
    $lastQueue = $repo->moderationPage($adviser, 999);
    $assert($firstQueue['total'] > 100 && count($firstQueue['posts']) === 20, 'Moderation returns a bounded first page beyond 100 pending posts');
    $assert($lastQueue['page'] === $lastQueue['page_count'] && count($lastQueue['posts']) < 20, 'Out-of-range moderation pages clamp to the final page');
    $assert(count(array_intersect(array_column($firstQueue['posts'], 'id'), array_column($lastQueue['posts'], 'id'))) === 0, 'Moderation pages do not duplicate posts');
    $assert(!array_key_exists('moderation', $repo->pageData($adviser)), 'Feed response no longer embeds the moderation queue');
    $repo->hide($adviser,$facultyPost,'Moderation test');
    $hiddenPage = $repo->pageData($faculty);
    $assert(!(bool) array_filter($hiddenPage['posts'], fn(array $post): bool => $post['id'] === $facultyPost), 'Hidden posts do not appear publicly');
} finally {
    $live->exec("DROP DATABASE IF EXISTS `$scratch`");
}
