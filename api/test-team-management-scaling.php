<?php

declare(strict_types=1);

require_once __DIR__.'/teams.php';

function teamScalingAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$database = (new Database())->connection();
$repository = new TeamManagementRepository($database);
$index = $repository->index([]);

teamScalingAssert(!array_key_exists('students', $index), 'Team index must not contain the full student roster.');
foreach ($index['teams'] as $team) {
    teamScalingAssert(count($team['members']) <= 3, 'Team cards may contain at most three preview members.');
}

$yearId = (int) ($database->query('SELECT id FROM tbl_school_years ORDER BY id DESC LIMIT 1')->fetchColumn() ?: 0);
teamScalingAssert($yearId > 0, 'A school year is required for the scaling checks.');
$teamIdStatement = $database->prepare('SELECT id FROM tbl_teams WHERE school_year_id=? ORDER BY id LIMIT 1');
$teamIdStatement->execute([$yearId]);
$teamId = (int) ($teamIdStatement->fetchColumn() ?: 0);

if ($teamId > 0) {
    $selection = $repository->teamSelection($teamId);
    $expectedMembers = $database->prepare("SELECT COUNT(*) FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE tu.team_id=?");
    $expectedMembers->execute([$teamId]);
    teamScalingAssert(count($selection['member_ids']) === (int) $expectedMembers->fetchColumn(), 'Lazy team selection must include every current student member.');

    $first = $repository->studentCandidates(['school_year_id' => $yearId, 'team_id' => $teamId]);
    teamScalingAssert(count($first['students']) <= 24, 'Eligible-student pages may contain at most 24 records.');
    if ($first['pagination']['last_page'] > 1) {
        $second = $repository->studentCandidates(['school_year_id' => $yearId, 'team_id' => $teamId, 'page' => 2]);
        teamScalingAssert(!array_intersect(array_column($first['students'], 'id'), array_column($second['students'], 'id')), 'Eligible-student pages must not overlap.');
    }
}

$unassigned = $repository->unassignedStudents(['school_year_id' => $yearId]);
teamScalingAssert(count($unassigned['students']) <= 25, 'Unassigned-student pages may contain at most 25 records.');
if ($unassigned['pagination']['last_page'] > 1) {
    $nextUnassigned = $repository->unassignedStudents(['school_year_id' => $yearId, 'page' => 2]);
    teamScalingAssert(!array_intersect(array_column($unassigned['students'], 'id'), array_column($nextUnassigned['students'], 'id')), 'Unassigned-student pages must not overlap.');
}

echo "Team management scaling checks passed.\n";
