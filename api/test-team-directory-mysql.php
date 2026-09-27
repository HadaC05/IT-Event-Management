<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

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
$directory = new StudentPortalRepository($db);
$alphabetical = $directory->teamMembers($userId, ['sort' => 'name']);
if (array_column($studentPage['members'], 'id') !== array_column($alphabetical['members'], 'id')) {
    throw new RuntimeException('The default directory order must be name A–Z.');
}
if ($studentPage['year_levels']) {
    $year = $studentPage['year_levels'][0];
    $filtered = $directory->teamMembers($userId, ['year_level_id' => $year['id']]);
    foreach ($filtered['members'] as $member) {
        if ($member['year_level'] !== $year['label']) throw new RuntimeException('Year-level filter returned a teammate from another year.');
    }
}
$directory->teamMembers($userId, ['sort' => 'year']);
try {
    $directory->teamMembers($userId, ['sort' => 'unknown']);
    throw new RuntimeException('Invalid member sort order was accepted.');
} catch (InvalidArgumentException $expected) {
}

// FacultyRepository resolves an active team membership; it does not depend on
// the actor's role. This read-only fixture exercises its DISTINCT event query
// even when no Faculty account has been assigned to a team locally.
$facultyPage = (new FacultyRepository($db))->students($userId);
if (count($facultyPage['students']) > 25) throw new RuntimeException('Faculty directory exceeded one page.');

echo "Team directory MySQL compatibility checks passed.\n";
