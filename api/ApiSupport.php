<?php

declare(strict_types=1);

final class PageSize
{
    public static function from(array $input, int $default = 10): int
    {
        $requested = filter_var($input['per_page'] ?? null, FILTER_VALIDATE_INT);
        return in_array($requested, [5, 10, 20], true) ? $requested : $default;
    }
}

final class StudentId
{
    public const FORMAT_MESSAGE = 'Student ID numbers must use 02-xxxx-xxxxx or 02-xxxx-xxxxxx.';

    public static function isValid(string $value): bool
    {
        return preg_match('/^02-\d{4}-\d{5,6}$/', trim($value)) === 1;
    }
}

final class FacultyId
{
    public const FORMAT_MESSAGE = 'Faculty ID numbers must use the format 2x-xxx-F.';

    public static function isValid(string $value): bool
    {
        return preg_match('/^2\d-\d{3}-F$/', mb_strtoupper(trim($value))) === 1;
    }
}

final class JsonResponse
{
    public static function send(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        exit;
    }
}

final class SessionManager
{
    private const COOKIE_NAME = 'cite_event_session';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_name(self::COOKIE_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => self::cookiePath(),
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function csrfToken(): string
    {
        self::start();
        return (string) $_SESSION['csrf_token'];
    }

    public static function validateCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals(self::csrfToken(), $token);
    }

    public static function rememberCurrentSession(): void
    {
        setcookie(session_name(), session_id(), [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => self::cookiePath(),
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function cookiePath(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/'));
        $applicationPath = dirname(dirname($scriptName));

        if ($applicationPath === '.' || $applicationPath === '/') {
            return '/';
        }

        return '/'.trim($applicationPath, '/').'/';
    }
}

final class AuthGuard
{
    public static function requireAnyRole(array $roles): array
    {
        SessionManager::start();
        $user = $_SESSION['user'] ?? null;

        if (!is_array($user)) {
            JsonResponse::send(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        if (!in_array($user['role'] ?? null, $roles, true)) {
            JsonResponse::send(['success' => false, 'message' => 'You are not authorized to access this resource.'], 403);
        }

        if (!empty($user['must_change_password'])) {
            JsonResponse::send([
                'success' => false,
                'code' => 'password_change_required',
                'message' => 'Change your temporary password before using the system.',
            ], 403);
        }

        return $user;
    }

    public static function requireRole(string $role): array
    {
        return self::requireAnyRole([$role]);
    }
}

