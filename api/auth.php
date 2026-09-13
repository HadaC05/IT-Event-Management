<?php

declare(strict_types=1);

require_once __DIR__.'/Database.php';
require_once __DIR__.'/ApiSupport.php';

final class UserRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function findLoginCandidates(string $login): array
    {
        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;
        $column = $isEmail ? 'users.email' : 'users.username';
        $statement = $this->database->prepare(
            "SELECT
                users.id,
                users.username,
                users.email,
                users.password,
                users.first_name,
                users.middle_name,
                users.last_name,
                users.must_change_password,
                roles.name AS role,
                user_statuses.label AS status
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id
             LEFT JOIN user_statuses ON user_statuses.id = users.status
             WHERE {$column} = :login"
        );
        $statement->execute(['login' => $login]);

        return $statement->fetchAll();
    }
}

final class AuthController
{
    private const MAX_ATTEMPTS = 6;
    private const WINDOW_SECONDS = 60;

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function login(array $input): array
    {
        $this->guardRateLimit();
        $login = trim((string) ($input['login'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($login === '' || mb_strlen($login) > 255 || $password === '') {
            $this->recordFailure();
            throw new InvalidArgumentException('Enter your username/email and password.');
        }

        $account = null;
        foreach ($this->users->findLoginCandidates($login) as $candidate) {
            if (password_verify($password, (string) $candidate['password'])) {
                $account = $candidate;
                break;
            }
        }

        if ($account === null) {
            $this->recordFailure();
            throw new InvalidArgumentException('The username/email or password is incorrect.');
        }

        if (strtolower((string) $account['status']) === 'inactive') {
            $this->recordFailure();
            throw new InvalidArgumentException('This account is inactive. Please contact the SBO Adviser.');
        }

        unset($account['password']);
        $account['id'] = (int) $account['id'];
        $account['must_change_password'] = (bool) $account['must_change_password'];
        $account['full_name'] = trim(implode(' ', array_filter([
            $account['first_name'],
            $account['middle_name'],
            $account['last_name'],
        ])));

        session_regenerate_id(true);
        $_SESSION['user'] = $account;
        unset($_SESSION['login_attempts'], $_SESSION['login_window_started']);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        if (!empty($input['remember'])) {
            SessionManager::rememberCurrentSession();
        }

        $redirectUrl = $account['role'] === 'SBO Adviser'
            ? 'pages/adviser/dashboard.html'
            : 'pages/dashboard.html';

        return [
            'user' => $account,
            'message' => 'Signed in successfully, '.$account['first_name'].'. Opening your portal now.',
            'redirect_url' => $redirectUrl,
            'csrf_token' => $_SESSION['csrf_token'],
        ];
    }

    public function currentUser(): ?array
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $parameters['path'], $parameters['domain'], $parameters['secure'], $parameters['httponly']);
        }

        session_destroy();
    }

    private function guardRateLimit(): void
    {
        $started = (int) ($_SESSION['login_window_started'] ?? 0);
        if ($started === 0 || time() - $started >= self::WINDOW_SECONDS) {
            $_SESSION['login_window_started'] = time();
            $_SESSION['login_attempts'] = 0;
            return;
        }

        if ((int) ($_SESSION['login_attempts'] ?? 0) >= self::MAX_ATTEMPTS) {
            throw new OverflowException('Too many sign-in attempts. Please wait one minute and try again.');
        }
    }

    private function recordFailure(): void
    {
        $_SESSION['login_attempts'] = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
    }
}

$action = (string) ($_GET['action'] ?? 'session');

if ($action === 'session') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

    SessionManager::start();
    JsonResponse::send([
        'success' => true,
        'authenticated' => isset($_SESSION['user']),
        'user' => $_SESSION['user'] ?? null,
        'csrf_token' => SessionManager::csrfToken(),
    ]);
}

if (!in_array($action, ['login', 'logout'], true)) {
    JsonResponse::send(['success' => false, 'message' => 'Unknown authentication action.'], 404);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::send(['success' => false, 'message' => 'Method not allowed.'], 405);
}

SessionManager::start();
if (!SessionManager::validateCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
    JsonResponse::send(['success' => false, 'message' => 'Your session expired. Refresh the page and try again.'], 403);
}

$controller = new AuthController(new UserRepository((new Database())->connection()));

if ($action === 'logout') {
    $controller->logout();
    JsonResponse::send(['success' => true, 'message' => 'Signed out successfully.']);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = $_POST;
    }

    JsonResponse::send(['success' => true] + $controller->login($input));
} catch (OverflowException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 429);
} catch (InvalidArgumentException $exception) {
    JsonResponse::send(['success' => false, 'message' => $exception->getMessage()], 422);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    JsonResponse::send(['success' => false, 'message' => 'Sign-in is temporarily unavailable.'], 500);
}

