<?php

declare(strict_types=1);

final class AttendanceScanWindows
{
    private const PREFIXES = ['whole_day' => 'whole_day', 'morning' => 'morning', 'afternoon' => 'afternoon'];

    public static function forSession(array $schedule, string $session): array
    {
        $prefix = self::PREFIXES[$session] ?? null;
        if (!$prefix) throw new InvalidArgumentException('Invalid attendance session.');
        $date = (string) $schedule['schedule_date'];
        $zone = new DateTimeZone('Asia/Manila');
        $fields = [
            'in' => [$prefix.'_in_time', $prefix.'_in_close_time'],
            'out' => [$prefix.'_out_open_time', $prefix.'_out_time'],
        ];
        $windows = [];
        foreach ($fields as $phase => [$openField, $closeField]) {
            $open = $schedule[$openField] ?? null;
            $close = $schedule[$closeField] ?? null;
            if (!$open || !$close) throw new DomainException('This attendance window is not configured.');
            $windows[$phase] = [
                'opens' => new DateTimeImmutable($date.' '.$open, $zone),
                'closes' => new DateTimeImmutable($date.' '.$close, $zone),
            ];
            if ($windows[$phase]['opens'] >= $windows[$phase]['closes'])
                throw new DomainException('This attendance window has invalid times. Ask the adviser to edit it.');
        }
        if ($windows['in']['closes'] > $windows['out']['opens'])
            throw new DomainException('Time In and Time Out windows overlap. Ask the adviser to edit them.');
        return $windows;
    }

    public static function isOpen(array $window, DateTimeImmutable $now): bool
    {
        return $now >= $window['opens'] && $now < $window['closes'];
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    }
}
