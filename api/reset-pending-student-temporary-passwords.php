<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/StudentInitialCredential.php';

$apply = in_array('--apply', $argv, true);
$db = (new Database())->connection();
$students = $db->query("SELECT u.id,u.id_number,u.last_name
    FROM tbl_users u
    JOIN tbl_roles r ON r.id=u.role_id AND r.name='Student'
    WHERE u.must_change_password=1
    ORDER BY u.id")->fetchAll();

$prepared = [];
$skipped = [];
foreach ($students as $student) {
    try {
        $temporaryPassword = StudentInitialCredential::temporaryPassword(
            (string)($student['id_number'] ?? ''),
            (string)($student['last_name'] ?? ''),
        );
        $prepared[] = [
            'id' => (int)$student['id'],
            'hash' => $apply ? password_hash($temporaryPassword, PASSWORD_BCRYPT, ['cost' => 10]) : null,
        ];
    } catch (InvalidArgumentException $error) {
        $skipped[] = ['id' => (int)$student['id'], 'reason' => $error->getMessage()];
    }
}

$updated = 0;
if ($apply && $prepared) {
    $db->beginTransaction();
    try {
        $update = $db->prepare('UPDATE tbl_users SET password=?,updated_at=CURRENT_TIMESTAMP WHERE id=? AND must_change_password=1');
        foreach ($prepared as $student) {
            $update->execute([$student['hash'], $student['id']]);
            $updated += $update->rowCount();
        }
        $db->commit();
    } catch (Throwable $error) {
        if ($db->inTransaction()) $db->rollBack();
        throw $error;
    }
}

echo json_encode([
    'mode' => $apply ? 'applied' : 'dry-run',
    'eligible_accounts' => count($prepared),
    'updated_accounts' => $updated,
    'skipped_accounts' => count($skipped),
    'skipped' => $skipped,
    'temporary_password_rule' => StudentInitialCredential::RULE_DESCRIPTION,
    'credentials_disclosed' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
