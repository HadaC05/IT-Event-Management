<?php

declare(strict_types=1);

require_once __DIR__.'/db_connect.php';
require_once __DIR__.'/ApiSupport.php';
require_once __DIR__.'/AdviserProfileRepository.php';

$actor = AuthGuard::requireRole('SBO Adviser');
$repository = new AdviserProfileRepository((new Database())->connection());

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') JsonResponse::send(['success'=>true,'data'=>$repository->profile((int) $actor['id'])]);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') JsonResponse::send(['success'=>false,'message'=>'Method not allowed.'], 405);
    if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) JsonResponse::send(['success'=>false,'message'=>'Your session expired. Refresh and try again.'], 403);
    $profile = $repository->update((int) $actor['id'], $_POST, $_FILES['photo'] ?? null);
    foreach (['first_name','middle_name','last_name','email','profile_photo_path'] as $key) $_SESSION['user'][$key] = $profile[$key];
    $_SESSION['user']['full_name'] = trim(implode(' ', array_filter([$profile['first_name'],$profile['middle_name'],$profile['last_name']])));
    JsonResponse::send(['success'=>true,'data'=>$profile,'message'=>'Profile updated.']);
} catch (InvalidArgumentException $exception) {
    JsonResponse::send(['success'=>false,'message'=>$exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success'=>false,'message'=>'The adviser profile could not be saved.'], 500);
}
