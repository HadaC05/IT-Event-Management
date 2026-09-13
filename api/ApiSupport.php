<?php

declare(strict_types=1);

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
    private const COOKIE_PATH = '/IT-Event-Management/';

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_name(self::COOKIE_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => self::COOKIE_PATH,
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
            'path' => self::COOKIE_PATH,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

final class AuthGuard
{
    public static function requireRole(string $role): array
    {
        SessionManager::start();
        $user = $_SESSION['user'] ?? null;

        if (!is_array($user)) {
            JsonResponse::send(['success' => false, 'message' => 'Authentication required.'], 401);
        }

        if (($user['role'] ?? null) !== $role) {
            JsonResponse::send(['success' => false, 'message' => 'You are not authorized to access this resource.'], 403);
        }

        return $user;
    }
}

