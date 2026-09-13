<?php

declare(strict_types=1);

/**
 * One-time/repeatable SQLite-to-MySQL migration for the project.
 *
 * Usage: php api/migrate_sqlite_to_mysql.php [--fresh]
 * Configure the target with DB_HOST, DB_PORT, DB_NAME, DB_USER and DB_PASS.
 */
final class SqliteToMysqlMigration
{
    private PDO $source;
    private PDO $target;
    private string $database;

    public function __construct()
    {
        if (PHP_SAPI !== 'cli') {
            throw new RuntimeException('This migration can only be run from the command line.');
        }
        if (!extension_loaded('pdo_sqlite') || !extension_loaded('pdo_mysql')) {
            throw new RuntimeException('Both pdo_sqlite and pdo_mysql must be enabled.');
        }

        $sourcePath = dirname(__DIR__).'/database/database.sqlite';
        if (!is_file($sourcePath)) {
            throw new RuntimeException('The source SQLite database was not found.');
        }

        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = (int) (getenv('DB_PORT') ?: 3306);
        $user = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '';
        $this->database = getenv('DB_NAME') ?: 'it_event_management';
        if (!preg_match('/^[A-Za-z0-9_]+$/', $this->database)) {
            throw new InvalidArgumentException('DB_NAME may contain only letters, numbers, and underscores.');
        }

        $this->source = new PDO('sqlite:'.$sourcePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $server->exec('CREATE DATABASE IF NOT EXISTS '.$this->identifier($this->database)
            .' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $server->exec('USE '.$this->identifier($this->database));
        $this->target = $server;
    }

    public function run(bool $fresh): array
    {
        $tables = $this->tables();
        $existing = $this->target->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if ($existing && !$fresh) {
            throw new RuntimeException('The target database is not empty. Re-run with --fresh to replace its tables.');
        }

        $this->target->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            if ($fresh) {
                foreach ($existing as $table) {
                    $this->target->exec('DROP TABLE '.$this->identifier((string) $table));
                }
            }

            foreach ($tables as $table) {
                $this->createTable($table);
            }
            foreach ($tables as $table) {
                $this->createIndexes($table);
            }

            $counts = [];
            foreach ($tables as $table) {
                $counts[$table] = $this->copyRows($table);
            }
            foreach ($tables as $table) {
                $this->createForeignKeys($table);
            }
        } finally {
            $this->target->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        return $counts;
    }

    private function tables(): array
    {
        $statement = $this->source->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    private function createTable(string $table): void
    {
        $columns = $this->source->query('PRAGMA table_info('.$this->sqliteIdentifier($table).')')->fetchAll();
        $primary = array_values(array_filter($columns, static fn (array $column): bool => (int) $column['pk'] > 0));
        usort($primary, static fn (array $a, array $b): int => (int) $a['pk'] <=> (int) $b['pk']);
        $singleIntegerPrimary = count($primary) === 1
            && strtolower(trim((string) $primary[0]['type'])) === 'integer';

        $definitions = [];
        foreach ($columns as $column) {
            $definition = $this->identifier($column['name']).' '.$this->mysqlType((string) $column['type']);
            $definition .= (int) $column['notnull'] === 1 || (int) $column['pk'] > 0 ? ' NOT NULL' : ' NULL';
            if ($column['dflt_value'] !== null) {
                $definition .= ' DEFAULT '.$this->mysqlDefault((string) $column['dflt_value']);
            }
            if ($singleIntegerPrimary && (int) $column['pk'] === 1) {
                $definition .= ' AUTO_INCREMENT';
            }
            $definitions[] = $definition;
        }
        if ($primary) {
            $definitions[] = 'PRIMARY KEY ('.implode(', ', array_map(
                fn (array $column): string => $this->identifier($column['name']),
                $primary
            )).')';
        }

        $sql = 'CREATE TABLE '.$this->identifier($table).' ('.implode(', ', $definitions).')'
            .' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $this->target->exec($sql);
    }

    private function createIndexes(string $table): void
    {
        $indexes = $this->source->query('PRAGMA index_list('.$this->sqliteIdentifier($table).')')->fetchAll();
        foreach ($indexes as $index) {
            if (($index['origin'] ?? '') === 'pk' || str_starts_with((string) $index['name'], 'sqlite_autoindex')) {
                continue;
            }
            $columns = $this->source->query(
                'PRAGMA index_info('.$this->sqliteIdentifier((string) $index['name']).')'
            )->fetchAll();
            if (!$columns) continue;
            $columnSql = implode(', ', array_map(
                fn (array $column): string => $this->identifier($column['name']),
                $columns
            ));
            $unique = (int) $index['unique'] === 1 ? 'UNIQUE ' : '';
            $this->target->exec('CREATE '.$unique.'INDEX '.$this->identifier((string) $index['name'])
                .' ON '.$this->identifier($table).' ('.$columnSql.')');
        }
    }

    private function copyRows(string $table): int
    {
        $rows = $this->source->query('SELECT * FROM '.$this->sqliteIdentifier($table));
        $columns = $this->source->query('PRAGMA table_info('.$this->sqliteIdentifier($table).')')->fetchAll();
        $names = array_column($columns, 'name');
        $sql = 'INSERT INTO '.$this->identifier($table).' ('
            .implode(', ', array_map([$this, 'identifier'], $names)).') VALUES ('
            .implode(', ', array_fill(0, count($names), '?')).')';
        $insert = $this->target->prepare($sql);
        $count = 0;
        $this->target->beginTransaction();
        try {
            while ($row = $rows->fetch()) {
                $insert->execute(array_values($row));
                $count++;
            }
            $this->target->commit();
        } catch (Throwable $exception) {
            $this->target->rollBack();
            throw $exception;
        }
        return $count;
    }

    private function createForeignKeys(string $table): void
    {
        $foreignKeys = $this->source->query(
            'PRAGMA foreign_key_list('.$this->sqliteIdentifier($table).')'
        )->fetchAll();
        foreach ($foreignKeys as $foreignKey) {
            $name = 'fk_'.$table.'_'.$foreignKey['from'].'_'.$foreignKey['table'];
            if (strlen($name) > 60) $name = substr($name, 0, 51).'_'.substr(md5($name), 0, 8);
            $sql = 'ALTER TABLE '.$this->identifier($table)
                .' ADD CONSTRAINT '.$this->identifier($name)
                .' FOREIGN KEY ('.$this->identifier($foreignKey['from']).')'
                .' REFERENCES '.$this->identifier($foreignKey['table'])
                .' ('.$this->identifier($foreignKey['to']).')'
                .' ON DELETE '.$this->foreignKeyAction((string) $foreignKey['on_delete'])
                .' ON UPDATE '.$this->foreignKeyAction((string) $foreignKey['on_update']);
            $this->target->exec($sql);
        }
    }

    private function mysqlType(string $type): string
    {
        $normalized = strtolower(trim($type));
        return match (true) {
            $normalized === 'integer' => 'BIGINT',
            str_starts_with($normalized, 'tinyint') => 'TINYINT(1)',
            str_starts_with($normalized, 'varchar') => 'VARCHAR(255)',
            $normalized === 'text' => 'LONGTEXT',
            $normalized === 'datetime' => 'DATETIME',
            $normalized === 'date' => 'DATE',
            $normalized === 'time' => 'TIME',
            $normalized === 'numeric' => 'DECIMAL(12,2)',
            default => 'LONGTEXT',
        };
    }

    private function mysqlDefault(string $default): string
    {
        if (strcasecmp($default, 'CURRENT_TIMESTAMP') === 0) return 'CURRENT_TIMESTAMP';
        return $default;
    }

    private function foreignKeyAction(string $action): string
    {
        return match (strtoupper($action)) {
            'CASCADE', 'RESTRICT', 'SET NULL' => strtoupper($action),
            default => 'NO ACTION',
        };
    }

    private function identifier(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    private function sqliteIdentifier(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
}

try {
    $counts = (new SqliteToMysqlMigration())->run(in_array('--fresh', $argv ?? [], true));
    echo 'Migrated '.array_sum($counts).' rows across '.count($counts)." tables.\n";
    foreach ($counts as $table => $count) echo $table.": ".$count."\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: '.$exception->getMessage()."\n");
    exit(1);
}
