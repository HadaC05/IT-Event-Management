<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/StudentInitialCredential.php';

$assert = static function (bool $condition, string $label): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$label);
    echo 'PASS: '.$label.PHP_EOL;
};

$assert(StudentInitialCredential::temporaryPassword('02-2425-005055', 'Salinas') === '02-2425-005055SALINAS', 'standard surname follows the complete Student ID');
$assert(StudentInitialCredential::temporaryPassword('02-2425-000001', 'Abay-Abay') === '02-2425-000001ABAYABAY', 'hyphenated surname is entered without punctuation');
$assert(StudentInitialCredential::temporaryPassword('02-2425-000002', 'Dela Peña') === '02-2425-000002DELAPENA', 'spaces and diacritics are normalized consistently');
try {
    StudentInitialCredential::temporaryPassword('02-2425-000003', '---');
    $assert(false, 'empty normalized surname is rejected');
} catch (InvalidArgumentException) {
    $assert(true, 'empty normalized surname is rejected');
}

echo 'Student initial credential regression test completed.'.PHP_EOL;
