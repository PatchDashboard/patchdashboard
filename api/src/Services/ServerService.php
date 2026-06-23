<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class ServerService
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function findByClientKey(string $clientKey): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM servers WHERE client_key = :client_key LIMIT 1');
        $stmt->execute(['client_key' => $clientKey]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listTrusted(): array
    {
        $sql = <<<'SQL'
            SELECT s.*,
                   d.distro_name,
                   d.icon_path,
                   dv.version_num,
                   dv.eol_date,
                   dv.codename
            FROM servers s
            LEFT JOIN distro d ON d.id = s.distro_id
            LEFT JOIN distro_version dv ON dv.id = s.distro_version
            WHERE s.trusted = 1
            ORDER BY s.server_alias
        SQL;
        return $this->pdo->query($sql)->fetchAll();
    }

    public function listAll(): array
    {
        $sql = <<<'SQL'
            SELECT s.*,
                   d.distro_name,
                   dv.version_num
            FROM servers s
            LEFT JOIN distro d ON d.id = s.distro_id
            LEFT JOIN distro_version dv ON dv.id = s.distro_version
            ORDER BY s.server_alias
        SQL;
        return $this->pdo->query($sql)->fetchAll();
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM servers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function registerFromAgent(
        string $clientKey,
        string $clientHost,
        string $clientOs,
        string $clientOsVer,
        string $serverIp,
        ?array $capabilities = null
    ): void {
        $distroId = $this->resolveDistroId($clientOs);
        $versionId = $this->resolveDistroVersionId($distroId, $clientOsVer);

        $stmt = $this->pdo->prepare(
            'INSERT INTO servers (server_name, server_alias, distro_id, distro_version, server_ip, client_key, host_capabilities)
             VALUES (:server_name, :server_alias, :distro_id, :distro_version, :server_ip, :client_key, :host_capabilities)'
        );
        $stmt->execute([
            'server_name' => $clientHost,
            'server_alias' => $clientHost,
            'distro_id' => $distroId,
            'distro_version' => $versionId,
            'server_ip' => $serverIp,
            'client_key' => $clientKey,
            'host_capabilities' => $capabilities ? json_encode($capabilities) : null,
        ]);
    }

    public function touchLastSeen(string $clientKey): void
    {
        $this->pdo->prepare('UPDATE servers SET last_seen = NOW() WHERE client_key = :client_key')
            ->execute(['client_key' => $clientKey]);
    }

    public function shouldCheckPatches(string $clientKey): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM servers
             WHERE client_key = :client_key
               AND last_checked < NOW() - INTERVAL 2 HOUR
             LIMIT 1'
        );
        $stmt->execute(['client_key' => $clientKey]);
        if (!$stmt->fetch()) {
            return false;
        }

        $this->pdo->prepare('UPDATE servers SET last_checked = NOW() WHERE client_key = :client_key')
            ->execute(['client_key' => $clientKey]);

        return true;
    }

    public function shouldRunCveScan(string $clientKey): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM servers
             WHERE client_key = :client_key
               AND trusted = 1
               AND scan_containers = 1
               AND (
                 last_cve_scan IS NULL
                 OR last_cve_scan < NOW() - INTERVAL 1 DAY
               )
             LIMIT 1'
        );
        $stmt->execute(['client_key' => $clientKey]);
        return (bool) $stmt->fetch();
    }

    public function touchCveScan(int $serverId): void
    {
        if (!$this->columnExists('last_cve_scan')) {
            return;
        }

        $this->pdo->prepare('UPDATE servers SET last_cve_scan = NOW() WHERE id = :id')
            ->execute(['id' => $serverId]);
    }

    public function forceCveScanForAllTrusted(): int
    {
        if (!$this->columnExists('last_cve_scan')) {
            return 0;
        }

        return $this->pdo->exec(
            "UPDATE servers SET last_cve_scan = '2001-01-01 00:00:00' WHERE trusted = 1 AND scan_containers = 1"
        );
    }

    public function forceCveScanForServer(int $serverId): void
    {
        if (!$this->columnExists('last_cve_scan')) {
            return;
        }

        $this->pdo->prepare(
            "UPDATE servers SET last_cve_scan = '2001-01-01 00:00:00' WHERE id = :id"
        )->execute(['id' => $serverId]);
    }

    public function update(int $id, array $data): ?array
    {
        $allowed = ['server_alias', 'server_group', 'distro_id', 'distro_version', 'scan_containers'];
        $fields = [];
        $params = ['id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }

        if ($fields === []) {
            return $this->findById($id);
        }

        $sql = 'UPDATE servers SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);
        return $this->findById($id);
    }

    public function setTrusted(int $id, bool $trusted): void
    {
        $this->pdo->prepare('UPDATE servers SET trusted = :trusted WHERE id = :id')
            ->execute(['trusted' => $trusted ? 1 : 0, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM servers WHERE id = :id')->execute(['id' => $id]);
    }

    public function getInstallKey(): string
    {
        $company = $_ENV['YOUR_COMPANY'] ?? 'Test, INC';
        $stmt = $this->pdo->prepare('SELECT install_key FROM company WHERE display_name = :name LIMIT 1');
        $stmt->execute(['name' => $company]);
        $row = $stmt->fetch();
        if (!$row) {
            return '';
        }
        $parts = explode(' ', (string) $row['install_key']);
        return $parts[0] ?? '';
    }

    private function resolveDistroId(string $clientOs): int
    {
        if ($clientOs === 'Raspbian') {
            $clientOs = 'Raspbian';
        }
        $stmt = $this->pdo->prepare('SELECT id FROM distro WHERE distro_name LIKE :name LIMIT 1');
        $stmt->execute(['name' => $clientOs]);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : 0;
    }

    private function resolveDistroVersionId(int $distroId, string $clientOsVer): int
    {
        if ($distroId === 0) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            'SELECT id FROM distro_version WHERE distro_id = :distro_id AND version_num LIKE :version LIMIT 1'
        );
        $stmt->execute(['distro_id' => $distroId, 'version' => $clientOsVer . '%']);
        $row = $stmt->fetch();
        return $row ? (int) $row['id'] : 0;
    }

    private function columnExists(string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute(['table' => 'servers', 'column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
