<?php

declare(strict_types=1);

require_once __DIR__.'/student-roster-imports.php';

function rosterInclusionAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$service = new StudentRosterImportService((new Database())->connection(), new RosterSpreadsheetReader());
$validate = new ReflectionMethod($service, 'validateRecord');
$base = [
    '_source_row' => '2',
    'record_key' => 'PENDING-Y4-001',
    'student_id' => '',
    'official_name' => 'STUDENT, SAMPLE',
    'email' => '',
    'year_level' => '4',
    'tribe' => 'Solo Levelers',
    'school_year' => 'SY 26-27 SEM I',
    'row_status' => 'BLOCKED',
    'review_resolution' => 'Pending',
    'source_issues' => 'Official Student ID is not yet available.',
];

$pending = $validate->invoke($service, $base, [], [], 0);
rosterInclusionAssert($pending['validation_status'] === 'warning', 'A named student without an official ID must remain importable with warnings.');

$nonstandard = $base + ['student_id' => '131008882'];
$nonstandard['student_id'] = '131008882';
$nonstandard['row_status'] = 'REVIEW';
$nonstandardResult = $validate->invoke($service, $nonstandard, ['131008882' => [0]], [], 0);
rosterInclusionAssert($nonstandardResult['validation_status'] === 'warning', 'A nonstandard supplied Student ID must remain importable with warnings.');

$duplicateEmail = $base;
$duplicateEmail['student_id'] = '02-2026-00001';
$duplicateEmail['email'] = 'duplicate@example.test';
$duplicateEmail['row_status'] = 'REVIEW';
$duplicateEmailResult = $validate->invoke(
    $service,
    $duplicateEmail,
    ['02-2026-00001' => [0]],
    ['duplicate@example.test' => [0, 1]],
    0,
);
rosterInclusionAssert($duplicateEmailResult['validation_status'] === 'warning', 'Duplicate source emails must use pending addresses instead of excluding students.');

echo "Roster all-row inclusion checks passed.\n";
