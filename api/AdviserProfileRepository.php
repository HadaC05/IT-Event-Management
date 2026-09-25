<?php

declare(strict_types=1);

final class AdviserProfileRepository
{
    private const PHOTO_DIRECTORY = 'assets/uploads/profiles';

    public function __construct(private readonly PDO $db) {}

    public function profile(int $userId): array
    {
        $statement = $this->db->prepare('SELECT first_name,middle_name,last_name,username,email,bio,profile_photo_path FROM tbl_users WHERE id=? LIMIT 1');
        $statement->execute([$userId]);
        $profile = $statement->fetch();
        if (!$profile) throw new InvalidArgumentException('Adviser account not found.');
        return $profile;
    }

    public function update(int $userId, array $input, mixed $photo): array
    {
        $first = trim((string) ($input['first_name'] ?? ''));
        $middle = trim((string) ($input['middle_name'] ?? ''));
        $last = trim((string) ($input['last_name'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $bio = trim((string) ($input['bio'] ?? ''));
        if ($first === '' || $last === '' || mb_strlen($first) > 100 || mb_strlen($middle) > 100 || mb_strlen($last) > 100 || mb_strlen($bio) > 280 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter valid name, email, and profile details.');
        }

        $path = $this->storePhoto($photo);
        $oldPath = '';
        try {
            $this->db->beginTransaction();
            $current = $this->db->prepare('SELECT profile_photo_path FROM tbl_users WHERE id=? FOR UPDATE');
            $current->execute([$userId]);
            $row = $current->fetch();
            if (!$row) throw new InvalidArgumentException('Adviser account not found.');
            $oldPath = (string) ($row['profile_photo_path'] ?? '');
            $duplicate = $this->db->prepare('SELECT id FROM tbl_users WHERE email=? AND id<>? LIMIT 1');
            $duplicate->execute([$email, $userId]);
            if ($duplicate->fetchColumn()) throw new InvalidArgumentException('Email is already in use.');
            $sql = 'UPDATE tbl_users SET first_name=?,middle_name=?,last_name=?,email=?,bio=?,updated_at=CURRENT_TIMESTAMP'.($path ? ',profile_photo_path=?' : '').' WHERE id=?';
            $parameters = [$first, $middle ?: null, $last, $email, $bio ?: null];
            if ($path) $parameters[] = $path;
            $parameters[] = $userId;
            $this->db->prepare($sql)->execute($parameters);
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($path) $this->removePhoto($path);
            throw $exception;
        }
        if ($path && $oldPath && $oldPath !== $path) $this->removePhoto($oldPath);
        return $this->profile($userId);
    }

    private function storePhoto(mixed $file): ?string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Choose a profile picture no larger than 5 MB.');
        }
        $temporary = (string) ($file['tmp_name'] ?? '');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
        $extension = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
        $dimensions = @getimagesize($temporary);
        if (!$extension || !$dimensions || $dimensions[0] > 4096 || $dimensions[1] > 4096) {
            throw new InvalidArgumentException('Use a JPG, PNG, or WebP image up to 4096 × 4096 pixels.');
        }
        $directory = dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::PHOTO_DIRECTORY);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) throw new RuntimeException('The profile picture directory could not be created.');
        $path = self::PHOTO_DIRECTORY.'/'.bin2hex(random_bytes(20)).'.'.$extension;
        if (!move_uploaded_file($temporary, dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path))) {
            throw new RuntimeException('The profile picture could not be stored.');
        }
        return $path;
    }

    private function removePhoto(string $path): void
    {
        if (!str_starts_with($path, self::PHOTO_DIRECTORY.'/')) return;
        $root = realpath(dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::PHOTO_DIRECTORY));
        $file = realpath(dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path));
        if ($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
    }
}
