<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

use Psr\Log\LoggerInterface;

/**
 * Downloads and parses external CVE feeds (NVD API, OSV bulk zips).
 */
final class CveFeedSyncEngine
{
    private const OSV_BUCKET = 'https://osv-vulnerabilities.storage.googleapis.com';
    private const NVD_API = 'https://services.nvd.nist.gov/rest/json/cves/2.0';
    private const NVD_WINDOW_MAX_RESULTS = 8000;

    public function __construct(
        private \PDO $pdo,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $feedRow joined distro_cve_feeds + provider fields
     * @return array{added: int, updated: int, message: string}
     */
    public function syncFeed(array $feedRow): array
    {
        $providerId = (string) $feedRow['provider_id'];
        $syncMode = (string) ($feedRow['sync_mode'] ?? 'bulk');

        if ($syncMode === 'on_demand') {
            return [
                'added' => 0,
                'updated' => 0,
                'message' => 'On-demand provider; no bulk sync required.',
            ];
        }

        $config = $this->decodeConfig($feedRow['config'] ?? null);
        $defaultConfig = $this->decodeConfig($feedRow['default_config'] ?? null);
        $config = array_merge($defaultConfig, $config);

        return match ($providerId) {
            'nvd' => $this->syncNvd($config),
            'github' => $this->syncOsvBucket(
                $providerId,
                null,
                (string) ($config['ecosystem'] ?? 'GHSA'),
                catalogOnly: true,
            ),
            'debian', 'ubuntu_usn', 'redhat', 'rocky', 'almalinux', 'fedora' => $this->syncOsvBucket(
                $providerId,
                (int) $feedRow['distro_id'],
                (string) ($config['ecosystem'] ?? $this->defaultEcosystem($providerId)),
            ),
            default => throw new \RuntimeException("Unknown provider: {$providerId}"),
        };
    }

    /**
     * @param array<string, mixed> $config
     * @return array{added: int, updated: int, message: string}
     */
    private function syncNvd(array $config): array
    {
        $lookbackDays = max(1, min(120, (int) ($config['lookback_days'] ?? 7)));
        $rangeEnd = new \DateTimeImmutable('now');
        $rangeStart = $rangeEnd->modify("-{$lookbackDays} days")->setTime(0, 0, 0);

        $added = 0;
        $updated = 0;

        for ($windowStart = $rangeStart; $windowStart <= $rangeEnd; $windowStart = $windowStart->modify('+1 day')) {
            $windowEnd = $windowStart->setTime(23, 59, 59);
            if ($windowEnd > $rangeEnd) {
                $windowEnd = $rangeEnd;
            }

            $counts = $this->syncNvdWindow($windowStart, $windowEnd);
            $added += $counts['added'];
            $updated += $counts['updated'];
        }

        return [
            'added' => $added,
            'updated' => $updated,
            'message' => "NVD sync complete ({$lookbackDays} day lookback).",
        ];
    }

    /**
     * @return array{added: int, updated: int}
     */
    private function syncNvdWindow(\DateTimeImmutable $windowStart, \DateTimeImmutable $windowEnd): array
    {
        $start = $windowStart->format('Y-m-d\TH:i:s.v');
        $end = $windowEnd->format('Y-m-d\TH:i:s.v');

        $probe = $this->fetchJson(
            self::NVD_API . '?' . http_build_query([
                'lastModStartDate' => $start,
                'lastModEndDate' => $end,
                'resultsPerPage' => 1,
            ]),
            $this->nvdHeaders(),
        );
        if ($probe === null) {
            $this->logger->warning('Skipping NVD window after request failure', [
                'start' => $start,
                'end' => $end,
            ]);

            return ['added' => 0, 'updated' => 0];
        }

        $totalResults = (int) ($probe['totalResults'] ?? 0);
        if ($totalResults === 0) {
            return ['added' => 0, 'updated' => 0];
        }

        $windowSeconds = $windowEnd->getTimestamp() - $windowStart->getTimestamp();
        if ($totalResults > self::NVD_WINDOW_MAX_RESULTS && $windowSeconds > 3600) {
            $midTs = (int) (($windowStart->getTimestamp() + $windowEnd->getTimestamp()) / 2);
            $leftEnd = (new \DateTimeImmutable('@' . $midTs))->setTimezone($windowStart->getTimezone());
            $rightStart = $leftEnd->modify('+1 second');

            $this->logger->info('Splitting oversized NVD window', [
                'start' => $start,
                'end' => $end,
                'totalResults' => $totalResults,
            ]);

            $left = $this->syncNvdWindow($windowStart, $leftEnd);
            $right = $this->syncNvdWindow($rightStart, $windowEnd);

            return [
                'added' => $left['added'] + $right['added'],
                'updated' => $left['updated'] + $right['updated'],
            ];
        }

        if ($totalResults > self::NVD_WINDOW_MAX_RESULTS) {
            $this->logger->warning('Skipping NVD window that still exceeds result cap after splitting', [
                'start' => $start,
                'end' => $end,
                'totalResults' => $totalResults,
            ]);

            return ['added' => 0, 'updated' => 0];
        }

        $added = 0;
        $updated = 0;
        $startIndex = 0;

        do {
            $payload = $this->fetchJson(
                self::NVD_API . '?' . http_build_query([
                    'lastModStartDate' => $start,
                    'lastModEndDate' => $end,
                    'startIndex' => $startIndex,
                    'resultsPerPage' => 2000,
                ]),
                $this->nvdHeaders(),
            );

            if ($payload === null) {
                $this->logger->warning('Stopping NVD window pagination after request failure', [
                    'start' => $start,
                    'end' => $end,
                    'startIndex' => $startIndex,
                ]);
                break;
            }

            foreach ($payload['vulnerabilities'] ?? [] as $entry) {
                $cve = $entry['cve'] ?? [];
                $cveId = (string) ($cve['id'] ?? '');
                if ($cveId === '' || !str_starts_with($cveId, 'CVE-')) {
                    continue;
                }

                $summary = $this->firstEnglishDescription($cve['descriptions'] ?? []);
                $severity = null;
                $cvss = null;
                foreach ($cve['metrics'] ?? [] as $metricGroup) {
                    foreach (['cvssMetricV31', 'cvssMetricV30', 'cvssMetricV2'] as $key) {
                        if (!isset($metricGroup[$key][0]['cvssData'])) {
                            continue;
                        }
                        $data = $metricGroup[$key][0]['cvssData'];
                        $cvss = isset($data['baseScore']) ? (float) $data['baseScore'] : null;
                        $severity = $this->mapCvssSeverity($cvss, $data['baseSeverity'] ?? null);
                        break 2;
                    }
                }

                $published = $this->parseIsoDate($cve['published'] ?? null);
                $modified = $this->parseIsoDate($cve['lastModified'] ?? null);
                $changed = $this->upsertCatalog($cveId, $summary, $severity, $cvss, $published, $modified, ['nvd']);
                if ($changed === 'insert') {
                    $added++;
                } elseif ($changed === 'update') {
                    $updated++;
                }
            }

            $startIndex += 2000;
            if ($totalResults > 0 && $startIndex < $totalResults) {
                usleep(1600000);
            }
        } while ($startIndex < $totalResults);

        return ['added' => $added, 'updated' => $updated];
    }

    /**
     * @return array{added: int, updated: int, message: string}
     */
    private function syncOsvBucket(
        string $providerId,
        ?int $distroId,
        string $ecosystem,
        bool $catalogOnly = false,
    ): array {
        [$zipUrl, $recordIdPrefix] = $this->resolveOsvZipTarget($ecosystem);
        $zipPath = $this->downloadToTemp($zipUrl);
        if ($zipPath === null) {
            throw new \RuntimeException("Failed to download OSV archive from {$zipUrl}");
        }

        if (!class_exists(\ZipArchive::class)) {
            @unlink($zipPath);
            throw new \RuntimeException('PHP zip extension is required (install php-zip or enable ext-zip)');
        }

        $zip = new \ZipArchive();
        $openResult = $zip->open($zipPath);
        if ($openResult !== true) {
            $size = filesize($zipPath) ?: 0;
            @unlink($zipPath);
            throw new \RuntimeException(
                "Failed to open OSV zip from {$zipUrl} (code {$openResult}, downloaded {$size} bytes)"
            );
        }

        $added = 0;
        $updated = 0;
        $upsert = $this->pdo->prepare(
            'INSERT INTO cve_advisories
                (provider_id, distro_id, cve_id, package_name, fixed_version, severity, title, advisory_url, published_at, raw_ref, synced_at)
             VALUES
                (:provider_id, :distro_id, :cve_id, :package_name, :fixed_version, :severity, :title, :advisory_url, :published_at, :raw_ref, NOW())
             ON DUPLICATE KEY UPDATE
                fixed_version = VALUES(fixed_version),
                severity = VALUES(severity),
                title = VALUES(title),
                advisory_url = VALUES(advisory_url),
                published_at = VALUES(published_at),
                raw_ref = VALUES(raw_ref),
                synced_at = NOW()'
        );

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || !str_ends_with($name, '.json')) {
                continue;
            }

            $raw = $zip->getFromIndex($i);
            if ($raw === false) {
                continue;
            }

            $doc = json_decode($raw, true);
            unset($raw);
            if (!is_array($doc)) {
                continue;
            }

            $docId = (string) ($doc['id'] ?? '');
            if ($recordIdPrefix !== null && !str_starts_with($docId, $recordIdPrefix)) {
                unset($doc);
                continue;
            }

            $cveIds = $this->extractCveIds($doc);
            if ($cveIds === []) {
                continue;
            }

            $severity = $this->extractOsvSeverity($doc);
            $title = isset($doc['summary']) ? (string) $doc['summary'] : null;
            $published = $this->parseIsoDate($doc['published'] ?? null);
            $advisoryUrl = isset($doc['database_specific']['url']) ? (string) $doc['database_specific']['url'] : null;
            $rawRef = isset($doc['id']) ? (string) $doc['id'] : null;

            foreach ($doc['affected'] ?? [] as $affected) {
                $packageName = (string) ($affected['package']['name'] ?? '');
                if ($packageName === '') {
                    continue;
                }

                $fixedVersion = $this->extractFixedVersion($affected);
                foreach ($cveIds as $cveId) {
                    if (!$catalogOnly) {
                        $upsert->execute([
                            'provider_id' => $providerId,
                            'distro_id' => $distroId,
                            'cve_id' => $cveId,
                            'package_name' => $packageName,
                            'fixed_version' => $fixedVersion,
                            'severity' => $severity,
                            'title' => $title !== null ? mb_substr($title, 0, 512) : null,
                            'advisory_url' => $advisoryUrl,
                            'published_at' => $published,
                            'raw_ref' => $rawRef,
                        ]);

                        $rowCount = $upsert->rowCount();
                        if ($rowCount === 1) {
                            $added++;
                        } elseif ($rowCount === 2) {
                            $updated++;
                        }
                    }

                    $changed = $this->upsertCatalog(
                        $cveId,
                        $title,
                        $severity,
                        null,
                        $published,
                        $this->parseIsoDate($doc['modified'] ?? null),
                        [$providerId],
                    );
                    if ($catalogOnly) {
                        if ($changed === 'insert') {
                            $added++;
                        } elseif ($changed === 'update') {
                            $updated++;
                        }
                    }
                }
            }

            unset($doc, $cveIds, $severity, $title, $published, $advisoryUrl, $rawRef);
        }

        $zip->close();
        @unlink($zipPath);

        return [
            'added' => $added,
            'updated' => $updated,
            'message' => $recordIdPrefix !== null
                ? "OSV GHSA catalog sync complete."
                : "OSV {$ecosystem} sync complete.",
        ];
    }

    /**
     * GHSA advisories are exported in the unified OSV all.zip, not GHSA/all.zip.
     *
     * @return array{0: string, 1: string|null}
     */
    private function resolveOsvZipTarget(string $ecosystem): array
    {
        if (strtoupper($ecosystem) === 'GHSA') {
            return [self::OSV_BUCKET . '/all.zip', 'GHSA-'];
        }

        return [self::OSV_BUCKET . '/' . rawurlencode($ecosystem) . '/all.zip', null];
    }

    /**
     * @param array<int, array<string, mixed>> $descriptions
     */
    private function firstEnglishDescription(array $descriptions): ?string
    {
        foreach ($descriptions as $desc) {
            if (($desc['lang'] ?? '') === 'en' && isset($desc['value'])) {
                return mb_substr((string) $desc['value'], 0, 1024);
            }
        }

        return isset($descriptions[0]['value']) ? mb_substr((string) $descriptions[0]['value'], 0, 1024) : null;
    }

    /**
     * @param array<string, mixed> $doc
     * @return list<string>
     */
    private function extractCveIds(array $doc): array
    {
        $ids = [];
        $candidate = (string) ($doc['id'] ?? '');
        if (str_starts_with($candidate, 'CVE-')) {
            $ids[] = $candidate;
        }

        foreach ($doc['aliases'] ?? [] as $alias) {
            $alias = (string) $alias;
            if (str_starts_with($alias, 'CVE-')) {
                $ids[] = $alias;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string, mixed> $doc
     */
    private function extractOsvSeverity(array $doc): ?string
    {
        if (isset($doc['database_specific']['severity'])) {
            return $this->normalizeSeverity((string) $doc['database_specific']['severity']);
        }

        foreach ($doc['severity'] ?? [] as $entry) {
            if (isset($entry['score'])) {
                return $this->mapCvssSeverity((float) $entry['score'], null);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $affected
     */
    private function extractFixedVersion(array $affected): ?string
    {
        foreach ($affected['ranges'] ?? [] as $range) {
            foreach ($range['events'] ?? [] as $event) {
                if (isset($event['fixed'])) {
                    return (string) $event['fixed'];
                }
            }
        }

        return null;
    }

    /**
     * @param list<string> $sources
     * @return 'insert'|'update'|null
     */
    private function upsertCatalog(
        string $cveId,
        ?string $summary,
        ?string $severity,
        ?float $cvss,
        ?string $published,
        ?string $modified,
        array $sources,
    ): ?string {
        $existing = $this->pdo->prepare('SELECT cve_id, sources FROM cve_catalog WHERE cve_id = :cve_id');
        $existing->execute(['cve_id' => $cveId]);
        $row = $existing->fetch();

        $mergedSources = $sources;
        if ($row && !empty($row['sources'])) {
            $decoded = json_decode((string) $row['sources'], true);
            if (is_array($decoded)) {
                $mergedSources = array_values(array_unique(array_merge($decoded, $sources)));
            }
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO cve_catalog (cve_id, summary, severity, cvss_score, published_at, modified_at, sources, synced_at)
             VALUES (:cve_id, :summary, :severity, :cvss_score, :published_at, :modified_at, :sources, NOW())
             ON DUPLICATE KEY UPDATE
                summary = COALESCE(VALUES(summary), summary),
                severity = COALESCE(VALUES(severity), severity),
                cvss_score = COALESCE(VALUES(cvss_score), cvss_score),
                published_at = COALESCE(VALUES(published_at), published_at),
                modified_at = COALESCE(VALUES(modified_at), modified_at),
                sources = VALUES(sources),
                synced_at = NOW()'
        );
        $stmt->execute([
            'cve_id' => $cveId,
            'summary' => $summary,
            'severity' => $severity,
            'cvss_score' => $cvss,
            'published_at' => $published,
            'modified_at' => $modified,
            'sources' => json_encode($mergedSources),
        ]);

        if (!$row) {
            return 'insert';
        }

        return $stmt->rowCount() >= 1 ? 'update' : null;
    }

    private function defaultEcosystem(string $providerId): string
    {
        return match ($providerId) {
            'github' => 'GHSA',
            'debian' => 'Debian',
            'ubuntu_usn' => 'Ubuntu',
            'redhat' => 'Red Hat',
            'rocky' => 'Rocky Linux',
            'almalinux' => 'AlmaLinux',
            'fedora' => 'Fedora',
            default => $providerId,
        };
    }

    /**
     * @return array<string, string>
     */
    private function nvdHeaders(): array
    {
        $headers = ['User-Agent: PatchDashboard/2.0'];
        $apiKey = $_ENV['NVD_API_KEY'] ?? getenv('NVD_API_KEY') ?: '';
        if ($apiKey !== '') {
            $headers[] = 'apiKey: ' . $apiKey;
        }

        return $headers;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>|null
     */
    private function fetchJson(string $url, array $headers = []): ?array
    {
        $response = $this->httpRequest($url, $headers);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $this->logger->warning('HTTP JSON fetch failed', [
                'url' => $url,
                'status' => $response['status'],
                'error' => $response['error'],
                'body' => mb_substr($response['body'], 0, 240),
            ]);
            return null;
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            $this->logger->warning('HTTP JSON decode failed', [
                'url' => $url,
                'status' => $response['status'],
                'body' => mb_substr($response['body'], 0, 240),
            ]);
            return null;
        }

        return $decoded;
    }

    private function downloadToTemp(string $url): ?string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pd-osv-');
        if ($tmp === false) {
            return null;
        }

        $response = $this->httpRequest($url, ['User-Agent: PatchDashboard/2.0'], $tmp, 900);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            @unlink($tmp);
            $this->logger->warning('HTTP download failed', [
                'url' => $url,
                'status' => $response['status'],
                'error' => $response['error'],
                'body' => mb_substr($response['body'], 0, 240),
            ]);
            return null;
        }

        if (!is_file($tmp) || (filesize($tmp) ?: 0) === 0) {
            @unlink($tmp);
            return null;
        }

        return $tmp;
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, body: string, error: ?string}
     */
    private function httpRequest(string $url, array $headers = [], ?string $destPath = null, int $timeout = 180): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP curl extension is required for CVE feed sync');
        }

        $headerLines = $headers;
        if (!in_array('Accept: application/json', $headerLines, true) && $destPath === null) {
            $headerLines[] = 'Accept: application/json';
        }

        $githubToken = $_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN') ?: '';
        if ($githubToken !== '' && str_contains($url, 'api.github.com')) {
            $headerLines[] = 'Authorization: Bearer ' . $githubToken;
        }

        $attempt = 0;
        $lastStatus = 0;
        $lastError = null;
        $body = '';

        while ($attempt < 6) {
            $attempt++;
            $ch = curl_init($url);
            if ($ch === false) {
                return ['status' => 0, 'body' => '', 'error' => 'curl_init failed'];
            }

            $fileHandle = null;
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 30,
                CURLOPT_HTTPHEADER => $headerLines,
                CURLOPT_USERAGENT => 'PatchDashboard/2.0',
            ]);

            if ($destPath !== null) {
                $fileHandle = fopen($destPath, 'wb');
                if ($fileHandle === false) {
                    curl_close($ch);
                    return ['status' => 0, 'body' => '', 'error' => 'Unable to open temp file'];
                }
                curl_setopt($ch, CURLOPT_FILE, $fileHandle);
            } else {
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            }

            $result = curl_exec($ch);
            $lastStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $lastError = curl_error($ch) ?: null;
            curl_close($ch);
            if ($fileHandle !== null) {
                fclose($fileHandle);
            }

            if ($destPath === null) {
                $body = is_string($result) ? $result : '';
            }

            if ($result !== false && $lastStatus >= 200 && $lastStatus < 300) {
                return [
                    'status' => $lastStatus,
                    'body' => $body,
                    'error' => null,
                ];
            }

            if ($destPath !== null && is_file($destPath)) {
                $body = (string) file_get_contents($destPath, false, null, 0, 4096);
            }

            if ($result === false && $lastError !== null) {
                sleep(min($attempt, 10));
                continue;
            }

            if ($lastStatus === 429 || $lastStatus === 503 || $lastStatus === 502) {
                sleep(min($attempt * 3, 45));
                continue;
            }

            break;
        }

        return ['status' => $lastStatus, 'body' => $body, 'error' => $lastError];
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

    private function parseIsoDate(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return null;
        }
    }

    private function mapCvssSeverity(?float $score, ?string $label): ?string
    {
        if ($label !== null && $label !== '') {
            return $this->normalizeSeverity($label);
        }

        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= 9.0 => 'critical',
            $score >= 7.0 => 'high',
            $score >= 4.0 => 'medium',
            default => 'low',
        };
    }

    private function normalizeSeverity(string $severity): string
    {
        $severity = strtolower(trim($severity));

        return match (true) {
            str_contains($severity, 'crit') => 'critical',
            str_contains($severity, 'high'), str_contains($severity, 'important') => 'high',
            str_contains($severity, 'med'), str_contains($severity, 'mod') => 'medium',
            str_contains($severity, 'low') => 'low',
            default => $severity,
        };
    }
}
