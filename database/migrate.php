#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Apply SQL migrations in database/migrations/ in filename order.
 *
 * Usage:
 *   php database/migrate.php [--dsn="mysql:host=...;dbname=..."] [--user=...] [--pass=...]
 */

$root = dirname(__DIR__);
$migrationsDir = $root . '/database/migrations';

$options = getopt('', ['dsn:', 'user:', 'pass:']);
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'patchdashboard';
$dsn = $options['dsn'] ?? getenv('DB_DSN') ?: "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
$user = $options['user'] ?? getenv('DB_USER') ?: 'patchdashboard';
$pass = $options['pass'] ?? getenv('DB_PASS') ?: 'patchdashboard';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(64) NOT NULL PRIMARY KEY,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$files = glob($migrationsDir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $version = basename($file);
    if (in_array($version, $applied, true)) {
        echo "Skipping {$version}\n";
        continue;
    }

    echo "Applying {$version}\n";
    $sql = file_get_contents($file);
    if ($sql === false) {
        throw new RuntimeException("Unable to read {$file}");
    }

    foreach (splitStatements($sql) as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            if (shouldIgnoreMigrationError($statement, $e)) {
                echo "  warning: " . $e->getMessage() . "\n";
                continue;
            }
            throw $e;
        }
    }

    $stmt = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (:version)');
    $stmt->execute(['version' => $version]);
}

echo "Migrations complete.\n";

function splitStatements(string $sql): array
{
    $parts = preg_split('/;\s*\n/', $sql) ?: [];
    $statements = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $statements[] = $part . ';';
    }
    return $statements;
}

function shouldIgnoreMigrationError(string $statement, PDOException $e): bool
{
    $message = $e->getMessage();
    if (str_contains($statement, 'ADD COLUMN IF NOT EXISTS') && str_contains($message, 'Duplicate column')) {
        return true;
    }
    if (str_contains($statement, 'RENAME TABLE') && str_contains($message, "doesn't exist")) {
        return true;
    }
    if (str_contains($statement, 'MODIFY') && str_contains($message, 'Duplicate')) {
        return true;
    }
    return false;
}
