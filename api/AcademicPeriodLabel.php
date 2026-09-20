<?php

declare(strict_types=1);

/**
 * Keeps academic-period values stable in storage and consistent in the UI.
 * School year and semester are separate concepts: "2026-2027" is the school
 * year, while "First Semester" is the term.
 */
final class AcademicPeriodLabel
{
    public static function parse(string $value): array
    {
        $clean = trim(preg_replace('/\s+/u', ' ', str_replace(['–', '—'], '-', $value)) ?? '');
        if (!preg_match('/(?:SY\s*)?(\d{2,4})\s*[-\/]\s*(\d{2,4})/iu', $clean, $match)) {
            throw new InvalidArgumentException('Use a school year such as 2026-2027 and include the semester.');
        }

        $start = self::fourDigitYear($match[1], null);
        $end = self::fourDigitYear($match[2], $start);
        if ($end !== $start + 1) {
            throw new InvalidArgumentException('The school year must cover consecutive years, such as 2026-2027.');
        }

        $upper = mb_strtoupper($clean);
        $termCode = null;
        if (preg_match('/\b(?:SEM(?:ESTER)?\s*)?(?:I|1ST|FIRST|1)\b/u', $upper)) {
            $termCode = 'first_semester';
        }
        if (preg_match('/\b(?:SEM(?:ESTER)?\s*)?(?:II|2ND|SECOND|2)\b/u', $upper)) {
            $termCode = 'second_semester';
        }
        if (preg_match('/\bSUMMER(?:\s+TERM)?\b/u', $upper)) {
            $termCode = 'summer';
        }
        if ($termCode === null) {
            throw new InvalidArgumentException('The academic period must identify First Semester, Second Semester, or Summer Term.');
        }

        $termName = self::termName($termCode);
        $schoolYear = $start.'-'.$end;
        return [
            'school_year_label' => $schoolYear,
            'term_code' => $termCode,
            'term_name' => $termName,
            'label' => self::display($schoolYear, $termName),
        ];
    }

    public static function display(string $schoolYear, string $termName = ''): string
    {
        $year = trim($schoolYear);
        if (!str_starts_with(mb_strtoupper($year), 'SY ')) {
            $year = 'SY '.$year;
        }
        return trim($year.($termName !== '' ? ' · '.self::termName($termName) : ''));
    }

    public static function termName(string $value): string
    {
        return match (mb_strtolower(trim($value))) {
            'first_semester', 'first semester', 'semester i', 'sem i' => 'First Semester',
            'second_semester', 'second semester', 'semester ii', 'sem ii' => 'Second Semester',
            'summer', 'summer term' => 'Summer Term',
            default => trim($value),
        };
    }

    private static function fourDigitYear(string $value, ?int $start): int
    {
        $year = (int) $value;
        if (strlen($value) === 4) return $year;
        if ($start !== null) {
            $century = intdiv($start, 100) * 100;
            $candidate = $century + $year;
            return $candidate < $start ? $candidate + 100 : $candidate;
        }
        return ($year >= 70 ? 1900 : 2000) + $year;
    }
}
