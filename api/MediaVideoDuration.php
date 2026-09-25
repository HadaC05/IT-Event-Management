<?php

declare(strict_types=1);

final class MediaVideoDuration
{
    public const MAX_SECONDS = 300;

    public static function seconds(string $path, string $mime): ?float
    {
        $size = @filesize($path);
        if ($size === false || $size < 16 || $size > 25 * 1024 * 1024) return null;
        $handle = @fopen($path, 'rb');
        if ($handle === false) return null;
        try {
            return $mime === 'video/webm' ? self::webmSeconds($handle, $size) : self::quickTimeSeconds($handle, $size);
        } finally {
            fclose($handle);
        }
    }

    private static function quickTimeSeconds($handle, int $size): ?float
    {
        $position = 0;
        while ($position + 8 <= $size) {
            fseek($handle, $position);
            $header = fread($handle, 8);
            if (strlen($header) !== 8) return null;
            $length = unpack('N', substr($header, 0, 4))[1];
            $headerSize = 8;
            if ($length === 1) {
                $extended = fread($handle, 8);
                if (strlen($extended) !== 8) return null;
                $length = self::uint($extended);
                $headerSize = 16;
            } elseif ($length === 0) $length = $size - $position;
            if ($length < $headerSize || $position + $length > $size) return null;
            if (substr($header, 4, 4) === 'moov') return self::movieHeaderSeconds($handle, $position + $headerSize, $position + $length);
            $position += $length;
        }
        return null;
    }

    private static function movieHeaderSeconds($handle, int $position, int $end): ?float
    {
        while ($position + 8 <= $end) {
            fseek($handle, $position);
            $header = fread($handle, 8);
            if (strlen($header) !== 8) return null;
            $length = unpack('N', substr($header, 0, 4))[1];
            $headerSize = 8;
            if ($length === 1) {
                $extended = fread($handle, 8);
                if (strlen($extended) !== 8) return null;
                $length = self::uint($extended);
                $headerSize = 16;
            } elseif ($length === 0) $length = $end - $position;
            if ($length < $headerSize || $position + $length > $end) return null;
            if (substr($header, 4, 4) === 'mvhd') {
                $payload = fread($handle, min(32, $length - $headerSize));
                if (strlen($payload) < 20) return null;
                $version = ord($payload[0]);
                if ($version === 0) {
                    $timescale = self::uint(substr($payload, 12, 4));
                    $duration = self::uint(substr($payload, 16, 4));
                } elseif ($version === 1 && strlen($payload) >= 32) {
                    $timescale = self::uint(substr($payload, 20, 4));
                    $duration = self::uint(substr($payload, 24, 8));
                } else return null;
                return $timescale > 0 && $duration > 0 ? $duration / $timescale : null;
            }
            $position += $length;
        }
        return null;
    }

    private static function webmSeconds($handle, int $size): ?float
    {
        $position = 0;
        while ($position < $size) {
            $element = self::ebmlElement($handle, $position, $size);
            if ($element === null) return null;
            if ($element['id'] === 0x18538067) return self::webmSegmentSeconds($handle, $element['start'], $element['end']);
            $position = $element['end'];
        }
        return null;
    }

    private static function webmSegmentSeconds($handle, int $position, int $end): ?float
    {
        while ($position < $end) {
            $element = self::ebmlElement($handle, $position, $end);
            if ($element === null) return null;
            if ($element['id'] === 0x1549A966) return self::webmInfoSeconds($handle, $element['start'], $element['end']);
            $position = $element['end'];
        }
        return null;
    }

    private static function webmInfoSeconds($handle, int $position, int $end): ?float
    {
        $timecodeScale = 1000000;
        $duration = null;
        while ($position < $end) {
            $element = self::ebmlElement($handle, $position, $end);
            if ($element === null) return null;
            $length = $element['end'] - $element['start'];
            if ($element['id'] === 0x2AD7B1 && $length >= 1 && $length <= 8) {
                fseek($handle, $element['start']);
                $timecodeScale = self::uint(fread($handle, $length));
            } elseif ($element['id'] === 0x4489 && in_array($length, [4, 8], true)) {
                fseek($handle, $element['start']);
                $raw = fread($handle, $length);
                if (strlen($raw) !== $length) return null;
                $duration = unpack($length === 4 ? 'G' : 'E', $raw)[1];
            }
            $position = $element['end'];
        }
        $seconds = $duration === null ? null : $duration * $timecodeScale / 1000000000;
        return $seconds !== null && is_finite($seconds) && $seconds > 0 ? $seconds : null;
    }

    private static function ebmlElement($handle, int $position, int $end): ?array
    {
        fseek($handle, $position);
        $id = self::vint($handle, false);
        $length = self::vint($handle, true);
        if ($id === null || $length === false) return null;
        $start = ftell($handle);
        if ($start > $end) return null;
        $elementEnd = $length === null ? $end : $start + $length;
        return $elementEnd <= $end && $elementEnd >= $start ? ['id'=>$id,'start'=>$start,'end'=>$elementEnd] : null;
    }

    private static function vint($handle, bool $size): int|false|null
    {
        $first = fread($handle, 1);
        if ($first === false || $first === '') return false;
        $byte = ord($first);
        $mask = 0x80;
        $length = 1;
        while (($byte & $mask) === 0 && $length <= ($size ? 8 : 4)) { $mask >>= 1; $length++; }
        if ($mask === 0 || $length > ($size ? 8 : 4)) return false;
        $value = $size ? $byte & ($mask - 1) : $byte;
        for ($index = 1; $index < $length; $index++) {
            $next = fread($handle, 1);
            if ($next === false || $next === '') return false;
            $value = ($value << 8) | ord($next);
        }
        if ($size && $value === (1 << (7 * $length)) - 1) return null;
        return $value;
    }

    private static function uint(string $bytes): int
    {
        $value = 0;
        for ($index = 0, $count = strlen($bytes); $index < $count; $index++) $value = ($value << 8) | ord($bytes[$index]);
        return $value;
    }
}
