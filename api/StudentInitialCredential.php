<?php

declare(strict_types=1);

/**
 * One deterministic credential rule for first-time Student access.
 *
 * Username: the complete Student ID.
 * Temporary password: complete Student ID + normalized uppercase surname.
 * Example: 02-2425-005055SALINAS.
 *
 * The password is temporary only. Student accounts must replace it immediately
 * after their first successful sign-in.
 */
final class StudentInitialCredential
{
    public const RULE_DESCRIPTION = 'Complete Student ID followed immediately by the surname in uppercase, without spaces or punctuation';

    public static function temporaryPassword(string $studentId, string $lastName): string
    {
        $studentId = trim($studentId);
        $surname = self::normalizeSurname($lastName);
        if ($studentId === '') {
            throw new InvalidArgumentException('A Student ID is required to create the temporary password.');
        }
        if ($surname === '') {
            throw new InvalidArgumentException('A surname is required to create the temporary password.');
        }
        return $studentId.$surname;
    }

    public static function normalizeSurname(string $lastName): string
    {
        $value = trim($lastName);
        if (function_exists('iconv')) {
            $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if (is_string($ascii)) $value = $ascii;
        }
        return mb_strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $value));
    }
}
