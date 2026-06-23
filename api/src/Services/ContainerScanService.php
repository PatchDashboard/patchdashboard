<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class ContainerScanService
{
    private const VALID_RUNTIMES = ['host', 'image', 'docker', 'podman', 'lxc'];

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
            $runtime = $this->normalizeRuntime($container);
            $runtimeId = (string) ($container['runtime_id'] ?? $container['name'] ?? uniqid());
            $containerId = $this->upsertContainer($serverId, $container, $runtime, $runtimeId);
            $seenKeys[] = $runtime . ':' . $runtimeId;
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

    public function listVulnerabilities(?string $severity = null, ?int $serverId = null): array
    {
        if (!$this->tableExists('container_vulnerabilities')) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT cv.*, c.name AS container_name, c.runtime, c.image_ref, s.server_alias, s.id AS server_id
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
        if ($serverId !== null) {
            $sql .= ' AND s.id = :server_id';
            $params['server_id'] = $serverId;
        }

        $sql .= ' ORDER BY cv.severity DESC, cv.cve_id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * @return array<int, array{workloads: int, container_workloads: int, host_cves: int, container_cves: int}>
     */
    public function workloadSummariesByServer(): array
    {
        if (!$this->tableExists('containers')) {
            return [];
        }

        $rows = $this->pdo->query(
            'SELECT c.server_id,
                    COUNT(*) AS workloads,
                    SUM(CASE WHEN c.runtime = \'host\' THEN 1 ELSE 0 END) AS host_scans,
                    SUM(CASE WHEN c.runtime != \'host\' THEN 1 ELSE 0 END) AS container_workloads
             FROM containers c
             GROUP BY c.server_id'
        )->fetchAll();

        $summaries = [];
        foreach ($rows as $row) {
            $summaries[(int) $row['server_id']] = [
                'workloads' => (int) $row['workloads'],
                'host_scans' => (int) $row['host_scans'],
                'container_workloads' => (int) $row['container_workloads'],
                'host_cves' => 0,
                'container_cves' => 0,
            ];
        }

        if (!$this->tableExists('container_vulnerabilities')) {
            return $summaries;
        }

        $cveRows = $this->pdo->query(
            'SELECT c.server_id,
                    SUM(CASE WHEN c.runtime = \'host\' THEN 1 ELSE 0 END) AS host_cves,
                    SUM(CASE WHEN c.runtime != \'host\' THEN 1 ELSE 0 END) AS container_cves
             FROM container_vulnerabilities cv
             JOIN containers c ON c.id = cv.container_id
             WHERE cv.resolved_at IS NULL
             GROUP BY c.server_id'
        )->fetchAll();

        foreach ($cveRows as $row) {
            $serverId = (int) $row['server_id'];
            $summaries[$serverId] ??= [
                'workloads' => 0,
                'host_scans' => 0,
                'container_workloads' => 0,
                'host_cves' => 0,
                'container_cves' => 0,
            ];
            $summaries[$serverId]['host_cves'] = (int) $row['host_cves'];
            $summaries[$serverId]['container_cves'] = (int) $row['container_cves'];
        }

        return $summaries;
    }

    /**
     * @return array<int, list<array{id: int, name: string, runtime: string, image_ref: ?string, cve_count: int}>>
     */
    public function nestedContainersByServer(): array
    {
        if (!$this->tableExists('containers')) {
            return [];
        }

        $sql = <<<'SQL'
            SELECT c.server_id, c.id, c.name, c.runtime, c.image_ref,
                   COUNT(cv.id) AS cve_count
            FROM containers c
            LEFT JOIN container_vulnerabilities cv
              ON cv.container_id = c.id AND cv.resolved_at IS NULL
            WHERE c.runtime != 'host'
            GROUP BY c.id, c.server_id, c.name, c.runtime, c.image_ref
            ORDER BY c.name
        SQL;

        $nested = [];
        foreach ($this->pdo->query($sql)->fetchAll() as $row) {
            $serverId = (int) $row['server_id'];
            $nested[$serverId] ??= [];
            $nested[$serverId][] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'runtime' => (string) $row['runtime'],
                'image_ref' => $row['image_ref'] !== null ? (string) $row['image_ref'] : null,
                'cve_count' => (int) $row['cve_count'],
            ];
        }

        return $nested;
    }

    /**
     * @return array{
     *   workloads: list<array<string, mixed>>,
     *   host_workloads: list<array<string, mixed>>,
     *   container_workloads: list<array<string, mixed>>,
     *   vulnerabilities: list<array<string, mixed>>,
     *   host_vulnerabilities: list<array<string, mixed>>,
     *   container_vulnerabilities: list<array<string, mixed>>
     * }
     */
    public function serverWorkloads(int $serverId): array
    {
        $workloads = $this->list($serverId);
        $vulnerabilities = $this->listVulnerabilities(null, $serverId);

        $hostWorkloads = [];
        $containerWorkloads = [];
        foreach ($workloads as $row) {
            if (($row['runtime'] ?? '') === 'host') {
                $hostWorkloads[] = $row;
            } else {
                $containerWorkloads[] = $row;
            }
        }

        $hostVulnerabilities = [];
        $containerVulnerabilities = [];
        foreach ($vulnerabilities as $row) {
            if (($row['runtime'] ?? '') === 'host') {
                $hostVulnerabilities[] = $row;
            } else {
                $containerVulnerabilities[] = $row;
            }
        }

        return [
            'workloads' => $workloads,
            'host_workloads' => $hostWorkloads,
            'container_workloads' => $containerWorkloads,
            'vulnerabilities' => $vulnerabilities,
            'host_vulnerabilities' => $hostVulnerabilities,
            'container_vulnerabilities' => $containerVulnerabilities,
        ];
    }

    public function summary(): array
    {
        if (!$this->tableExists('container_vulnerabilities')) {
            return $this->emptySummary();
        }

        $base = <<<'SQL'
            SELECT LOWER(cv.severity) AS severity, COUNT(*) AS count
            FROM container_vulnerabilities cv
            JOIN containers c ON c.id = cv.container_id
            WHERE cv.resolved_at IS NULL
        SQL;

        $overall = $this->summarizeSeverityRows(
            $this->pdo->query($base . ' GROUP BY cv.severity')->fetchAll()
        );

        $host = $this->summarizeSeverityRows(
            $this->pdo->query($base . " AND c.runtime = 'host' GROUP BY cv.severity")->fetchAll()
        );

        $containers = $this->summarizeSeverityRows(
            $this->pdo->query($base . " AND c.runtime != 'host' GROUP BY cv.severity")->fetchAll()
        );

        $workloads = $this->pdo->query(
            "SELECT
                SUM(CASE WHEN runtime = 'host' THEN 1 ELSE 0 END) AS host_scans,
                SUM(CASE WHEN runtime != 'host' THEN 1 ELSE 0 END) AS container_workloads
             FROM containers"
        )->fetch() ?: [];

        return array_merge($overall, [
            'host' => $host,
            'containers' => $containers,
            'workloads' => [
                'host' => (int) ($workloads['host_scans'] ?? 0),
                'containers' => (int) ($workloads['container_workloads'] ?? 0),
            ],
        ]);
    }

    /**
     * @param list<array{severity?: string, count?: int|string}> $rows
     * @return array{critical: int, high: int, medium: int, low: int, total: int}
     */
    private function summarizeSeverityRows(array $rows): array
    {
        $summary = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $key = strtolower((string) ($row['severity'] ?? ''));
            if (isset($summary[$key])) {
                $summary[$key] = (int) $row['count'];
            }
            $summary['total'] += (int) ($row['count'] ?? 0);
        }

        return $summary;
    }

    /**
     * @return array{
     *   critical: int, high: int, medium: int, low: int, total: int,
     *   host: array{critical: int, high: int, medium: int, low: int, total: int},
     *   containers: array{critical: int, high: int, medium: int, low: int, total: int},
     *   workloads: array{host: int, containers: int}
     * }
     */
    private function emptySummary(): array
    {
        $zero = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0, 'total' => 0];

        return array_merge($zero, [
            'host' => $zero,
            'containers' => $zero,
            'workloads' => ['host' => 0, 'containers' => 0],
        ]);
    }

    private function upsertContainer(int $serverId, array $container, string $runtime, string $runtimeId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM containers WHERE server_id = :server_id AND runtime = :runtime AND runtime_id = :runtime_id'
        );
        $stmt->execute([
            'server_id' => $serverId,
            'runtime' => $runtime,
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
            'runtime' => $runtime,
            'runtime_id' => $runtimeId,
            'name' => $container['name'] ?? $runtimeId,
            'image_ref' => $container['image'] ?? null,
            'digest' => $container['digest'] ?? null,
            'status' => $container['status'] ?? 'running',
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $container
     */
    private function normalizeRuntime(array $container): string
    {
        $runtime = strtolower(trim((string) ($container['runtime'] ?? '')));
        $runtimeId = (string) ($container['runtime_id'] ?? '');
        $imageRef = (string) ($container['image'] ?? $container['image_ref'] ?? '');

        if ($runtime === 'host' || $runtimeId === 'rootfs' || str_starts_with($imageRef, 'os:')) {
            return 'host';
        }

        if ($runtime === 'image' || str_starts_with($runtimeId, 'docker:') || str_starts_with($runtimeId, 'podman:')) {
            return 'image';
        }

        if (in_array($runtime, self::VALID_RUNTIMES, true)) {
            return $runtime;
        }

        return 'docker';
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
