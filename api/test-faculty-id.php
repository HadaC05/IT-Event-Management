<?php

declare(strict_types=1);

require_once __DIR__.'/ApiSupport.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
};

$assert(FacultyId::isValid('26-045-F'), 'the documented Faculty ID format is accepted');
$assert(FacultyId::isValid('26-045-f'), 'a lowercase suffix is normalized for validation');
$assert(FacultyId::isValid('20-001-F'), 'another 2x Faculty ID is accepted');
$assert(!FacultyId::isValid('26045F'), 'an unformatted Faculty ID is rejected');
$assert(!FacultyId::isValid('19-045-F'), 'a Faculty ID outside the 2x series is rejected');
$assert(!FacultyId::isValid('02-2026-00001'), 'a Student ID is rejected as a Faculty ID');

echo "Faculty ID validation checks passed.\n";
