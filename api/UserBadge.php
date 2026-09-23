<?php

declare(strict_types=1);

final class UserBadge
{
    private const DEVELOPERS = [
        '02-2324-01129' => true,  // Brian Ragasi
        '02-2122-030923' => true, // Micah Dusil Lago
        '02-2324-13843' => true,  // Hannah Cubillan
        '02-2324-09736' => true,  // Jessie James Parajes
    ];

    public static function forIdNumber(?string $idNumber): ?string
    {
        return isset(self::DEVELOPERS[strtoupper(trim((string) $idNumber))]) ? 'Dev' : null;
    }
}
