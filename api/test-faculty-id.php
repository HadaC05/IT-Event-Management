<?php

declare(strict_types=1);

require_once __DIR__.'/ApiSupport.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
};

$assert(FacultyId::isValid('26-045-F'), 'the documented Faculty ID format is accepted');
$assert(FacultyId::isValid('26-045-f'), 'a lowercase suffix is normalized for validation');
$assert(FacultyId::isValid('20-001-F'), 'another 2x Faculty ID is accepted');
$assert(FacultyId::isValid('23-2324-A'), 'four middle digits and any final letter are accepted');
$assert(FacultyId::isValid('29-123456-z'), 'six middle digits and a lowercase final letter are accepted');
$assert(!FacultyId::isValid('26045F'), 'an unformatted Faculty ID is rejected');
$assert(FacultyId::isValid('19-045-F'), 'older Faculty ID year prefixes are accepted');
$assert(FacultyId::isValid('12-015-F'), 'the supplied roster’s oldest Faculty ID series is accepted');
$assert(!FacultyId::isValid('23-12-F'), 'fewer than three middle digits are rejected');
$assert(!FacultyId::isValid('02-2026-00001'), 'a Student ID is rejected as a Faculty ID');

echo "Faculty ID validation checks passed.\n";
