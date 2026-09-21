<?php

declare(strict_types=1);

require_once __DIR__.'/officers.php';

function officerScalingAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$database = (new Database())->connection();
$repository = new OfficerManagementRepository($database);
$index = $repository->index([]);

officerScalingAssert(!array_key_exists('students', $index), 'Officer index must not contain the eligible-student roster.');

$expectedTotal = (int) $database->query("SELECT COUNT(*)
    FROM tbl_users u
    JOIN tbl_roles r ON r.id=u.role_id
    JOIN tbl_user_statuses s ON s.id=u.status
    WHERE r.name='Student' AND s.label='active' AND u.id_number IS NOT NULL
      AND NOT EXISTS (SELECT 1 FROM tbl_sbo_officer_assignments a WHERE a.student_id=u.id_number)")->fetchColumn();
$first = $repository->eligibleStudents([]);
officerScalingAssert(count($first['students']) <= 24, 'Eligible-officer student pages may contain at most 24 records.');
officerScalingAssert($first['pagination']['total'] === $expectedTotal, 'Eligible-student pagination total must match the database.');

if ($first['pagination']['last_page'] > 1) {
    $second = $repository->eligibleStudents(['page' => 2]);
    officerScalingAssert(
        !array_intersect(array_column($first['students'], 'id'), array_column($second['students'], 'id')),
        'Eligible-student pages must not overlap.'
    );
}

if ($first['students']) {
    $student = $first['students'][0];
    $search = $repository->eligibleStudents(['search' => $student['id_number']]);
    officerScalingAssert(
        in_array($student['id'], array_column($search['students'], 'id'), true),
        'Exact student-ID search must return the eligible student.'
    );
    $yearFilter = $student['year_level'] === null ? 'none' : (string) $student['year_level'];
    $filtered = $repository->eligibleStudents(['year_level' => $yearFilter]);
    foreach ($filtered['students'] as $candidate) {
        officerScalingAssert(
            ($yearFilter === 'none' && $candidate['year_level'] === null)
                || ($yearFilter !== 'none' && $candidate['year_level'] === (int) $yearFilter),
            'Year-level filtering returned a student from a different year level.'
        );
    }
}

echo "Officer management scaling checks passed.\n";
