<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/SboAuthorization.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('CREATE TABLE tbl_events (id INTEGER PRIMARY KEY, academic_period_id INTEGER)');
$db->exec('CREATE TABLE tbl_academic_periods (id INTEGER PRIMARY KEY, school_year_id INTEGER)');
$db->exec('CREATE TABLE tbl_teams (id INTEGER PRIMARY KEY, school_year_id INTEGER, name TEXT, is_active INTEGER)');
$db->exec('CREATE TABLE tbl_team_user (team_id INTEGER, user_id INTEGER)');
$db->exec('CREATE TABLE tbl_event_membership_snapshots (event_id INTEGER, user_id INTEGER, team_id INTEGER)');
$db->exec("INSERT INTO tbl_academic_periods VALUES (1,7);
    INSERT INTO tbl_events VALUES (1,1);
    INSERT INTO tbl_teams VALUES (10,7,'Old Tribe',1),(20,7,'New Tribe',1),(30,8,'Previous School Year Tribe',1);
    INSERT INTO tbl_team_user VALUES (20,42),(30,42);
    INSERT INTO tbl_event_membership_snapshots VALUES (1,42,10)");

$auth = new SboAuthorization($db);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$assignment = ['event_id' => 1];
$current = $auth->currentTeamForEvent(1,42);
$assert($current && (int)$current['id'] === 20 && $current['name'] === 'New Tribe',
    'Scanner must use the new tribe from the event school year.');
$assert($auth->studentIsEligible($assignment,42),
    'The old event snapshot must still establish student eligibility after the move.');
$snapshot = $db->query('SELECT team_id FROM tbl_event_membership_snapshots WHERE event_id=1 AND user_id=42')->fetchColumn();
$assert((int)$snapshot === 10, 'Historical event snapshot must remain unchanged.');

$db->exec('DELETE FROM tbl_team_user WHERE team_id=20 AND user_id=42');
$assert($auth->currentTeamForEvent(1,42) === false,
    'A tribe from another school year must not be used for this event.');
$db->exec('INSERT INTO tbl_team_user VALUES (20,42)');
$db->exec('UPDATE tbl_teams SET is_active=0 WHERE id=20');
$assert($auth->currentTeamForEvent(1,42) === false,
    'An inactive tribe must not be shown as the current tribe.');
$assert(!$auth->studentIsEligible($assignment,43),
    'A student missing from the event snapshot must remain ineligible.');

echo "Current tribe scanner checks passed.\n";
