<?php

declare(strict_types=1);

require_once __DIR__.'/adviser-events.php';

function eventAudienceAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$repository = new EventManagementRepository((new Database())->connection());
$metadata = $repository->metadata();

eventAudienceAssert(!array_key_exists('active_students', $metadata), 'Event metadata must not contain the full student roster.');
eventAudienceAssert(isset($metadata['active_students_count']), 'Event metadata must contain the active-student summary count.');
foreach (array_merge($metadata['audience_year_levels'], $metadata['audience_teams']) as $audience) {
    eventAudienceAssert(!array_key_exists('member_ids', $audience), 'Audience summaries must not contain duplicated member IDs.');
    eventAudienceAssert(isset($audience['students_count']), 'Audience summaries must contain a student count.');
}

$firstPage = $repository->studentCandidates([]);
eventAudienceAssert(count($firstPage['students']) <= 24, 'Student candidate pages may contain at most 24 records.');
eventAudienceAssert($firstPage['pagination']['per_page'] === 24, 'Student candidate pagination must remain at 24 records.');
eventAudienceAssert(
    $firstPage['pagination']['total'] === (int) $metadata['active_students_count'],
    'Candidate total must match the active-student summary count.'
);

if ($firstPage['pagination']['last_page'] > 1) {
    $secondPage = $repository->studentCandidates(['page' => 2]);
    $firstIds = array_column($firstPage['students'], 'id');
    $secondIds = array_column($secondPage['students'], 'id');
    eventAudienceAssert(!array_intersect($firstIds, $secondIds), 'Consecutive candidate pages must not repeat students.');
}

echo "Event audience scaling checks passed.\n";
