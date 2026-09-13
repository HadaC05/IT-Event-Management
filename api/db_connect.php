<?php

declare(strict_types=1);

final class Database
{
    private PDO $connection;

    public function __construct(
        ?string $host = null,
        ?string $database = null,
        ?string $username = null,
        ?string $password = null,
        ?int $port = null,
    ) {
        if (!extension_loaded('pdo_mysql')) {
            throw new RuntimeException('The PHP PDO MySQL extension is not enabled.');
        }

        $host ??= getenv('DB_HOST') ?: '127.0.0.1';
        $database ??= getenv('DB_NAME') ?: 'event_db';
        $username ??= getenv('DB_USER') ?: 'micah';
        $password ??= getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : 'YourActualPassword';
        $port ??= (int) (getenv('DB_PORT') ?: 3306);

        if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            throw new InvalidArgumentException('DB_NAME may contain only letters, numbers, and underscores.');
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $this->connection = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => true,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);

        $this->connection->exec("SET time_zone = '+08:00'");
    }

    public function connection(): PDO
    {
        return $this->connection;
    }
}
