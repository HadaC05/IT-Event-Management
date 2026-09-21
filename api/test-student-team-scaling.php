<?php

declare(strict_types=1);

require_once __DIR__.'/student-portal.php';

function studentTeamScalingAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$database=(new Database())->connection();
$fixture=$database->query("SELECT tu.user_id,tu.team_id FROM tbl_team_user tu
    JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student'
    JOIN tbl_teams t ON t.id=tu.team_id AND t.is_active=1
    ORDER BY (SELECT COUNT(*) FROM tbl_team_user members WHERE members.team_id=tu.team_id) DESC,tu.user_id LIMIT 1")->fetch();
studentTeamScalingAssert((bool)$fixture,'An active student team membership is required for the scaling test.');
$userId=(int)$fixture['user_id'];$teamId=(int)$fixture['team_id'];$repository=new StudentPortalRepository($database);
$summary=$repository->team($userId)['team'];
studentTeamScalingAssert(!array_key_exists('members',$summary),'Student team summary must not contain the full team roster.');
studentTeamScalingAssert((int)$summary['current_member']['id']===$userId,'Student team summary must identify the signed-in member separately.');
studentTeamScalingAssert(strlen(json_encode($summary))<20000,'Student team summary must remain below 20 KB.');

$expected=$database->prepare("SELECT COUNT(*) FROM tbl_team_user tu JOIN tbl_users u ON u.id=tu.user_id JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student' WHERE tu.team_id=? AND u.id<>?");$expected->execute([$teamId,$userId]);$expectedTotal=(int)$expected->fetchColumn();
$first=$repository->teamMembers($userId,[]);
studentTeamScalingAssert(count($first['members'])<=24,'Student team-directory pages may contain at most 24 teammates.');
studentTeamScalingAssert($first['pagination']['per_page']===24,'Student team pagination must remain at 24 records.');
studentTeamScalingAssert($first['pagination']['total']===$expectedTotal,'Student team-directory total must exclude only the signed-in student.');
studentTeamScalingAssert(!in_array($userId,array_column($first['members'],'id'),true),'The signed-in student must not be duplicated in the teammate directory.');
studentTeamScalingAssert(strlen(json_encode($first))<20000,'A Student team-directory page must remain below 20 KB.');

if($first['pagination']['last_page']>1){$second=$repository->teamMembers($userId,['page_number'=>2]);studentTeamScalingAssert(!array_intersect(array_column($first['members'],'id'),array_column($second['members'],'id')),'Adjacent Student team-directory pages must not overlap.');}
if($first['members']){$member=$first['members'][0];$searched=$repository->teamMembers($userId,['search'=>$member['full_name']]);studentTeamScalingAssert(in_array($member['id'],array_column($searched['members'],'id'),true),'Server-side teammate name search must return the matching member.');}

echo "Student team scaling checks passed.\n";
