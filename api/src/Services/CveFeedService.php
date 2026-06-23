<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

use Psr\Log\LoggerInterface;

final class CveFeedService
{
    public function __construct(
        private \PDO $pdo,
        private CveFeedSyncEngine $syncEngine,
        private LoggerInterface $logger,
    ) {
    }

    public function listProviders(): array
    {
        if (!$this->tableExists('cve_feed_providers')) {
            return [];
        }

        return $this->pdo->query('SELECT * FROM cve_feed_providers ORDER BY name')->fetchAll();
    }

    public function listFeedsGroupedByDistro(): array
    {
        if (!$this->tableExists('distro_cve_feeds')) {
            return [];
        }

        $rows = $this->pdo->query(
            'SELECT f.*, p.name AS provider_name, p.description AS provider_description,
                    p.docs_url, p.sync_mode, p.default_config,
                    d.distro_name
             FROM distro_cve_feeds f
             JOIN cve_feed_providers p ON p.id = f.provider_id
             LEFT JOIN distro d ON d.id = f.distro_id
             ORDER BY f.distro_id IS NULL DESC, d.distro_name, f.priority, p.name'
        )->fetchAll();

        $grouped = [];
        foreach ($rows as $row) {
            $row['config'] = $this->decodeConfig($row['config']);
            $row['default_config'] = $this->decodeConfig($row['default_config']);
            $distroId = $row['distro_id'] !== null ? (int) $row['distro_id'] : 0;
            if (!isset($grouped[$distroId])) {
                $grouped[$distroId] = [
                    'distro_id' => $distroId === 0 ? null : $distroId,
                    'distro_name' => $row['distro_name'] ?? 'Global',
                    'feeds' => [],
                ];
            }
            $grouped[$distroId]['feeds'][] = $row;
        }

        uasort($grouped, function (array $a, array $b): int {
            if ($a['distro_id'] === null) {
                return -1;
            }
            if ($b['distro_id'] === null) {
                return 1;
            }
            return strcmp((string) $a['distro_name'], (string) $b['distro_name']);
        });

        return array_values($grouped);
    }

    public function findFeed(int $feedId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT f.*, p.name AS provider_name, p.sync_mode, p.default_config, d.distro_name
             FROM distro_cve_feeds f
             JOIN cve_feed_providers p ON p.id = f.provider_id
             LEFT JOIN distro d ON d.id = f.distro_id
             WHERE f.id = :id'
        );
        $stmt->execute(['id' => $feedId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['config'] = $this->decodeConfig($row['config']);
        $row['default_config'] = $this->decodeConfig($row['default_config']);
        return $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateFeed(int $feedId, array $data): ?array
    {
        $feed = $this->findFeed($feedId);
        if ($feed === null) {
            return null;
        }

        $fields = [];
        $params = ['id' => $feedId];

        if (array_key_exists('enabled', $data)) {
            $fields[] = 'enabled = :enabled';
            $params['enabled'] = (int) (bool) $data['enabled'];
        }
        if (array_key_exists('priority', $data)) {
            $fields[] = 'priority = :priority';
            $params['priority'] = (int) $data['priority'];
        }
        if (array_key_exists('config', $data) && is_array($data['config'])) {
            $fields[] = 'config = :config';
            $params['config'] = json_encode($data['config']);
        }

        if ($fields === []) {
            return $feed;
        }

        $sql = 'UPDATE distro_cve_feeds SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);

        return $this->findFeed($feedId);
    }

    public function createFeed(?int $distroId, string $providerId): ?array
    {
        $providerId = trim($providerId);
        if ($providerId === '') {
            throw new \InvalidArgumentException('Provider is required');
        }

        $stmt = $this->pdo->prepare('SELECT * FROM cve_feed_providers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $providerId]);
        $provider = $stmt->fetch();
        if (!$provider) {
            throw new \InvalidArgumentException('Unknown provider');
        }

        if ($distroId !== null) {
            $distroStmt = $this->pdo->prepare('SELECT id FROM distro WHERE id = :id LIMIT 1');
            $distroStmt->execute(['id' => $distroId]);
            if (!$distroStmt->fetch()) {
                throw new \InvalidArgumentException('Unknown distro');
            }
        }

        if ($distroId === null) {
            $dup = $this->pdo->prepare(
                'SELECT id FROM distro_cve_feeds WHERE distro_id IS NULL AND provider_id = :provider_id LIMIT 1'
            );
            $dup->execute(['provider_id' => $providerId]);
        } else {
            $dup = $this->pdo->prepare(
                'SELECT id FROM distro_cve_feeds WHERE distro_id = :distro_id AND provider_id = :provider_id LIMIT 1'
            );
            $dup->execute(['distro_id' => $distroId, 'provider_id' => $providerId]);
        }

        if ($dup->fetch()) {
            throw new \RuntimeException('Feed already configured for this provider');
        }

        $defaultConfig = $this->decodeConfig($provider['default_config']);
        $insert = $this->pdo->prepare(
            'INSERT INTO distro_cve_feeds (distro_id, provider_id, enabled, priority, config)
             VALUES (:distro_id, :provider_id, 1, 100, :config)'
        );
        $insert->execute([
            'distro_id' => $distroId,
            'provider_id' => $providerId,
            'config' => json_encode($defaultConfig),
        ]);

        return $this->findFeed((int) $this->pdo->lastInsertId());
    }

    /**
     * @return array{feed: array, result: array{added: int, updated: int, message: string}}
     */
    public function syncFeed(int $feedId): array
    {
        $feed = $this->findFeed($feedId);
        if ($feed === null) {
            throw new \InvalidArgumentException('Feed not found');
        }

        if (!(bool) $feed['enabled']) {
            throw new \RuntimeException('Feed is disabled');
        }

        $logId = $this->startSyncLog($feedId);
        $this->markFeedRunning($feedId);

        try {
            $result = $this->syncEngine->syncFeed($feed);
            $this->finishSyncLog($logId, 'success', $result['added'], $result['updated'], $result['message']);
            $this->markFeedSuccess($feedId, $result['added'] + $result['updated'], $result['message']);

            return ['feed' => $this->findFeed($feedId), 'result' => $result];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            $this->finishSyncLog($logId, 'error', 0, 0, $message);
            $this->markFeedError($feedId, $message);
            $this->logger->error('CVE feed sync failed', ['feed_id' => $feedId, 'error' => $message]);
            throw $e;
        }
    }

    /**
     * @return list<array{feed_id: int, status: string, message: string}>
     */
    public function syncAllEnabled(): array
    {
        $feeds = $this->pdo->query(
            'SELECT f.id
             FROM distro_cve_feeds f
             JOIN cve_feed_providers p ON p.id = f.provider_id
             WHERE f.enabled = 1 AND p.sync_mode IN (\'bulk\', \'both\')
             ORDER BY f.priority, f.id'
        )->fetchAll();

        $results = [];
        foreach ($feeds as $feed) {
            $feedId = (int) $feed['id'];
            try {
                $sync = $this->syncFeed($feedId);
                $results[] = [
                    'feed_id' => $feedId,
                    'status' => 'success',
                    'message' => $sync['result']['message'],
                ];
            } catch (\Throwable $e) {
                $results[] = [
                    'feed_id' => $feedId,
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function listSyncLogs(int $feedId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cve_feed_sync_log
             WHERE distro_feed_id = :feed_id
             ORDER BY id DESC
             LIMIT :limit'
        );
        $stmt->bindValue('feed_id', $feedId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', max(1, min(100, $limit)), \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Match synced advisories for a distro package.
     *
     * @return array{
     *   urgency: string,
     *   cve_ids: list<string>,
     *   reference_url: ?string,
     *   bug_url: ?string,
     *   sources: list<string>
     * }
     */
    public function matchPackage(int $distroId, string $packageName): array
    {
        if (!$this->tableExists('cve_advisories')) {
            return $this->emptyMatch();
        }

        $stmt = $this->pdo->prepare(
            'SELECT a.cve_id, a.severity, a.advisory_url, a.provider_id, c.cvss_score, c.severity AS catalog_severity
             FROM cve_advisories a
             LEFT JOIN cve_catalog c ON c.cve_id = a.cve_id
             JOIN distro_cve_feeds f ON f.distro_id = a.distro_id AND f.provider_id = a.provider_id AND f.enabled = 1
             WHERE a.distro_id = :distro_id AND a.package_name = :package_name
             ORDER BY f.priority, a.synced_at DESC
             LIMIT 50'
        );
        $stmt->execute([
            'distro_id' => $distroId,
            'package_name' => $packageName,
        ]);
        $rows = $stmt->fetchAll();

        if ($rows === []) {
            return $this->emptyMatch();
        }

        $cveIds = [];
        $sources = [];
        $bestUrgency = 'unknown';
        $referenceUrl = null;
        $bugUrl = null;

        foreach ($rows as $row) {
            $cveIds[] = (string) $row['cve_id'];
            $sources[] = (string) $row['provider_id'];
            $severity = (string) ($row['severity'] ?: $row['catalog_severity'] ?: '');
            $bestUrgency = $this->maxUrgency($bestUrgency, $this->severityToUrgency($severity, $row['cvss_score'] ?? null));

            if ($referenceUrl === null) {
                $referenceUrl = $row['advisory_url'] ?: ('https://nvd.nist.gov/vuln/detail/' . $row['cve_id']);
            }
            if ($bugUrl === null && !empty($row['advisory_url'])) {
                $bugUrl = (string) $row['advisory_url'];
            }
        }

        return [
            'urgency' => $bestUrgency,
            'cve_ids' => array_values(array_unique($cveIds)),
            'reference_url' => $referenceUrl,
            'bug_url' => $bugUrl,
            'sources' => array_values(array_unique($sources)),
        ];
    }

    public function getCatalogEntry(string $cveId): ?array
    {
        if (!$this->tableExists('cve_catalog')) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM cve_catalog WHERE cve_id = :cve_id');
        $stmt->execute(['cve_id' => $cveId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $row['sources'] = $this->decodeConfig($row['sources']);
        return $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function searchAdvisories(?int $distroId, ?string $packageName, ?string $cveId, int $limit = 50): array
    {
        $where = ['1=1'];
        $params = [];

        if ($distroId !== null) {
            $where[] = 'a.distro_id = :distro_id';
            $params['distro_id'] = $distroId;
        }
        if ($packageName !== null && $packageName !== '') {
            $where[] = 'a.package_name LIKE :package_name';
            $params['package_name'] = '%' . $packageName . '%';
        }
        if ($cveId !== null && $cveId !== '') {
            $where[] = 'a.cve_id = :cve_id';
            $params['cve_id'] = strtoupper($cveId);
        }

        $sql = 'SELECT a.*, d.distro_name, p.name AS provider_name
                FROM cve_advisories a
                LEFT JOIN distro d ON d.id = a.distro_id
                LEFT JOIN cve_feed_providers p ON p.id = a.provider_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.synced_at DESC
                LIMIT ' . max(1, min(200, $limit));

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function startSyncLog(int $feedId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO cve_feed_sync_log (distro_feed_id, started_at, status)
             VALUES (:feed_id, NOW(), \'running\')'
        );
        $stmt->execute(['feed_id' => $feedId]);

        return (int) $this->pdo->lastInsertId();
    }

    private function finishSyncLog(int $logId, string $status, int $added, int $updated, string $message): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE cve_feed_sync_log
             SET finished_at = NOW(), status = :status, records_added = :added, records_updated = :updated, message = :message
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $logId,
            'status' => $status,
            'added' => $added,
            'updated' => $updated,
            'message' => mb_substr($message, 0, 65000),
        ]);
    }

    private function markFeedRunning(int $feedId): void
    {
        $this->pdo->prepare(
            'UPDATE distro_cve_feeds SET last_sync_status = \'running\', last_sync_message = NULL WHERE id = :id'
        )->execute(['id' => $feedId]);
    }

    private function markFeedSuccess(int $feedId, int $records, string $message): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE distro_cve_feeds
             SET last_sync_at = NOW(), last_sync_status = \'success\', last_sync_message = :message, records_synced = :records
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $feedId,
            'message' => mb_substr($message, 0, 65000),
            'records' => $records,
        ]);
    }

    private function markFeedError(int $feedId, string $message): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE distro_cve_feeds
             SET last_sync_at = NOW(), last_sync_status = \'error\', last_sync_message = :message
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $feedId,
            'message' => mb_substr($message, 0, 65000),
        ]);
    }

    /**
     * @return array{urgency: string, cve_ids: list<string>, reference_url: null, bug_url: null, sources: list<string>}
     */
    private function emptyMatch(): array
    {
        return [
            'urgency' => 'unknown',
            'cve_ids' => [],
            'reference_url' => null,
            'bug_url' => null,
            'sources' => [],
        ];
    }

    private function severityToUrgency(string $severity, mixed $cvssScore): string
    {
        $severity = strtolower(trim($severity));
        if ($severity !== '') {
            return match (true) {
                str_contains($severity, 'crit'), str_contains($severity, 'emergency') => 'high',
                str_contains($severity, 'high'), str_contains($severity, 'important') => 'high',
                str_contains($severity, 'med'), str_contains($severity, 'mod') => 'medium',
                str_contains($severity, 'low') => 'low',
                default => 'unknown',
            };
        }

        if ($cvssScore !== null && $cvssScore !== '') {
            $score = (float) $cvssScore;
            return match (true) {
                $score >= 7.0 => 'high',
                $score >= 4.0 => 'medium',
                $score > 0 => 'low',
                default => 'unknown',
            };
        }

        return 'unknown';
    }

    private function maxUrgency(string $current, string $candidate): string
    {
        $rank = ['unknown' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'emergency' => 4];
        $currentRank = $rank[$current] ?? 0;
        $candidateRank = $rank[$candidate] ?? 0;

        return $candidateRank > $currentRank ? $candidate : $current;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeConfig(mixed $config): array
    {
        if (is_array($config)) {
            return $config;
        }

        if (is_string($config) && $config !== '') {
            $decoded = json_decode($config, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->query('SHOW TABLES LIKE ' . $this->pdo->quote($table));
        return (bool) $stmt->fetch();
    }
}
