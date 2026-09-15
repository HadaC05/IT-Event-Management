<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__.'/sbo-attendance.php';

$repository = (new ReflectionClass(SboAttendanceRepository::class))->newInstanceWithoutConstructor();
$insideBox = new ReflectionMethod(SboAttendanceRepository::class, 'insideBox');
$distance = new ReflectionMethod(SboAttendanceRepository::class, 'distance');

$centerLatitude = 8.4699237;
$centerLongitude = 124.6342058;
$radius = 100.0;
$cornerLatitude = $centerLatitude + (90 / 111320);
$cornerLongitude = $centerLongitude + (90 / (111320 * cos(deg2rad($centerLatitude))));

$inside = $insideBox->invoke($repository, $cornerLatitude, $cornerLongitude, $centerLatitude, $centerLongitude, $radius);
$directDistance = $distance->invoke($repository, $cornerLatitude, $cornerLongitude, $centerLatitude, $centerLongitude);
$outside = $insideBox->invoke(
    $repository,
    $centerLatitude + (101 / 111320),
    $centerLongitude,
    $centerLatitude,
    $centerLongitude,
    $radius
);

if (!$inside || $directDistance <= $radius || $outside) {
    throw new RuntimeException('FAIL: The saved square boundary calculation is incorrect.');
}

echo 'PASS: A 90 m north/east corner is inside the 100 m square while its direct distance is '
    .round($directDistance, 2)." m.\n";
