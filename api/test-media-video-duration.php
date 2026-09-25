<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/MediaVideoDuration.php';

function videoAtom(string $type, string $payload): string { return pack('N', strlen($payload) + 8).$type.$payload; }
function mp4WithDuration(int $seconds): string
{
    $header = "\0\0\0\0".pack('N', 0).pack('N', 0).pack('N', 1000).pack('N', $seconds * 1000);
    return videoAtom('ftyp', 'isom').videoAtom('moov', videoAtom('mvhd', $header));
}
function webmWithDuration(int $seconds): string
{
    $scale = hex2bin('2ad7b1830f4240');
    $duration = hex2bin('448988').pack('E', (float) $seconds * 1000);
    $info = hex2bin('1549a966').chr(0x80 | strlen($scale.$duration)).$scale.$duration;
    return hex2bin('1a45dfa38018538067').chr(0x80 | strlen($info)).$info;
}

$path = tempnam(sys_get_temp_dir(), 'cite_video_duration_');
if ($path === false) throw new RuntimeException('Could not create the video test file.');
try {
    foreach (['video/mp4'=> 'mp4WithDuration', 'video/webm'=>'webmWithDuration'] as $mime=>$fixture) {
        foreach ([299, 300, 301] as $seconds) {
            file_put_contents($path, $fixture($seconds));
            $actual = MediaVideoDuration::seconds($path, $mime);
            if ($actual === null || abs($actual - $seconds) > .001) throw new RuntimeException("Incorrect $mime duration for $seconds seconds.");
            if (($actual <= MediaVideoDuration::MAX_SECONDS) !== ($seconds <= 300)) throw new RuntimeException("Incorrect $mime cutoff for $seconds seconds.");
        }
    }
    file_put_contents($path, 'not a video');
    if (MediaVideoDuration::seconds($path, 'video/mp4') !== null) throw new RuntimeException('Invalid video metadata should be rejected.');
    echo "PASS: MP4 and WebM durations enforce the five-minute boundary.\n";
} finally {
    @unlink($path);
}
