<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__.'/scores.php';

$db = (new Database())->connection();
$repository = new ScoreManagementRepository($db);
$eventId = (int) $db->query('SELECT id FROM tbl_events WHERE deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn();
$teamId = (int) $db->query('SELECT id FROM tbl_teams WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
$actorId = (int) $db->query("SELECT u.id FROM tbl_users u JOIN tbl_roles r ON r.id=u.role_id WHERE r.name='SBO Adviser' ORDER BY u.id LIMIT 1")->fetchColumn();

if (!$eventId || !$teamId || !$actorId) {
    throw new RuntimeException('Activity scoring test requires an event, an active team, and an SBO Adviser.');
}

$db->beginTransaction();
try {
    $activityInsert = $db->prepare("INSERT INTO tbl_event_activities(event_id,name,status,created_by,created_at,updated_at) VALUES(?,?,'active',?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
    $activityInsert->execute([$eventId, '__Scoring test A', $actorId]);
    $activityA = (int) $db->lastInsertId();
    $activityInsert->execute([$eventId, '__Scoring test B', $actorId]);
    $activityB = (int) $db->lastInsertId();

    $categoryInsert = $db->prepare('INSERT INTO tbl_score_categories(event_id,activity_id,name,max_points,sort_order,created_at,updated_at) VALUES(?,?,?,?,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
    $categoryInsert->execute([$eventId, $activityA, '__Shared criterion name', 100]);
    $categoryA = (int) $db->lastInsertId();
    $categoryInsert->execute([$eventId, $activityB, '__Shared criterion name', 50]);
    $categoryB = (int) $db->lastInsertId();

    $scoreInsert = $db->prepare('INSERT INTO tbl_scores(event_id,team_id,score_category_id,points,recorded_by,created_at,updated_at) VALUES(?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
    $scoreInsert->execute([$eventId, $teamId, $categoryA, 88, $actorId]);

    $view = $repository->show($eventId, $activityA);
    if (count($view['categories']) !== 1 || $view['categories'][0]['id'] !== $categoryA) {
        throw new RuntimeException('The activity view exposed a category from another activity.');
    }
    if (($view['scores'][$categoryA.'-'.$teamId] ?? null) !== 88.0) {
        throw new RuntimeException('The activity score was not returned in its score matrix.');
    }

    $blocked = false;
    try {
        $repository->saveScores($eventId, [
            'activity_id' => $activityA,
            'scores' => [$categoryB => [$teamId => '25']],
        ], $actorId);
    } catch (InvalidArgumentException) {
        $blocked = true;
    }
    if (!$blocked) throw new RuntimeException('A category from another activity was accepted.');

    echo "PASS: activity-specific categories, scores, and cross-activity protection.\n";
} finally {
    if ($db->inTransaction()) $db->rollBack();
}
