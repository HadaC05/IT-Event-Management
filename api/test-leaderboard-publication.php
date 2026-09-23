<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__.'/LeaderboardPublication.php';
require_once __DIR__.'/student-portal.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE tbl_leaderboard_publication (id INTEGER PRIMARY KEY, student_visible INTEGER NOT NULL, updated_by INTEGER, updated_at TEXT)');
$db->exec('CREATE TABLE tbl_activity_logs (actor_id INTEGER, action TEXT, description TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('INSERT INTO tbl_leaderboard_publication (id,student_visible) VALUES (1,0)');

$setting = new LeaderboardPublication($db);
if ($setting->studentVisible()) throw new RuntimeException('Student standings must start hidden.');
$hidden = (new StudentPortalRepository($db))->leaderboard(1);
if ($hidden !== ['visible' => false, 'event' => null, 'categories' => [], 'teams' => []])
    throw new RuntimeException('Hidden student leaderboard returned score data.');

if (!$setting->setStudentVisible(true, 7) || !$setting->studentVisible())
    throw new RuntimeException('The Adviser could not reveal the leaderboard.');
$setting->setStudentVisible(true, 7);
if ((int) $db->query('SELECT COUNT(*) FROM tbl_activity_logs')->fetchColumn() !== 1)
    throw new RuntimeException('An unchanged setting created another audit entry.');

$db->sqliteCreateFunction('CONCAT', static fn (...$parts): string => implode('', $parts));
foreach ([
    'CREATE TABLE tbl_events (id INTEGER PRIMARY KEY,title TEXT,start_at TEXT,end_at TEXT,deleted_at TEXT)',
    'CREATE TABLE tbl_event_user (event_id INTEGER,user_id INTEGER)',
    'CREATE TABLE tbl_event_membership_snapshots (event_id INTEGER,user_id INTEGER)',
    'CREATE TABLE tbl_score_categories (id INTEGER,event_id INTEGER,name TEXT,sort_order INTEGER)',
    'CREATE TABLE tbl_event_activities (id INTEGER,event_id INTEGER,name TEXT)',
    'CREATE TABLE tbl_activity_score_results (event_id INTEGER,activity_id INTEGER,team_id INTEGER,overall_points INTEGER,finalized_at TEXT)',
    'CREATE TABLE vw_finalized_scores (event_id INTEGER,team_id INTEGER,score_category_id INTEGER,points INTEGER,updated_at TEXT)',
    'CREATE TABLE tbl_teams (id INTEGER,name TEXT,color TEXT,is_active INTEGER)',
    'CREATE TABLE tbl_team_user (team_id INTEGER,user_id INTEGER)',
] as $sql) $db->exec($sql);
$db->exec("INSERT INTO tbl_events VALUES (11,'Finished event','2026-09-20','2026-09-21',NULL)");
$db->exec('INSERT INTO tbl_event_user VALUES (11,1)');
$db->exec("INSERT INTO tbl_score_categories VALUES (2,11,'Legacy criterion',1)");
$db->exec("INSERT INTO tbl_event_activities VALUES (3,11,'Competition')");
$db->exec("INSERT INTO tbl_teams VALUES (1,'Green','#397565',1),(2,'Blue','#123456',1)");
$db->exec('INSERT INTO tbl_team_user VALUES (1,1),(2,2)');
$db->exec("INSERT INTO vw_finalized_scores VALUES (11,1,2,5,'2026-09-21'),(11,2,2,4,'2026-09-21')");
$db->exec("INSERT INTO tbl_activity_score_results VALUES (11,3,1,10,'2026-09-21'),(11,3,2,8,'2026-09-21')");

$revealed = (new StudentPortalRepository($db))->leaderboard(1);
if (!$revealed['visible'] || (int) $revealed['event']['id'] !== 11 || count($revealed['categories']) !== 2)
    throw new RuntimeException('A finished event with both scoring types was not revealed.');
if ($revealed['teams'][0]['total_score'] !== 15.0 || $revealed['teams'][1]['total_score'] !== 12.0)
    throw new RuntimeException('Student totals did not combine legacy and competition points.');
if ($revealed['teams'][0]['category_scores']['activity-3'] !== 10.0)
    throw new RuntimeException('The competition score is missing from student standings.');

if ($setting->setStudentVisible(false, 7) || $setting->studentVisible())
    throw new RuntimeException('The Adviser could not hide the leaderboard again.');
if ((int) $db->query('SELECT COUNT(*) FROM tbl_activity_logs')->fetchColumn() !== 2)
    throw new RuntimeException('Visibility changes were not audited.');
if ((new StudentPortalRepository($db))->leaderboard(1)['visible'])
    throw new RuntimeException('Student scores remained visible after hiding the leaderboard.');

echo "Leaderboard publication checks passed.\n";
