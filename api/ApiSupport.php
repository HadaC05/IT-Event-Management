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
    public const FORMAT_MESSAGE = 'Faculty ID numbers must use 2x-xxx-X through 2x-xxxxxx-X (for example, 23-2324-F).';

    public static function isValid(string $value): bool
    {
        return preg_match('/^2\d-\d{3,6}-[A-Z]$/', mb_strtoupper(trim($value))) === 1;
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

final class RequestSecurity
{
    private const MAX_DEPTH = 12;
    private const MAX_VALUES = 10000;
    private const MAX_STRING_BYTES = 100000;
    private const MAX_JSON_BYTES = 2 * 1024 * 1024;

    public static function guardGlobals(): void
    {
        try {
            self::assertValid($_GET, 'query');
            self::assertValid($_POST, 'form');
            $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
            if (str_contains($contentType, 'application/json')) {
                $raw = (string) file_get_contents('php://input');
                if (strlen($raw) > self::MAX_JSON_BYTES) throw new InvalidArgumentException('The request body is too large.');
                if ($raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) throw new InvalidArgumentException('The request body must contain valid JSON.');
                    self::assertValid($decoded, 'JSON');
                }
            }
        } catch (InvalidArgumentException $exception) {
            JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 400);
        }
    }

    public static function assertValid(array $input, string $source = 'request'): void
    {
        $count = 0;
        self::walk($input, $source, 0, $count);
    }

    private static function walk(array $values, string $source, int $depth, int &$count): void
    {
        if ($depth > self::MAX_DEPTH) throw new InvalidArgumentException("The {$source} data is nested too deeply.");
        foreach ($values as $key => $value) {
            if (++$count > self::MAX_VALUES) throw new InvalidArgumentException("The {$source} contains too many values.");
            self::assertString((string) $key, "{$source} field name", 128);
            if (is_array($value)) {
                self::walk($value, $source, $depth + 1, $count);
                continue;
            }
            if (is_object($value) || is_resource($value)) throw new InvalidArgumentException("The {$source} contains an unsupported value.");
            if (is_string($value)) self::assertString($value, "{$source} value", self::MAX_STRING_BYTES);
        }
    }

    private static function assertString(string $value, string $label, int $maximumBytes): void
    {
        if (strlen($value) > $maximumBytes) throw new InvalidArgumentException("A {$label} is too long.");
        if (!mb_check_encoding($value, 'UTF-8')) throw new InvalidArgumentException("A {$label} is not valid UTF-8.");
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) throw new InvalidArgumentException("A {$label} contains unsupported control characters.");
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

if (PHP_SAPI !== 'cli') RequestSecurity::guardGlobals();

