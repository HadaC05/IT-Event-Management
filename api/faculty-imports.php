<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/FacultyInitialCredential.php';

final class FacultySpreadsheetReader
{
    private const RELATIONSHIP_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const HEADER_ALIASES = [
        'name' => ['name', 'faculty name', 'teacher name'],
        'email' => ['email', 'gmail', 'phinma gmail'],
        'faculty_id' => ['school id', 'faculty id', 'id number'],
    ];

    /** @return array<int,array{source_row:int,name:string,email:string,faculty_id:string}> */
    public function read(string $path): array
    {
        if (!class_exists(ZipArchive::class)) throw new RuntimeException('The PHP ZIP extension is required to read Excel files.');
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) throw new InvalidArgumentException('The uploaded Excel workbook could not be opened.');
        try {
            $sheetXml = $zip->getFromName($this->firstSheetPath($zip));
            if ($sheetXml === false) throw new InvalidArgumentException('The first worksheet could not be read.');
            $sheet = simplexml_load_string($sheetXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
            if ($sheet === false) throw new InvalidArgumentException('The worksheet is not valid XML.');
            $shared = $this->sharedStrings($zip);
            $headers = [];
            $records = [];
            foreach ($this->xpath($sheet, '//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $rowNumber = (int) ($row['r'] ?? 0);
                $values = [];
                foreach ($this->xpath($row, './*[local-name()="c"]') as $cell) {
                    $values[$this->columnIndex((string) ($cell['r'] ?? ''))] = trim($this->cellValue($cell, $shared));
                }
                if ($headers === []) {
                    if ($rowNumber > 20) break;
                    $candidate = $this->mapHeaders($values);
                    if (isset($candidate['name'], $candidate['faculty_id'])) {
                        $headers = $candidate;
                        continue;
                    }
                    $headers = $this->headerlessColumns($values);
                    if ($headers === []) continue;
                }
                $record = ['source_row' => $rowNumber, 'name' => '', 'email' => '', 'faculty_id' => ''];
                foreach ($headers as $field => $column) $record[$field] = trim((string) ($values[$column] ?? ''));
                if ($record['name'] !== '' || $record['email'] !== '' || $record['faculty_id'] !== '') $records[] = $record;
            }
            if ($headers === []) throw new InvalidArgumentException('The workbook needs Faculty ID and name columns. Headings are optional; email is optional.');
            return $records;
        } finally {
            $zip->close();
        }
    }

    /** @param array<int,string> $values @return array<string,int> */
    private function mapHeaders(array $values): array
    {
        $mapped = [];
        foreach ($values as $column => $value) {
            $normalized = mb_strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $value)));
            foreach (self::HEADER_ALIASES as $field => $aliases) {
                if (!isset($mapped[$field]) && in_array($normalized, $aliases, true)) $mapped[$field] = $column;
            }
        }
        return $mapped;
    }

    /** @param array<int,string> $values @return array<string,int> */
    private function headerlessColumns(array $values): array
    {
        $filled = array_filter($values, static fn (string $value): bool => $value !== '');
        if (count($filled) !== 2) return [];
        foreach ($filled as $idColumn => $id) {
            if (!FacultyId::isValid($id)) continue;
            foreach ($filled as $nameColumn => $name) {
                if ($nameColumn !== $idColumn && count(preg_split('/\s+/u', str_replace(',', ' ', $name), -1, PREG_SPLIT_NO_EMPTY) ?: []) >= 2)
                    return ['faculty_id' => $idColumn, 'name' => $nameColumn];
            }
        }
        return [];
    }

    private function firstSheetPath(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationshipsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relationshipsXml === false) throw new InvalidArgumentException('This is not a supported Excel workbook.');
        $workbook = simplexml_load_string($workbookXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        $relationships = simplexml_load_string($relationshipsXml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if ($workbook === false || $relationships === false) throw new InvalidArgumentException('The workbook structure could not be read.');
        $targets = [];
        foreach ($this->xpath($relationships, '//*[local-name()="Relationship"]') as $relationship) $targets[(string) $relationship['Id']] = (string) $relationship['Target'];
        $sheets = $this->xpath($workbook, '//*[local-name()="sheets"]/*[local-name()="sheet"]');
        if (!$sheets) throw new InvalidArgumentException('The workbook has no worksheets.');
        $attributes = $sheets[0]->attributes(self::RELATIONSHIP_NS);
        $target = str_replace('\\', '/', $targets[(string) ($attributes['id'] ?? '')] ?? '');
        if ($target === '') throw new InvalidArgumentException('The first worksheet could not be located.');
        return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.ltrim($target, '/');
    }

    /** @return array<int,string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) return [];
        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if ($document === false) return [];
        $strings = [];
        foreach ($this->xpath($document, '//*[local-name()="si"]') as $item) {
            $parts = [];
            foreach ($this->xpath($item, './/*[local-name()="t"]') as $text) $parts[] = (string) $text;
            $strings[] = implode('', $parts);
        }
        return $strings;
    }

    /** @param array<int,string> $shared */
    private function cellValue(SimpleXMLElement $cell, array $shared): string
    {
        $type = (string) ($cell['t'] ?? '');
        if ($type === 'inlineStr') {
            $parts = [];
            foreach ($this->xpath($cell, './*[local-name()="is"]//*[local-name()="t"]') as $text) $parts[] = (string) $text;
            return implode('', $parts);
        }
        $nodes = $this->xpath($cell, './*[local-name()="v"]');
        $value = isset($nodes[0]) ? (string) $nodes[0] : '';
        return $type === 's' ? ($shared[(int) $value] ?? '') : $value;
    }

    /** @return array<int,SimpleXMLElement> */
    private function xpath(SimpleXMLElement $node, string $expression): array
    {
        $matches = $node->xpath($expression);
        return is_array($matches) ? $matches : [];
    }

    private function columnIndex(string $reference): int
    {
        if (!preg_match('/^([A-Z]+)/i', $reference, $matches)) return 0;
        $index = 0;
        foreach (str_split(strtoupper($matches[1])) as $letter) $index = ($index * 26) + ord($letter) - 64;
        return max(0, $index - 1);
    }
}

final class FacultyImportService
{
    private const MAX_ROWS = 5000;

    public function __construct(private readonly PDO $db, private readonly FacultySpreadsheetReader $reader) {}

    public function preview(string $path): array
    {
        $records = $this->reader->read($path);
        if (!$records) throw new InvalidArgumentException('The workbook contains no faculty rows.');
        if (count($records) > self::MAX_ROWS) throw new InvalidArgumentException('A faculty import may contain at most 5,000 rows.');
        $existing = $this->existingKeys();
        $seenIds = [];
        $seenEmails = [];
        $rows = [];
        foreach ($records as $record) {
            $id = mb_strtoupper(trim($record['faculty_id']));
            $suppliedEmail = mb_strtolower(trim($record['email']));
            $needsEmail = $suppliedEmail === '';
            $email = $needsEmail && FacultyId::isValid($id) ? $this->pendingEmail($id) : $suppliedEmail;
            $name = trim((string) preg_replace('/\s+/u', ' ', $record['name']));
            $errors = [];
            if ($name === '' || count(preg_split('/\s+/u', str_replace(',', ' ', $name), -1, PREG_SPLIT_NO_EMPTY) ?: []) < 2) $errors[] = 'Enter the faculty member’s complete name.';
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
            if (!FacultyId::isValid($id)) $errors[] = FacultyId::FORMAT_MESSAGE;
            if (isset($seenIds[$id])) $errors[] = 'Faculty ID is duplicated in this workbook.';
            if (isset($seenEmails[$email])) $errors[] = 'Email is duplicated in this workbook.';
            if (isset($existing['ids'][$id]) || isset($existing['usernames'][$id])) $errors[] = 'Faculty ID already belongs to an account.';
            if (isset($existing['emails'][$email])) $errors[] = 'Email already belongs to an account.';
            if ($id !== '') $seenIds[$id] = true;
            if ($email !== '') $seenEmails[$email] = true;
            [$first, $middle, $last] = $this->splitName($name);
            $rows[] = [
                'source_row' => $record['source_row'], 'name' => $name, 'email' => $email, 'faculty_id' => $id,
                'needs_email' => $needsEmail,
                'first_name' => $first, 'middle_name' => $middle, 'last_name' => $last,
                'status' => $errors ? 'blocked' : 'ready', 'errors' => $errors,
            ];
        }
        return ['rows' => $rows, 'summary' => ['total' => count($rows), 'ready' => count(array_filter($rows, fn ($row) => $row['status'] === 'ready')), 'blocked' => count(array_filter($rows, fn ($row) => $row['status'] === 'blocked'))]];
    }

    public function apply(array $rows, int $actorId): array
    {
        $ready = array_values(array_filter($rows, fn ($row) => ($row['status'] ?? '') === 'ready'));
        if (!$ready) throw new InvalidArgumentException('This preview has no valid faculty rows to import.');
        $this->db->beginTransaction();
        try {
            $this->db->query('SELECT id FROM tbl_roles ORDER BY id FOR UPDATE')->fetchAll();
            $roleId = $this->requiredId("SELECT id FROM tbl_roles WHERE name='Faculty'", 'Faculty role');
            $statusId = $this->requiredId("SELECT id FROM tbl_user_statuses WHERE label='active'", 'Active user status');
            $existing = $this->existingKeys();
            $insert = $this->db->prepare('INSERT INTO tbl_users (role_id,id_number,first_name,middle_name,last_name,username,password,status,email,must_change_password,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
            $imported = 0;
            foreach ($ready as $row) {
                $id = mb_strtoupper(trim((string) $row['faculty_id']));
                $email = mb_strtolower(trim((string) $row['email']));
                if (!FacultyId::isValid($id) || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($existing['ids'][$id]) || isset($existing['usernames'][$id]) || isset($existing['emails'][$email])) continue;
                $password = FacultyInitialCredential::temporaryPassword($id, (string) $row['last_name']);
                $insert->execute([$roleId, $id, $row['first_name'], $row['middle_name'] ?: null, $row['last_name'], $id, password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]), $statusId, $email]);
                $existing['ids'][$id] = $existing['usernames'][$id] = $existing['emails'][$email] = true;
                $imported++;
            }
            $log = $this->db->prepare('INSERT INTO tbl_activity_logs(actor_id,action,description,created_at,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
            $log->execute([$actorId, 'faculty_imported', "$imported Faculty accounts were imported."]);
            $this->db->commit();
            return ['imported' => $imported, 'skipped' => count($ready) - $imported, 'must_change_password' => true, 'temporary_password_rule' => FacultyInitialCredential::RULE_DESCRIPTION];
        } catch (Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    /** @return array{ids:array<string,bool>,usernames:array<string,bool>,emails:array<string,bool>} */
    private function existingKeys(): array
    {
        $keys = ['ids' => [], 'usernames' => [], 'emails' => []];
        foreach ($this->db->query('SELECT id_number,username,email FROM tbl_users') as $user) {
            $id = mb_strtoupper(trim((string) $user['id_number']));
            $username = mb_strtoupper(trim((string) $user['username']));
            $email = mb_strtolower(trim((string) $user['email']));
            if ($id !== '') $keys['ids'][$id] = true;
            if ($username !== '') $keys['usernames'][$username] = true;
            if ($email !== '') $keys['emails'][$email] = true;
        }
        return $keys;
    }

    private function pendingEmail(string $facultyId): string
    {
        return 'faculty.'.mb_strtolower($facultyId).'@pending.invalid';
    }

    /** @return array{0:string,1:string,2:string} */
    private function splitName(string $name): array
    {
        if (str_contains($name, ',')) {
            [$last, $given] = array_map('trim', explode(',', $name, 2));
            return [$given, '', $last];
        }
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $last = array_pop($parts) ?: '';
        return [implode(' ', $parts), '', $last];
    }

    private function requiredId(string $sql, string $label): int
    {
        $value = $this->db->query($sql)->fetchColumn();
        if ($value === false) throw new RuntimeException("$label is missing from the database.");
        return (int) $value;
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) !== __FILE__) return;

try {
    $actor = AuthGuard::requireRole('SBO Adviser');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) JsonResponse::send(['success' => false, 'message' => 'Your session expired. Refresh and try again.'], 419);
    $service = new FacultyImportService((new Database())->connection(), new FacultySpreadsheetReader());
    $action = trim((string) ($_POST['action'] ?? $_GET['action'] ?? ''));
    if ($action === 'preview') {
        $upload = $_FILES['faculty_workbook'] ?? null;
        if (!is_array($upload) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Choose an Excel faculty workbook to preview.');
        if ((int) $upload['size'] > 10 * 1024 * 1024) throw new InvalidArgumentException('The workbook may not exceed 10 MB.');
        if (mb_strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION)) !== 'xlsx') throw new InvalidArgumentException('Upload an .xlsx workbook.');
        $preview = $service->preview((string) $upload['tmp_name']);
        $token = bin2hex(random_bytes(24));
        SessionManager::start();
        $_SESSION['faculty_import_preview'] = ['token' => $token, 'created_at' => time(), 'rows' => $preview['rows']];
        JsonResponse::send(['success' => true, 'message' => 'Faculty workbook validated.', 'data' => $preview + ['token' => $token]]);
    }
    $input = json_decode((string) file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (($input['action'] ?? '') !== 'apply') throw new InvalidArgumentException('Unknown faculty import action.');
    SessionManager::start();
    $preview = $_SESSION['faculty_import_preview'] ?? null;
    if (!is_array($preview) || !hash_equals((string) ($preview['token'] ?? ''), (string) ($input['token'] ?? '')) || time() - (int) ($preview['created_at'] ?? 0) > 1800) throw new InvalidArgumentException('The faculty preview expired. Preview the workbook again.');
    $result = $service->apply((array) $preview['rows'], (int) $actor['id']);
    unset($_SESSION['faculty_import_preview']);
    JsonResponse::send(['success' => true, 'message' => $result['imported'].' Faculty account'.($result['imported'] === 1 ? '' : 's').' imported.', 'data' => $result]);
} catch (InvalidArgumentException $error) {
    JsonResponse::send(['success' => false, 'message' => $error->getMessage()], 422);
} catch (Throwable $error) {
    error_log($error->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'Faculty import failed.'], 500);
}
