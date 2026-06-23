<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class SuppressionService
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function list(): array
    {
        $table = $this->suppressionTable();
        return $this->pdo->query("SELECT * FROM {$table} ORDER BY package_name, server_name")->fetchAll();
    }

    public function packageNames(): array
    {
        $table = $this->suppressionTable();
        $rows = $this->pdo->query("SELECT package_name FROM {$table}")->fetchAll();
        return array_column($rows, 'package_name');
    }

    public function isSuppressed(string $packageName, string $serverName): bool
    {
        $table = $this->suppressionTable();
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM {$table}
             WHERE package_name = :package_name
               AND server_name IN ('0', :server_name)
             LIMIT 1"
        );
        $stmt->execute(['package_name' => $packageName, 'server_name' => $serverName]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @return list<string>
     */
    public function suppressedPackageNamesForServer(string $serverName): array
    {
        $table = $this->suppressionTable();
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT package_name FROM {$table}
             WHERE server_name IN ('0', :server_name)"
        );
        $stmt->execute(['server_name' => $serverName]);
        return array_column($stmt->fetchAll(), 'package_name');
    }

    public function create(string $packageName, string $serverName): array
    {
        $table = $this->suppressionTable();
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$table} (package_name, server_name) VALUES (:package_name, :server_name)"
        );
        $stmt->execute(['package_name' => $packageName, 'server_name' => $serverName]);
        return [
            'id' => (int) $this->pdo->lastInsertId(),
            'package_name' => $packageName,
            'server_name' => $serverName,
        ];
    }

    public function delete(int $id): bool
    {
        $table = $this->suppressionTable();
        $stmt = $this->pdo->prepare("DELETE FROM {$table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    private function suppressionTable(): string
    {
        $check = $this->pdo->query("SHOW TABLES LIKE 'suppressed'")->fetch();
        return $check ? 'suppressed' : 'supressed';
    }
}
