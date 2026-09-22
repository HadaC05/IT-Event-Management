<?php

declare(strict_types=1);

require_once __DIR__.'/student-portal.php';
require_once __DIR__.'/faculty.php';

$db = (new Database())->connection();
$userId = (int) $db->query(
    "SELECT tu.user_id FROM tbl_team_user tu
     JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1
     JOIN tbl_users u ON u.id=tu.user_id
     JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student'
     ORDER BY tu.id LIMIT 1"
)->fetchColumn();
if ($userId < 1) throw new RuntimeException('An active student team membership is required.');

$studentPage = (new StudentPortalRepository($db))->teamMembers($userId);
if (count($studentPage['members']) > 24) throw new RuntimeException('Student directory exceeded one page.');

// FacultyRepository resolves an active team membership; it does not depend on
// the actor's role. This read-only fixture exercises its DISTINCT event query
// even when no Faculty account has been assigned to a team locally.
$facultyPage = (new FacultyRepository($db))->students($userId);
if (count($facultyPage['students']) > 25) throw new RuntimeException('Faculty directory exceeded one page.');

echo "Team directory MySQL compatibility checks passed.\n";
