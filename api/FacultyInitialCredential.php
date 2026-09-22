<?php

declare(strict_types=1);

final class FacultyInitialCredential
{
    public const RULE_DESCRIPTION = 'Complete Faculty ID followed immediately by the surname in uppercase, without spaces or punctuation';

    public static function temporaryPassword(string $facultyId, string $lastName): string
    {
        $facultyId = mb_strtoupper(trim($facultyId));
        $surname = trim($lastName);
        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $surname);
            if (is_string($ascii)) $surname = $ascii;
        }
        $surname = mb_strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $surname));
        if ($facultyId === '' || $surname === '') throw new InvalidArgumentException('Faculty ID and surname are required to create the temporary password.');
        return $facultyId.$surname;
    }
}
