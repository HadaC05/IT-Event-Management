<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/AcademicPeriodLabel.php';

final class RosterSpreadsheetReader
{
    private const RELATIONSHIP_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** @return array<int, array<string, string>> */
    public function read(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP ZIP extension is required to read Excel files.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('The uploaded Excel workbook could not be opened.');
        }

        try {
            $sheetPath = $this->masterRosterSheetPath($zip);
            $sharedStrings = $this->sharedStrings($zip);
            $sheetXml = $zip->getFromName($sheetPath);
            if ($sheetXml === false) {
                throw new InvalidArgumentException('The Master Roster worksheet is missing.');
            }

            $sheet = simplexml_load_string($sheetXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
            if (!$sheet) {
                throw new InvalidArgumentException('The Master Roster worksheet is not valid XML.');
            }

            $headers = [];
            $records = [];
            foreach ($sheet->sheetData->row as $row) {
                $rowNumber = (int) ($row['r'] ?? 0);
                $values = [];
                foreach ($row->c as $cell) {
                    $reference = (string) ($cell['r'] ?? '');
                    $column = $this->columnIndex($reference);
                    $values[$column] = $this->cellValue($cell, $sharedStrings);
                }

                if ($headers === []) {
                    if ($rowNumber !== 1) {
                        continue;
                    }
                    ksort($values);
                    foreach ($values as $column => $value) {
                        $headers[$column] = trim(mb_strtolower($value));
                    }
                    continue;
                }

                $record = ['_source_row' => (string) $rowNumber];
                $hasValue = false;
                foreach ($headers as $column => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $value = trim((string) ($values[$column] ?? ''));
                    $record[$header] = $value;
                    $hasValue = $hasValue || $value !== '';
                }
                if ($hasValue) {
                    $records[] = $record;
                }
            }

            $requiredHeaders = ['record_key', 'student_id', 'official_name', 'email', 'year_level', 'tribe', 'school_year', 'row_status'];
            $missing = array_values(array_diff($requiredHeaders, array_values($headers)));
            if ($missing !== []) {
                throw new InvalidArgumentException('Master Roster is missing required columns: '.implode(', ', $missing).'.');
            }

            return $records;
        } finally {
            $zip->close();
        }
    }

    private function masterRosterSheetPath(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relationshipsXml === false) {
            throw new InvalidArgumentException('This is not a supported Excel workbook.');
        }

        $workbook = simplexml_load_string($workbookXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        $relationships = simplexml_load_string($relationshipsXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if (!$workbook || !$relationships) {
            throw new InvalidArgumentException('The workbook structure could not be read.');
        }

        $relationshipTargets = [];
        foreach ($relationships->Relationship as $relationship) {
            $relationshipTargets[(string) $relationship['Id']] = (string) $relationship['Target'];
        }

        foreach ($workbook->sheets->sheet as $sheet) {
            if (mb_strtolower(trim((string) $sheet['name'])) !== 'master roster') {
                continue;
            }
            $relationshipAttributes = $sheet->attributes(self::RELATIONSHIP_NS);
            $relationshipId = (string) ($relationshipAttributes['id'] ?? '');
            $target = $relationshipTargets[$relationshipId] ?? '';
            if ($target === '') {
                break;
            }
            $target = str_replace('\\', '/', $target);
            return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.ltrim($target, '/');
        }

        throw new InvalidArgumentException('The workbook must contain a worksheet named Master Roster.');
    }

    /** @return array<int, string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if (!$document) {
            return [];
        }
        $strings = [];
        foreach ($document->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }
            $parts = [];
            foreach ($item->r as $run) {
                $parts[] = (string) $run->t;
            }
            $strings[] = implode('', $parts);
        }
        return $strings;
    }

    /** @param array<int, string> $sharedStrings */
    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) ($cell['t'] ?? '');
        if ($type === 'inlineStr') {
            if (isset($cell->is->t)) {
                return (string) $cell->is->t;
            }
            $parts = [];
            foreach ($cell->is->r as $run) {
                $parts[] = (string) $run->t;
            }
            return implode('', $parts);
        }
        $value = (string) ($cell->v ?? '');
        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }
        if ($type === 'b') {
            return $value === '1' ? '1' : '0';
        }
        return $value;
    }

    private function columnIndex(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $reference, $matches)) {
            return 0;
        }
        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }
        return max(0, $index - 1);
    }
}

final class StudentRosterImportService
{
    private const ACCEPTED = ['ready', 'warning'];
    private const TEAM_COLORS = [
        'Titan Slayers' => '#397565',
        'Demon Slayers' => '#2F3AE0',
        'Chainsaw Squad' => '#FF6B2C',
        'Hero Academia' => '#8B5CF6',
        'Jujutsu Sorcerers' => '#0F766E',
        'Spy X Family' => '#DB2777',
        'Straw Hat' => '#D4A017',
    ];

    public function __construct(private readonly PDO $db, private readonly RosterSpreadsheetReader $reader) {}

    public function preview(string $path, string $filename, int $actorId): array
    {
        $records = $this->reader->read($path);
        if ($records === []) {
            throw new InvalidArgumentException('Master Roster contains no student rows.');
        }
        if (count($records) > 10000) {
            throw new InvalidArgumentException('A roster import may contain at most 10,000 rows.');
        }

        $idRows = [];
        $emailRows = [];
        foreach ($records as $index => $record) {
            $studentId = $this->clean($record['student_id'] ?? '');
            $email = mb_strtolower($this->clean($record['email'] ?? ''));
            if ($studentId !== '') $idRows[mb_strtoupper($studentId)][] = $index;
            if ($email !== '') $emailRows[$email][] = $index;
        }

        $validated = [];
        $counts = ['ready' => 0, 'warning' => 0, 'review' => 0, 'blocked' => 0];
        $flagCounts = [];
        foreach ($records as $index => $record) {
            $result = $this->validateRecord($record, $idRows, $emailRows, $index);
            $validated[] = $result;
            $counts[$result['validation_status']]++;
            foreach ($result['flags'] as $flag) {
                $key = $flag['severity'].':'.$flag['code'];
                if (!isset($flagCounts[$key])) {
                    $flagCounts[$key] = ['severity' => $flag['severity'], 'code' => $flag['code'], 'message' => $flag['message'], 'count' => 0];
                }
                $flagCounts[$key]['count']++;
            }
        }

        usort($flagCounts, static fn (array $a, array $b): int => [$a['severity'], $b['count']] <=> [$b['severity'], $a['count']]);
        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new RuntimeException('The workbook checksum could not be calculated.');
        }

        $this->db->beginTransaction();
        try {
            $batch = $this->db->prepare("INSERT INTO tbl_student_import_batches
                (original_filename,file_sha256,mode,status,total_rows,ready_rows,warning_rows,review_rows,blocked_rows,imported_by,created_at)
                VALUES (?,?, 'preview','previewed',?,?,?,?,?,?,CURRENT_TIMESTAMP)");
            $batch->execute([
                mb_substr(basename($filename), 0, 255), $hash, count($validated), $counts['ready'],
                $counts['warning'], $counts['review'], $counts['blocked'], $actorId,
            ]);
            $batchId = (int) $this->db->lastInsertId();

            $insert = $this->db->prepare("INSERT INTO tbl_student_import_rows
                (batch_id,source_row,record_key,student_id,official_name,email,gender,campus,program,year_level,
                 section_name,tribe,school_year,enrollment_status,source_files,source_issues,review_resolution,
                 source_row_status,validation_status,flags_json,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
            foreach ($validated as $row) {
                $record = $row['record'];
                $insert->execute([
                    $batchId, (int) $record['_source_row'], $this->nullable($record['record_key'] ?? ''),
                    $this->nullable($record['student_id'] ?? ''), $this->nullable($record['official_name'] ?? ''),
                    $this->nullable($record['email'] ?? ''), $this->nullable($record['gender'] ?? ''),
                    $this->nullable($record['campus'] ?? ''), $this->nullable($record['program'] ?? ''),
                    $this->nullable($record['year_level'] ?? ''), $this->nullable($record['section'] ?? ''),
                    $this->nullable($record['tribe'] ?? ''), $this->nullable($record['school_year'] ?? ''),
                    $this->nullable($record['enrollment_status'] ?? ''), $this->nullable($record['source_files'] ?? ''),
                    $this->nullable($record['source_issues'] ?? ''), $this->nullable($record['review_resolution'] ?? ''),
                    $this->nullable($record['row_status'] ?? ''), $row['validation_status'],
                    json_encode($row['flags'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]);
            }
            $this->db->commit();
        } catch (Throwable $error) {
            $this->db->rollBack();
            throw $error;
        }

        return [
            'batch_id' => $batchId,
            'filename' => basename($filename),
            'sha256' => $hash,
            'summary' => ['total' => count($validated)] + $counts + [
                'importable' => $counts['ready'] + $counts['warning'],
                'excluded' => $counts['review'] + $counts['blocked'],
            ],
            'flag_counts' => array_values($flagCounts),
            'flagged_rows' => $this->flaggedRows($batchId, '', 1, 25),
        ];
    }

    public function applyReplacement(int $batchId, int $actorId): array
    {
        if (PHP_SAPI !== 'cli') {
            set_time_limit(600);
        }
        $batch = $this->batch($batchId);
        if ($batch['status'] !== 'previewed') {
            throw new InvalidArgumentException('Only a previewed import can be applied.');
        }
        if ((int) $batch['ready_rows'] + (int) $batch['warning_rows'] === 0) {
            throw new InvalidArgumentException('This import has no validated student rows to apply.');
        }

        $rowsStatement = $this->db->prepare("SELECT * FROM tbl_student_import_rows
            WHERE batch_id=? AND validation_status IN ('ready','warning') ORDER BY source_row");
        $rowsStatement->execute([$batchId]);
        $rows = $rowsStatement->fetchAll();

        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare("UPDATE tbl_student_import_batches SET status='applying',mode='replace',failure_message=NULL WHERE id=? AND status='previewed'");
            $lock->execute([$batchId]);
            if ($lock->rowCount() !== 1) {
                throw new RuntimeException('The import is already being applied by another request.');
            }

            $this->purgeOperationalData();
            $studentRoleId = (int) $this->requiredValue("SELECT id FROM tbl_roles WHERE name='Student'", 'Student role');
            $activeStatusId = (int) $this->requiredValue("SELECT id FROM tbl_user_statuses WHERE label='active'", 'Active user status');
            $yearLevelIds = [];
            foreach ($this->db->query('SELECT id,label FROM tbl_year_levels')->fetchAll() as $level) {
                $yearLevelIds[$this->yearNumber((string) $level['label'])] = (int) $level['id'];
            }

            $schoolYearIds = [];
            $periodsBySource = [];
            $schoolYearInsert = $this->db->prepare('INSERT INTO tbl_school_years (label,created_at,updated_at) VALUES (?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
            $periodInsert = $this->db->prepare('INSERT INTO tbl_academic_periods (school_year_id,term_code,term_name,is_active,created_at,updated_at) VALUES (?,?,?,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
            $createdPeriods = [];
            foreach (array_values(array_unique(array_filter(array_map(static fn (array $row): string => trim((string) $row['school_year']), $rows)))) as $sourcePeriod) {
                $period = AcademicPeriodLabel::parse($sourcePeriod);
                $yearLabel = $period['school_year_label'];
                if (!isset($schoolYearIds[$yearLabel])) {
                    $schoolYearInsert->execute([$yearLabel]);
                    $schoolYearIds[$yearLabel] = (int) $this->db->lastInsertId();
                }
                $periodKey = $yearLabel.'|'.$period['term_code'];
                if (!isset($createdPeriods[$periodKey])) {
                    $periodInsert->execute([$schoolYearIds[$yearLabel], $period['term_code'], $period['term_name']]);
                    $createdPeriods[$periodKey] = (int) $this->db->lastInsertId();
                }
                $periodsBySource[$sourcePeriod] = $period + ['id' => $createdPeriods[$periodKey]];
            }

            $teamIds = [];
            $teamInsert = $this->db->prepare('INSERT INTO tbl_teams (school_year_id,name,color,is_active,created_at,updated_at) VALUES (?,?,?,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
            foreach ($rows as $row) {
                $sourcePeriod = trim((string) $row['school_year']);
                $schoolYear = $periodsBySource[$sourcePeriod]['school_year_label'] ?? '';
                $tribe = trim((string) $row['tribe']);
                if ($tribe === '' || !isset($schoolYearIds[$schoolYear])) continue;
                $key = $schoolYear.'|'.mb_strtolower($tribe);
                if (isset($teamIds[$key])) continue;
                $teamInsert->execute([$schoolYearIds[$schoolYear], $tribe, self::TEAM_COLORS[$tribe] ?? '#397565']);
                $teamIds[$key] = (int) $this->db->lastInsertId();
            }

            $userInsert = $this->db->prepare("INSERT INTO tbl_users
                (role_id,id_number,first_name,middle_name,last_name,year_level,officer_team_id,username,password,
                 must_change_password,status,email,created_at,updated_at)
                VALUES (?,?,?,?,?,?,NULL,?,?,1,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
            $profileInsert = $this->db->prepare("INSERT INTO tbl_student_profiles
                (user_id,record_key,gender,campus,program,section_name,school_year_label,enrollment_status,source_files,last_import_batch_id,created_at,updated_at)
                VALUES (?,?,?,?,?,?,?,?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)");
            $membershipInsert = $this->db->prepare('INSERT INTO tbl_team_user (team_id,user_id,created_at,updated_at) VALUES (?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
            // Keep the validation status after import so warnings remain visible in the review queue.
            // A non-null user_id is the durable indication that the row was applied.
            $rowUpdate = $this->db->prepare('UPDATE tbl_student_import_rows SET user_id=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');

            $imported = 0;
            foreach ($rows as $row) {
                $studentId = trim((string) $row['student_id']);
                [$firstName, $lastName] = $this->splitOfficialName((string) $row['official_name']);
                $yearNumber = (int) $row['year_level'];
                $email = mb_strtolower(trim((string) $row['email']));
                if ($email === '') {
                    $email = mb_strtolower(preg_replace('/[^A-Za-z0-9]+/', '.', $studentId) ?? $studentId).'@pending.invalid';
                }
                $temporaryPassword = $this->temporaryPassword($studentId);
                $userInsert->execute([
                    $studentRoleId, $studentId, $firstName, null, $lastName, $yearLevelIds[$yearNumber] ?? null,
                    $studentId, password_hash($temporaryPassword, PASSWORD_BCRYPT, ['cost' => 10]), $activeStatusId, $email,
                ]);
                $userId = (int) $this->db->lastInsertId();
                $profileInsert->execute([
                    $userId, (string) $row['record_key'], $row['gender'], $row['campus'], $row['program'],
                    $row['section_name'], $periodsBySource[trim((string) $row['school_year'])]['label'], $row['enrollment_status'], $row['source_files'], $batchId,
                ]);
                $tribe = trim((string) $row['tribe']);
                $schoolYear = $periodsBySource[trim((string) $row['school_year'])]['school_year_label'];
                $teamKey = $schoolYear.'|'.mb_strtolower($tribe);
                if ($tribe !== '' && isset($teamIds[$teamKey])) {
                    $membershipInsert->execute([$teamIds[$teamKey], $userId]);
                }
                $rowUpdate->execute([$userId, (int) $row['id']]);
                $imported++;
            }

            $skipped = (int) $batch['review_rows'] + (int) $batch['blocked_rows'];
            $complete = $this->db->prepare("UPDATE tbl_student_import_batches
                SET status='completed',imported_rows=?,skipped_rows=?,applied_at=CURRENT_TIMESTAMP WHERE id=?");
            $complete->execute([$imported, $skipped, $batchId]);
            $this->db->commit();
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            // Only the request that still owns a previewable batch may mark it failed.
            // A concurrent retry may have completed the same batch while this request
            // was waiting for the row lock; never overwrite that completed state.
            $this->markFailedIfPreviewed($batchId, $error->getMessage());
            throw $error;
        }

        return [
            'batch_id' => $batchId,
            'imported' => $imported,
            'flagged' => $skipped,
            'temporary_password_rule' => 'CITE@ followed by the final 5 or 6 digits of the Student ID',
            'must_change_password' => true,
        ];
    }

    private function markFailedIfPreviewed(int $batchId, string $message): void
    {
        $failed = $this->db->prepare("UPDATE tbl_student_import_batches SET status='failed',failure_message=? WHERE id=? AND status='previewed'");
        $failed->execute([mb_substr($message, 0, 1000), $batchId]);
    }

    public function latest(?int $batchId = null, string $status = '', int $page = 1): array
    {
        if ($batchId === null) {
            $value = $this->db->query('SELECT id FROM tbl_student_import_batches ORDER BY id DESC LIMIT 1')->fetchColumn();
            if ($value === false) return ['batch' => null, 'flagged_rows' => null];
            $batchId = (int) $value;
        }
        $batch = $this->batch($batchId);
        return [
            'batch' => $batch,
            'flag_counts' => [
                ['severity' => 'warning', 'code' => 'warning_rows', 'message' => 'Imported rows with incomplete non-key information.', 'count' => $batch['warning_rows']],
                ['severity' => 'review', 'code' => 'review_rows', 'message' => 'Rows that need a documented human decision.', 'count' => $batch['review_rows']],
                ['severity' => 'blocked', 'code' => 'blocked_rows', 'message' => 'Rows that cannot create an account yet.', 'count' => $batch['blocked_rows']],
            ],
            'flagged_rows' => $this->flaggedRows($batchId, $status, $page, 25),
        ];
    }

    private function validateRecord(array $record, array $idRows, array $emailRows, int $index): array
    {
        $flags = [];
        $add = static function (string $severity, string $code, string $message, string $field = '') use (&$flags): void {
            $flags[] = compact('severity', 'code', 'message', 'field');
        };
        $studentId = $this->clean($record['student_id'] ?? '');
        $officialName = $this->clean($record['official_name'] ?? '');
        $email = mb_strtolower($this->clean($record['email'] ?? ''));
        $year = $this->clean($record['year_level'] ?? '');
        $sourceStatus = mb_strtoupper($this->clean($record['row_status'] ?? ''));
        $resolution = mb_strtolower($this->clean($record['review_resolution'] ?? ''));

        if ($studentId === '') $add('blocked', 'missing_student_id', 'Student ID is required before an account can be created.', 'student_id');
        elseif (!StudentId::isValid($studentId)) $add('blocked', 'invalid_student_id', StudentId::FORMAT_MESSAGE, 'student_id');
        elseif (count($idRows[mb_strtoupper($studentId)] ?? []) > 1) $add('blocked', 'duplicate_student_id', 'The Student ID appears more than once in this workbook.', 'student_id');
        if ($officialName === '') $add('blocked', 'missing_official_name', 'Official name is required.', 'official_name');
        elseif (!str_contains($officialName, ',')) $add('warning', 'name_format', 'Official name does not use the expected LAST, FIRST format.', 'official_name');
        if (!in_array((int) $year, [1, 2, 3, 4], true)) $add('review', 'invalid_year_level', 'Year level must be 1, 2, 3, or 4.', 'year_level');
        $sourcePeriod = $this->clean($record['school_year'] ?? '');
        if ($sourcePeriod === '') {
            $add('review', 'missing_school_year', 'School year and semester are required for tribe membership.', 'school_year');
        } else {
            try {
                AcademicPeriodLabel::parse($sourcePeriod);
            } catch (InvalidArgumentException $error) {
                $add('review', 'invalid_academic_period', $error->getMessage(), 'school_year');
            }
        }
        if ($email === '') $add('warning', 'missing_email', 'Email is missing; a pending placeholder will be used.', 'email');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $add('review', 'invalid_email', 'Email address is not valid.', 'email');
        elseif (count($emailRows[$email] ?? []) > 1) $add('review', 'duplicate_email', 'Email is assigned to more than one Student ID.', 'email');
        if ($this->clean($record['tribe'] ?? '') === '') $add('warning', 'missing_tribe', 'No tribe membership will be created until a tribe is confirmed.', 'tribe');
        foreach (['gender' => 'Gender', 'campus' => 'Campus', 'section' => 'Section'] as $field => $label) {
            if ($this->clean($record[$field] ?? '') === '') $add('warning', 'missing_'.$field, $label.' is missing.', $field);
        }
        if (in_array($sourceStatus, ['BLOCKED'], true)) $add('blocked', 'source_blocked', 'The master roster marked this row as blocked.', 'row_status');
        elseif ($sourceStatus === 'REVIEW') $add('review', 'source_review', 'The master roster marked this row for human review.', 'row_status');
        elseif (!in_array($sourceStatus, ['READY', 'READY WITH GAPS'], true)) $add('review', 'unknown_source_status', 'The row has an unknown import status.', 'row_status');
        if ($resolution === 'pending' || ($this->clean($record['source_issues'] ?? '') !== '' && !in_array($resolution, ['confirmed', 'corrected', 'not applicable'], true))) {
            $add('review', 'unresolved_source_issue', 'A source conflict still needs a documented resolution.', 'review_resolution');
        }

        $rank = ['ready' => 0, 'warning' => 1, 'review' => 2, 'blocked' => 3];
        $status = 'ready';
        foreach ($flags as $flag) {
            if ($rank[$flag['severity']] > $rank[$status]) $status = $flag['severity'];
        }
        return ['record' => $record, 'validation_status' => $status, 'flags' => $flags];
    }

    private function purgeOperationalData(): void
    {
        foreach (['tbl_posts', 'tbl_events', 'tbl_teams'] as $table) {
            $this->db->exec('DELETE FROM '.$table);
        }
        $this->db->exec("DELETE u FROM tbl_users u LEFT JOIN tbl_roles r ON r.id=u.role_id WHERE r.name IS NULL OR r.name <> 'SBO Adviser'");
        foreach ([
            'tbl_activity_logs', 'tbl_notifications', 'tbl_sessions', 'tbl_account_password_reset_tokens',
            'tbl_password_reset_tokens', 'tbl_sbo_scan_rate_limits', 'tbl_cache', 'tbl_cache_locks',
            'tbl_jobs', 'tbl_failed_jobs', 'tbl_job_batches',
        ] as $table) {
            $this->db->exec('DELETE FROM '.$table);
        }
        $this->db->exec('DELETE FROM tbl_academic_periods');
        $this->db->exec('DELETE FROM tbl_school_years');
    }

    private function flaggedRows(int $batchId, string $status, int $page, int $perPage): array
    {
        $allowed = ['warning', 'review', 'blocked'];
        $where = 'batch_id=? AND validation_status <> \'ready\' AND validation_status <> \'imported\'';
        $params = [$batchId];
        if ($status !== '') {
            if (!in_array($status, $allowed, true)) throw new InvalidArgumentException('Invalid import status filter.');
            $where .= ' AND validation_status=?';
            $params[] = $status;
        }
        $count = $this->db->prepare('SELECT COUNT(*) FROM tbl_student_import_rows WHERE '.$where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $statement = $this->db->prepare("SELECT id,source_row,record_key,student_id,official_name,email,year_level,tribe,
            validation_status,flags_json FROM tbl_student_import_rows WHERE $where ORDER BY source_row LIMIT ? OFFSET ?");
        $position = 1;
        foreach ($params as $value) $statement->bindValue($position++, $value);
        $statement->bindValue($position++, $perPage, PDO::PARAM_INT);
        $statement->bindValue($position, ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['source_row'] = (int) $row['source_row'];
            $row['flags'] = json_decode((string) $row['flags_json'], true, 512, JSON_THROW_ON_ERROR);
            unset($row['flags_json']);
        }
        unset($row);
        return ['rows' => $rows, 'pagination' => compact('page', 'lastPage', 'perPage', 'total')];
    }

    private function batch(int $batchId): array
    {
        $statement = $this->db->prepare('SELECT * FROM tbl_student_import_batches WHERE id=?');
        $statement->execute([$batchId]);
        $batch = $statement->fetch();
        if (!$batch) throw new InvalidArgumentException('Roster import batch not found.');
        foreach (['id','total_rows','ready_rows','warning_rows','review_rows','blocked_rows','imported_rows','skipped_rows'] as $field) {
            $batch[$field] = (int) $batch[$field];
        }
        return $batch;
    }

    private function requiredValue(string $sql, string $label): mixed
    {
        $value = $this->db->query($sql)->fetchColumn();
        if ($value === false) throw new RuntimeException($label.' is missing from the database.');
        return $value;
    }

    /** @return array{0:string,1:string} */
    private function splitOfficialName(string $officialName): array
    {
        $officialName = trim(preg_replace('/\s+/', ' ', $officialName) ?? $officialName);
        if (str_contains($officialName, ',')) {
            [$lastName, $givenNames] = array_map('trim', explode(',', $officialName, 2));
            return [$givenNames !== '' ? $givenNames : 'Student', $lastName !== '' ? $lastName : 'Unknown'];
        }
        $parts = preg_split('/\s+/', $officialName) ?: [];
        $lastName = array_pop($parts) ?: 'Unknown';
        return [implode(' ', $parts) ?: 'Student', $lastName];
    }

    private function temporaryPassword(string $studentId): string
    {
        $parts = explode('-', $studentId);
        return 'CITE@'.(end($parts) ?: preg_replace('/\D/', '', $studentId));
    }

    private function yearNumber(string $label): int
    {
        return match (true) {
            str_contains(mb_strtolower($label), 'first') => 1,
            str_contains(mb_strtolower($label), 'second') => 2,
            str_contains(mb_strtolower($label), 'third') => 3,
            str_contains(mb_strtolower($label), 'fourth') => 4,
            default => 0,
        };
    }

    private function clean(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value) ?? (string) $value);
    }

    private function nullable(mixed $value): ?string
    {
        $clean = $this->clean($value);
        return $clean === '' ? null : $clean;
    }
}

function rosterImportError(Throwable $error): never
{
    $status = $error instanceof InvalidArgumentException ? 422 : 500;
    JsonResponse::send(['success' => false, 'message' => $error->getMessage()], $status);
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

$database = new Database();
$service = new StudentRosterImportService($database->connection(), new RosterSpreadsheetReader());

if (PHP_SAPI === 'cli') {
    $options = getopt('', ['file:', 'actor::', 'preview-only']);
    $file = (string) ($options['file'] ?? '');
    $actor = (int) ($options['actor'] ?? 0);
    if ($file === '' || !is_file($file) || $actor < 1) {
        fwrite(STDERR, "Usage: php api/student-roster-imports.php --file=roster.xlsx --actor=USER_ID [--preview-only]\n");
        exit(2);
    }
    try {
        $preview = $service->preview($file, basename($file), $actor);
        $result = ['preview' => [
            'batch_id' => $preview['batch_id'],
            'filename' => $preview['filename'],
            'sha256' => $preview['sha256'],
            'summary' => $preview['summary'],
            'flag_counts' => $preview['flag_counts'],
        ]];
        if (!array_key_exists('preview-only', $options)) {
            $result['apply'] = $service->applyReplacement((int) $preview['batch_id'], $actor);
        }
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
        exit(0);
    } catch (Throwable $error) {
        fwrite(STDERR, $error::class.': '.$error->getMessage().PHP_EOL);
        exit(1);
    }
}

try {
    $currentUser = AuthGuard::requireRole('SBO Adviser');
    $actorId = (int) $currentUser['id'];
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $batchId = isset($_GET['batch_id']) && $_GET['batch_id'] !== '' ? (int) $_GET['batch_id'] : null;
        JsonResponse::send(['success' => true, 'data' => $service->latest($batchId, trim((string) ($_GET['status'] ?? '')), (int) ($_GET['page'] ?? 1))]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        JsonResponse::send(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 419);
    }

    $action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
    if ($action === 'preview') {
        $upload = $_FILES['roster'] ?? null;
        if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Choose an Excel roster to preview.');
        }
        if ((int) $upload['size'] > 25 * 1024 * 1024) throw new InvalidArgumentException('The roster may not exceed 25 MB.');
        if (mb_strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION)) !== 'xlsx') throw new InvalidArgumentException('Upload an .xlsx workbook.');
        $data = $service->preview((string) $upload['tmp_name'], (string) $upload['name'], $actorId);
        JsonResponse::send([
            'success' => true,
            'message' => 'Preview complete. No accounts changed yet; confirm the replacement below to import the validated roster.',
            'data' => $data,
        ]);
    }

    $input = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    $action = trim((string) ($input['action'] ?? $action));
    if ($action === 'apply') {
        $data = $service->applyReplacement((int) ($input['batch_id'] ?? 0), $actorId);
        JsonResponse::send(['success' => true, 'message' => 'The validated roster replaced the demo data.', 'data' => $data]);
    }
    throw new InvalidArgumentException('Unknown roster import action.');
} catch (Throwable $error) {
    rosterImportError($error);
}
