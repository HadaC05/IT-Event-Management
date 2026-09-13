<?php

declare(strict_types=1);

final class Database
{
    private PDO $connection;

    public function __construct(?string $databasePath = null)
    {
        if (!extension_loaded('pdo_sqlite')) {
            throw new RuntimeException('The PHP PDO SQLite extension is not enabled.');
        }

        $databasePath ??= dirname(__DIR__).'/database/database.sqlite';

        if (!is_file($databasePath)) {
            throw new RuntimeException('The SQLite database file could not be found.');
        }

        $this->connection = new PDO('sqlite:'.$databasePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        $this->connection->exec('PRAGMA foreign_keys = ON');
        $this->connection->exec('PRAGMA busy_timeout = 5000');
    }

    public function connection(): PDO
    {
        return $this->connection;
    }
}
