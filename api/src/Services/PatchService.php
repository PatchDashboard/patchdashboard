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
        $servers = $this->pdo->query('SELECT * FROM servers WHERE trusted = 1 ORDER BY server_alias')->fetchAll();

        $rows = [];
        $totalPatches = 0;
        $serversNeedingPatches = 0;

        foreach ($servers as $server) {
            $count = $this->patchCountForServer(
                $server['server_name'],
                $suppressions->suppressedPackageNamesForServer($server['server_name'])
            );
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
            'pending_patch_count' => $totalPatches,
            'servers' => $rows,
        ];
    }

    public function forServer(string $serverName, SuppressionService $suppressions): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM patches WHERE server_name = :server_name AND package_name != "" ORDER BY package_name'
        );
        $stmt->execute(['server_name' => $serverName]);
        $rows = $stmt->fetchAll();

        return array_map(function (array $patch) use ($suppressions, $serverName) {
            $patch['suppressed'] = $suppressions->isSuppressed((string) $patch['package_name'], $serverName);
            return $patch;
        }, $rows);
    }

    public function installAll(int $serverId, SuppressionService $suppressions): int
    {
        $server = $this->pdo->prepare('SELECT server_name FROM servers WHERE id = :id');
        $server->execute(['id' => $serverId]);
        $row = $server->fetch();
        if (!$row) {
            return 0;
        }

        $suppressed = $suppressions->suppressedPackageNamesForServer($row['server_name']);
        $placeholders = $this->placeholders($suppressed);

        $sql = 'UPDATE patches SET to_upgrade = 1 WHERE server_name = :server_name AND package_name != "" AND upgraded = 0';
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

    public function buildUpgradeCommand(string $serverName, array $packageNames): ?string
    {
        $installable = array_values(array_filter($packageNames));
        if ($installable === []) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT d.upgrade_command
             FROM servers s
             LEFT JOIN distro d ON d.id = s.distro_id
             WHERE s.server_name = :server_name
             LIMIT 1'
        );
        $stmt->execute(['server_name' => $serverName]);
        $row = $stmt->fetch();
        if (!$row || empty($row['upgrade_command'])) {
            return null;
        }

        return trim((string) $row['upgrade_command'] . ' ' . implode(' ', $installable));
    }

    public function packagesForServer(string $serverName): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM patch_allpackages WHERE server_name = :server_name ORDER BY package_name'
        );
        $stmt->execute(['server_name' => $serverName]);
        return $stmt->fetchAll();
    }

    public function searchPackages(string $query, bool $exact = false): array
    {
        if ($exact) {
            $stmt = $this->pdo->prepare(
                'SELECT p.server_name, p.package_name, p.package_version, s.id AS server_id
                 FROM patch_allpackages p
                 LEFT JOIN servers s ON s.server_name = p.server_name
                 WHERE p.package_name = :query
                 ORDER BY p.server_name, p.package_name
                 LIMIT 200'
            );
            $stmt->execute(['query' => $query]);
            return $stmt->fetchAll();
        }

        $stmt = $this->pdo->prepare(
            'SELECT p.server_name, p.package_name, p.package_version, s.id AS server_id
             FROM patch_allpackages p
             LEFT JOIN servers s ON s.server_name = p.server_name
             WHERE p.package_name LIKE :query OR p.server_name LIKE :query
             ORDER BY p.server_name, p.package_name
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
                    $parts[2],
                    isset($context['distro_id']) ? (int) $context['distro_id'] : null,
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
     * @return array{distro_name: string, distro_id: int, codename: ?string, version_num: ?string}|null
     */
    public function getDistroContextForServer(string $serverName): ?array
    {
        return $this->getServerDistroContext($serverName);
    }

    /**
     * @return array{distro_name: string, distro_id: int, codename: ?string, version_num: ?string}|null
     */
    private function getServerDistroContext(string $serverName): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.distro_name, d.id AS distro_id, dv.codename, dv.version_num
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
