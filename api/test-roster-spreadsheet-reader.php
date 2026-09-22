<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/student-roster-imports.php';

function rosterReaderAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

if (!class_exists(ZipArchive::class)) {
    throw new RuntimeException('The PHP ZIP extension is required for this regression test.');
}

$temporaryPath = tempnam(sys_get_temp_dir(), 'roster-reader-');
if ($temporaryPath === false) {
    throw new RuntimeException('Could not create the temporary workbook.');
}

$headers = ['record_key', 'student_id', 'official_name', 'email', 'year_level', 'tribe', 'school_year', 'row_status'];
$student = ['SID:02-2026-00001', '02-2026-00001', 'Student, Sample', '', '1', 'Titan Slayers', 'SY 26-27 SEM I', 'READY'];
$rowXml = static function (int $number, array $values): string {
    $cells = [];
    foreach ($values as $index => $value) {
        $column = chr(65 + $index);
        $escaped = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $cells[] = "<x:c r=\"{$column}{$number}\" t=\"inlineStr\"><x:is><x:t>{$escaped}</x:t></x:is></x:c>";
    }
    return '<x:row r="'.$number.'">'.implode('', $cells).'</x:row>';
};

$zip = new ZipArchive();
rosterReaderAssert($zip->open($temporaryPath, ZipArchive::OVERWRITE) === true, 'Could not open the temporary workbook archive.');
$zip->addFromString(
    'xl/workbook.xml',
    '<?xml version="1.0" encoding="utf-8"?>'
    .'<x:workbook xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    .'<x:sheets><x:sheet name="Master Roster" sheetId="1" r:id="rId1" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" /></x:sheets>'
    .'</x:workbook>'
);
$zip->addFromString(
    'xl/_rels/workbook.xml.rels',
    "\xEF\xBB\xBF".'<?xml version="1.0" encoding="utf-8"?>'
    .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="/xl/worksheets/sheet1.xml" />'
    .'</Relationships>'
);
$zip->addFromString(
    'xl/worksheets/sheet1.xml',
    '<?xml version="1.0" encoding="utf-8"?>'
    .'<x:worksheet xmlns:x="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><x:sheetData>'
    .$rowXml(1, $headers).$rowXml(2, $student)
    .'</x:sheetData></x:worksheet>'
);
$zip->close();

try {
    $records = (new RosterSpreadsheetReader())->read($temporaryPath);
    rosterReaderAssert(count($records) === 1, 'The namespace-prefixed workbook must yield one student record.');
    rosterReaderAssert(($records[0]['student_id'] ?? '') === '02-2026-00001', 'The Student ID was not read from the prefixed worksheet.');
    rosterReaderAssert(($records[0]['official_name'] ?? '') === 'Student, Sample', 'Inline string values were not read from the prefixed worksheet.');
} finally {
    @unlink($temporaryPath);
}

echo "Roster spreadsheet namespace compatibility checks passed.\n";
