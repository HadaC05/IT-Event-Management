<?php

declare(strict_types=1);

require_once __DIR__.'/faculty-imports.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$workbook = $argv[1] ?? dirname(__DIR__).'/outputs/faculty-import-example/faculty-import-example.xlsx';
$rows = (new FacultySpreadsheetReader())->read($workbook);
$assert(count($rows) === 1, 'The example workbook should contain one faculty row.');
$assert($rows[0]['name'] === 'Alan Dave Villadores', 'Faculty Name was not detected.');
$assert($rows[0]['email'] === 'aduilladores@phinmaed.com', 'PHINMA Gmail was not detected.');
$assert($rows[0]['faculty_id'] === '26-045-F', 'School ID was not detected.');
$assert(FacultyId::isValid('23-2324-F'), 'The documented Faculty ID example should be valid.');
$assert(FacultyId::isValid('29-123456-z'), 'Six middle digits and a lowercase suffix should be accepted.');
$assert(!FacultyId::isValid('19-2324-F'), 'Faculty IDs must start with 2.');
$assert(!FacultyId::isValid('23-12-F'), 'Faculty IDs need at least three middle digits.');
$assert(FacultyInitialCredential::temporaryPassword('23-2324-f', 'Dela Cruz') === '23-2324-FDELACRUZ', 'Temporary Faculty password rule is incorrect.');

$database = (new Database())->connection();
$preview = (new FacultyImportService($database, new FacultySpreadsheetReader()))->preview($workbook);
$assert($preview['summary']['total'] === 1, 'The service should preview the workbook row.');
$assert($preview['summary']['ready'] + $preview['summary']['blocked'] === 1, 'Every preview row needs a final validation status.');
$existing = $database->prepare('SELECT COUNT(*) FROM tbl_users WHERE id_number=?');
$existing->execute([$rows[0]['faculty_id']]);
if ((int) $existing->fetchColumn() > 0) $assert($preview['rows'][0]['status'] === 'blocked', 'An existing Faculty ID must be blocked as a duplicate.');

echo "Faculty import checks passed.\n";
