<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/MediaRepository.php';

final class StudentProfileRepository
{
    private const PROFILE_DIRECTORY = 'assets/uploads/profiles';

    public function __construct(private readonly PDO $db) {}

    public function updatePhoto(int $userId, mixed $file): string
    {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Choose a profile picture.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int) ($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new InvalidArgumentException('Choose a profile picture no larger than 5 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extension = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime] ?? null;
        $dimensions = @getimagesize((string) $file['tmp_name']);
        if (!$extension || !$dimensions || $dimensions[0] > 4096 || $dimensions[1] > 4096) {
            throw new InvalidArgumentException('Use a JPG, PNG, or WebP image up to 4096 × 4096 pixels.');
        }

        $directory = dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::PROFILE_DIRECTORY);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('The profile picture directory could not be created.');
        }
        $path = self::PROFILE_DIRECTORY.'/'.bin2hex(random_bytes(20)).'.'.$extension;
        $absolutePath = dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (!move_uploaded_file((string) $file['tmp_name'], $absolutePath)) {
            throw new RuntimeException('The profile picture could not be stored.');
        }

        $oldPath = '';
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('SELECT profile_photo_path FROM tbl_users WHERE id=? FOR UPDATE');
            $statement->execute([$userId]);
            $oldPath = (string) ($statement->fetchColumn() ?: '');
            $update = $this->db->prepare('UPDATE tbl_users SET profile_photo_path=?,updated_at=CURRENT_TIMESTAMP WHERE id=?');
            $update->execute([$path, $userId]);
            if ($update->rowCount() !== 1) throw new RuntimeException('The profile picture was not updated.');
            $this->db->commit();
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            @unlink($absolutePath);
            throw $exception;
        }

        $this->removeOldPhoto($oldPath, $path);
        return $path;
    }

    private function removeOldPhoto(string $oldPath, string $newPath): void
    {
        if ($oldPath === '' || $oldPath === $newPath || !str_starts_with($oldPath, self::PROFILE_DIRECTORY.'/')) return;
        $root = realpath(dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::PROFILE_DIRECTORY));
        $file = realpath(dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $oldPath));
        if ($root && $file && str_starts_with($file, $root.DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
    }
}

$actor = AuthGuard::requireRole('Student');
$db = (new Database())->connection();
$media = new MediaRepository($db);
$profiles = new StudentProfileRepository($db);

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        JsonResponse::send(['success'=>true,'data'=>$media->studentProfileData($actor)]);
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'], 405);
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
        JsonResponse::send(['success'=>false,'message'=>'Your session expired. Refresh and try again.'], 403);
    }
    $path = $profiles->updatePhoto((int) $actor['id'], $_FILES['photo'] ?? null);
    $_SESSION['user']['profile_photo_path'] = $path;
    JsonResponse::send(['success'=>true,'message'=>'Profile picture updated.','profile_photo_path'=>$path]);
} catch (InvalidArgumentException $exception) {
    JsonResponse::send(['success'=>false,'message'=>$exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success'=>false,'message'=>'The student profile could not be updated.'], 500);
}
