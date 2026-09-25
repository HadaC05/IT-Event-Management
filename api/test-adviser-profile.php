<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/AdviserProfileRepository.php';

$live = (new Database())->connection();
$scratch = 'adviser_profile_test_'.bin2hex(random_bytes(4));
if (!preg_match('/^adviser_profile_test_[0-9a-f]{8}$/', $scratch)) throw new RuntimeException('Invalid scratch database name.');
$live->exec("CREATE DATABASE `$scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $db = (new Database(null, $scratch))->connection();
    $db->exec('CREATE TABLE tbl_users (id INT PRIMARY KEY,first_name VARCHAR(100),middle_name VARCHAR(100),last_name VARCHAR(100),username VARCHAR(100),email VARCHAR(255) UNIQUE,bio VARCHAR(280),profile_photo_path VARCHAR(255),updated_at TIMESTAMP NULL)');
    $db->exec("INSERT INTO tbl_users(id,first_name,last_name,username,email) VALUES(1,'SBO','Adviser','adviser','adviser@test.invalid'),(2,'Other','Person','other','other@test.invalid')");
    $repository = new AdviserProfileRepository($db);
    $updated = $repository->update(1, ['first_name'=>'SBO','middle_name'=>'Test','last_name'=>'Adviser','email'=>'new-adviser@test.invalid','bio'=>'Advising the community.'], null);
    if ($updated['middle_name'] !== 'Test' || $updated['email'] !== 'new-adviser@test.invalid' || $updated['bio'] !== 'Advising the community.') throw new RuntimeException('Adviser profile changes were not saved.');
    try {
        $repository->update(1, ['first_name'=>'SBO','last_name'=>'Adviser','email'=>'other@test.invalid'], null);
        throw new RuntimeException('Duplicate email was accepted.');
    } catch (InvalidArgumentException $exception) {
        if (!str_contains($exception->getMessage(), 'already in use')) throw $exception;
    }
    if ($repository->profile(1)['email'] !== 'new-adviser@test.invalid') throw new RuntimeException('Duplicate email changed the saved profile.');
    echo "PASS: Adviser can update profile details; duplicate email is rejected without changing the profile.\n";
} finally {
    $live->exec("DROP DATABASE `$scratch`");
}
