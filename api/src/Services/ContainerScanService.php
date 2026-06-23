<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class ContainerScanService
{
    public function __construct(
        private \PDO $pdo,
        private AlertService $alertService
    ) {
    }

    public function ingest(int $serverId, array $payload): array
    {
        if (!$this->tableExists('containers')) {
            return ['ingested' => 0, 'message' => 'Container tables not migrated yet'];
        }

        $containers = $payload['containers'] ?? [];
        $newVulnerabilities = [];
        $seenKeys = [];

        foreach ($containers as $container) {
            $containerId = $this->upsertContainer($serverId, $container);
            $key = ($container['runtime'] ?? 'docker') . ':' . ($container['runtime_id'] ?? $container['name'] ?? '');
            $seenKeys[] = $key;
            $existing = $this->existingCveIds($containerId);
            $incoming = [];

            foreach ($container['vulnerabilities'] ?? [] as $vuln) {
                $cveId = (string) ($vuln['cve_id'] ?? '');
                if ($cveId === '') {
                    continue;
                }
                $incoming[] = $cveId;
                $isNew = !in_array($cveId, $existing, true);
                $this->upsertVulnerability($containerId, $vuln);
                if ($isNew) {
                    $newVulnerabilities[] = array_merge($vuln, [
                        'container_id' => $containerId,
                        'container_name' => $container['name'] ?? '',
                    ]);
                }
            }

            $this->resolveMissing($containerId, $incoming);
        }

        $this->removeStaleApplications($serverId, $seenKeys);
        $this->touchServerCveScan($serverId);

        $this->alertService->dispatchContainerAlerts($serverId, $newVulnerabilities);

        return [
            'ingested' => count($containers),
            'new_vulnerabilities' => count($newVulnerabilities),
        ];
    }

    public function list(?int $serverId = null): array
    {
        if (!$this->tableExists('containers')) {
            return [];
        }

        $sql = 'SELECT c.*, s.server_alias FROM containers c JOIN servers s ON s.id = c.server_id';
        $params = [];
        if ($serverId !== null) {
            $sql .= ' WHERE c.server_id = :server_id';
            $params['server_id'] = $serverId;
        }
        $sql .= ' ORDER BY s.server_alias, c.name';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listVulnerabilities(?string $severity = null): array
    {
        if (!$this->tableExists('container_vulnerabilities')) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT cv.*, c.name AS container_name, c.runtime, s.server_alias, s.id AS server_id
            FROM container_vulnerabilities cv
            JOIN containers c ON c.id = cv.container_id
            JOIN servers s ON s.id = c.server_id
            WHERE cv.resolved_at IS NULL
        SQL;
        $params = [];

        if ($severity !== null) {
            $sql .= ' AND cv.severity = :severity';
            $params['severity'] = strtoupper($severity);
        }

        $sql .= ' ORDER BY cv.severity DESC, cv.cve_id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function summary(): array
    {
        if (!$this->tableExists('container_vulnerabilities')) {
            return ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0];
        }

        $rows = $this->pdo->query(
            "SELECT severity, COUNT(*) AS count
             FROM container_vulnerabilities
             WHERE resolved_at IS NULL
             GROUP BY severity"
        )->fetchAll();

        $summary = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $key = strtolower((string) $row['severity']);
            if (isset($summary[$key])) {
                $summary[$key] = (int) $row['count'];
            }
            $summary['total'] += (int) $row['count'];
        }

        return $summary;
    }

    private function upsertContainer(int $serverId, array $container): int
    {
        $runtimeId = (string) ($container['runtime_id'] ?? $container['name'] ?? uniqid());
        $stmt = $this->pdo->prepare(
            'SELECT id FROM containers WHERE server_id = :server_id AND runtime = :runtime AND runtime_id = :runtime_id'
        );
        $stmt->execute([
            'server_id' => $serverId,
            'runtime' => $container['runtime'] ?? 'docker',
            'runtime_id' => $runtimeId,
        ]);
        $existing = $stmt->fetch();

        if ($existing) {
            $update = $this->pdo->prepare(
                'UPDATE containers SET name = :name, image_ref = :image_ref, digest = :digest, status = :status, last_scanned_at = NOW()
                 WHERE id = :id'
            );
            $update->execute([
                'id' => $existing['id'],
                'name' => $container['name'] ?? $runtimeId,
                'image_ref' => $container['image'] ?? null,
                'digest' => $container['digest'] ?? null,
                'status' => $container['status'] ?? 'running',
            ]);
            return (int) $existing['id'];
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO containers (server_id, runtime, runtime_id, name, image_ref, digest, status, last_scanned_at)
             VALUES (:server_id, :runtime, :runtime_id, :name, :image_ref, :digest, :status, NOW())'
        );
        $insert->execute([
            'server_id' => $serverId,
            'runtime' => $container['runtime'] ?? 'docker',
            'runtime_id' => $runtimeId,
            'name' => $container['name'] ?? $runtimeId,
            'image_ref' => $container['image'] ?? null,
            'digest' => $container['digest'] ?? null,
            'status' => $container['status'] ?? 'running',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function upsertVulnerability(int $containerId, array $vuln): void
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM container_vulnerabilities WHERE container_id = :container_id AND cve_id = :cve_id'
        );
        $stmt->execute(['container_id' => $containerId, 'cve_id' => $vuln['cve_id']]);
        $existing = $stmt->fetch();

        if ($existing) {
            $update = $this->pdo->prepare(
                'UPDATE container_vulnerabilities
                 SET severity = :severity, package_name = :package_name, installed_version = :installed_version,
                     fixed_version = :fixed_version, title = :title, last_seen = NOW(), resolved_at = NULL
                 WHERE id = :id'
            );
            $update->execute([
                'id' => $existing['id'],
                'severity' => strtoupper((string) ($vuln['severity'] ?? 'UNKNOWN')),
                'package_name' => $vuln['package'] ?? null,
                'installed_version' => $vuln['installed_version'] ?? null,
                'fixed_version' => $vuln['fixed_version'] ?? null,
                'title' => $vuln['title'] ?? null,
            ]);
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT INTO container_vulnerabilities
             (container_id, cve_id, severity, package_name, installed_version, fixed_version, title, first_seen, last_seen)
             VALUES (:container_id, :cve_id, :severity, :package_name, :installed_version, :fixed_version, :title, NOW(), NOW())'
        );
        $insert->execute([
            'container_id' => $containerId,
            'cve_id' => $vuln['cve_id'],
            'severity' => strtoupper((string) ($vuln['severity'] ?? 'UNKNOWN')),
            'package_name' => $vuln['package'] ?? null,
            'installed_version' => $vuln['installed_version'] ?? null,
            'fixed_version' => $vuln['fixed_version'] ?? null,
            'title' => $vuln['title'] ?? null,
        ]);
    }

    private function existingCveIds(int $containerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT cve_id FROM container_vulnerabilities WHERE container_id = :container_id AND resolved_at IS NULL'
        );
        $stmt->execute(['container_id' => $containerId]);
        return array_column($stmt->fetchAll(), 'cve_id');
    }

    private function resolveMissing(int $containerId, array $incomingCveIds): void
    {
        if ($incomingCveIds === []) {
            $this->pdo->prepare(
                'UPDATE container_vulnerabilities SET resolved_at = NOW() WHERE container_id = :container_id AND resolved_at IS NULL'
            )->execute(['container_id' => $containerId]);
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($incomingCveIds), '?'));
        $sql = "UPDATE container_vulnerabilities SET resolved_at = NOW()
                WHERE container_id = ? AND resolved_at IS NULL AND cve_id NOT IN ({$placeholders})";
        $params = array_merge([$containerId], $incomingCveIds);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    private function removeStaleApplications(int $serverId, array $seenKeys): void
    {
        $rows = $this->pdo->prepare('SELECT id, runtime, runtime_id FROM containers WHERE server_id = :server_id');
        $rows->execute(['server_id' => $serverId]);
        $existing = $rows->fetchAll();

        foreach ($existing as $row) {
            $key = $row['runtime'] . ':' . $row['runtime_id'];
            if (in_array($key, $seenKeys, true)) {
                continue;
            }

            $this->pdo->prepare('DELETE FROM container_vulnerabilities WHERE container_id = :id')
                ->execute(['id' => $row['id']]);
            $this->pdo->prepare('DELETE FROM containers WHERE id = :id')
                ->execute(['id' => $row['id']]);
        }
    }

    private function touchServerCveScan(int $serverId): void
    {
        if (!$this->columnExists('servers', 'last_cve_scan')) {
            return;
        }

        $this->pdo->prepare('UPDATE servers SET last_cve_scan = NOW() WHERE id = :id')
            ->execute(['id' => $serverId]);
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $stmt->execute(['table' => $table, 'column' => $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($table));
        return (bool) $stmt->fetch();
    }
}
