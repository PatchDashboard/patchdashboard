<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class PatchService
{
    public function __construct(private \PDO $pdo)
    {
    }

    public function dashboard(SuppressionService $suppressions): array
    {
        $suppressed = $suppressions->packageNames();
        $servers = $this->pdo->query('SELECT * FROM servers WHERE trusted = 1 ORDER BY server_alias')->fetchAll();

        $rows = [];
        $totalPatches = 0;
        $serversNeedingPatches = 0;

        foreach ($servers as $server) {
            $count = $this->patchCountForServer($server['server_name'], $suppressed);
            if ($count > 0) {
                $serversNeedingPatches++;
            }
            $totalPatches += $count;

            $distStmt = $this->pdo->prepare('SELECT * FROM distro WHERE id = :id');
            $distStmt->execute(['id' => $server['distro_id']]);
            $distro = $distStmt->fetch() ?: [];

            $rows[] = [
                'server_id' => (int) $server['id'],
                'server_name' => $server['server_name'],
                'server_alias' => $server['server_alias'],
                'patch_count' => $count,
                'distro_name' => $distro['distro_name'] ?? null,
                'icon_path' => $distro['icon_path'] ?? null,
            ];
        }

        $serverCount = count($servers);
        $percentGood = $serverCount > 0
            ? max(0, 100 - (int) round(($serversNeedingPatches / $serverCount) * 100))
            : 100;

        return [
            'percent_good' => $percentGood,
            'server_count' => $serverCount,
            'total_patches' => $totalPatches,
            'servers' => $rows,
        ];
    }

    public function forServer(string $serverName, SuppressionService $suppressions): array
    {
        $suppressed = $suppressions->packageNames();
        $placeholders = $this->placeholders($suppressed);

        $sql = 'SELECT * FROM patches WHERE server_name = :server_name AND package_name != ""';
        $params = ['server_name' => $serverName];

        if ($placeholders !== '') {
            $sql .= " AND package_name NOT IN ({$placeholders})";
            foreach ($suppressed as $i => $pkg) {
                $params["s{$i}"] = $pkg;
            }
        }

        $sql .= ' ORDER BY package_name';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function installAll(int $serverId, SuppressionService $suppressions): int
    {
        $server = $this->pdo->prepare('SELECT server_name FROM servers WHERE id = :id');
        $server->execute(['id' => $serverId]);
        $row = $server->fetch();
        if (!$row) {
            return 0;
        }

        $suppressed = $suppressions->packageNames();
        $placeholders = $this->placeholders($suppressed);

        $sql = 'UPDATE patches SET to_upgrade = 1 WHERE server_name = :server_name AND package_name != ""';
        $params = ['server_name' => $row['server_name']];

        if ($placeholders !== '') {
            $sql .= " AND package_name NOT IN ({$placeholders})";
            foreach ($suppressed as $i => $pkg) {
                $params["s{$i}"] = $pkg;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function packagesForServer(string $serverName): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM patch_allpackages WHERE server_name = :server_name ORDER BY package_name'
        );
        $stmt->execute(['server_name' => $serverName]);
        return $stmt->fetchAll();
    }

    public function searchPackages(string $query): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT server_name, package_name, package_version
             FROM patch_allpackages
             WHERE package_name LIKE :query OR server_name LIKE :query
             ORDER BY server_name, package_name
             LIMIT 200'
        );
        $stmt->execute(['query' => '%' . $query . '%']);
        return $stmt->fetchAll();
    }

    public function replacePatches(string $serverName, array $lines, ?PatchVulnerabilityService $vulnerabilities = null): void
    {
        $this->pdo->prepare('DELETE FROM patches WHERE server_name = :server_name')
            ->execute(['server_name' => $serverName]);

        $context = $this->getServerDistroContext($serverName);

        $insert = $this->pdo->prepare(
            'INSERT INTO patches (server_name, package_name, current, new, urgency, bug_url)
             VALUES (:server_name, :package_name, :current, :new, :urgency, :bug_url)'
        );

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode(':::', $line);
            if (count($parts) < 3) {
                continue;
            }

            $urgency = null;
            $bugUrl = null;
            if ($vulnerabilities !== null && $context !== null) {
                $meta = $vulnerabilities->enrichPatch(
                    (string) $context['distro_name'],
                    $context['codename'] !== null ? (string) $context['codename'] : null,
                    $context['version_num'] !== null ? (string) $context['version_num'] : null,
                    $parts[0],
                    $parts[2]
                );
                $urgency = $meta['urgency'];
                $bugUrl = $meta['bug_url'];
            }

            $insert->execute([
                'server_name' => $serverName,
                'package_name' => $parts[0],
                'current' => $parts[1],
                'new' => $parts[2],
                'urgency' => $urgency,
                'bug_url' => $bugUrl,
            ]);
        }
    }

    public function replacePackages(string $serverName, array $lines): void
    {
        $this->pdo->prepare('DELETE FROM patch_allpackages WHERE server_name = :server_name')
            ->execute(['server_name' => $serverName]);

        $insert = $this->pdo->prepare(
            'INSERT INTO patch_allpackages (server_name, package_name, package_version)
             VALUES (:server_name, :package_name, :package_version)'
        );

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = explode(':::', $line);
            if (count($parts) < 2) {
                continue;
            }
            $insert->execute([
                'server_name' => $serverName,
                'package_name' => $parts[0],
                'package_version' => $parts[1],
            ]);
        }
    }

    private function patchCountForServer(string $serverName, array $suppressed): int
    {
        $placeholders = $this->placeholders($suppressed);
        $sql = 'SELECT COUNT(*) AS total FROM patches WHERE server_name = :server_name AND package_name != ""';
        $params = ['server_name' => $serverName];

        if ($placeholders !== '') {
            $sql .= " AND package_name NOT IN ({$placeholders})";
            foreach ($suppressed as $i => $pkg) {
                $params["s{$i}"] = $pkg;
            }
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function placeholders(array $values): string
    {
        if ($values === []) {
            return '';
        }
        return implode(', ', array_map(fn ($i) => ":s{$i}", array_keys($values)));
    }

    /**
     * @return array{distro_name: string, codename: ?string, version_num: ?string}|null
     */
    public function getDistroContextForServer(string $serverName): ?array
    {
        return $this->getServerDistroContext($serverName);
    }

    /**
     * @return array{distro_name: string, codename: ?string, version_num: ?string}|null
     */
    private function getServerDistroContext(string $serverName): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.distro_name, dv.codename, dv.version_num
             FROM servers s
             JOIN distro d ON d.id = s.distro_id
             LEFT JOIN distro_version dv ON dv.id = s.distro_version
             WHERE s.server_name = :server_name
             LIMIT 1'
        );
        $stmt->execute(['server_name' => $serverName]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
