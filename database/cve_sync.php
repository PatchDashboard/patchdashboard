#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Sync enabled CVE feeds from external databases.
 *
 * Usage:
 *   php database/cve_sync.php [--all]
 *   php database/cve_sync.php --feed=12
 *   php database/cve_sync.php --provider=nvd
 *   php database/cve_sync.php --distro=Ubuntu
 */

$scriptDir = __DIR__;
$autoload = null;
foreach ([dirname($scriptDir) . '/api/vendor/autoload.php', '/app/vendor/autoload.php'] as $candidate) {
    if (is_readable($candidate)) {
        $autoload = $candidate;
        break;
    }
}
if ($autoload === null) {
    fwrite(STDERR, "Composer autoload not found. Run composer install in api/.\n");
    exit(1);
}
require $autoload;

use PatchDashboard\Api\Config\Database;
use PatchDashboard\Api\Services\CveFeedService;
use PatchDashboard\Api\Services\CveFeedSyncEngine;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

foreach ([dirname($scriptDir) . '/api/.env', '/app/.env'] as $dotenvPath) {
    if (!is_readable($dotenvPath)) {
        continue;
    }
    $dotenv = Dotenv\Dotenv::createImmutable(dirname($dotenvPath));
    $dotenv->safeLoad();
    break;
}

$memoryLimit = $_ENV['CVE_SYNC_MEMORY_LIMIT'] ?? getenv('CVE_SYNC_MEMORY_LIMIT') ?: '';
if ($memoryLimit !== '') {
    ini_set('memory_limit', (string) $memoryLimit);
}

$options = getopt('', ['all', 'feed:', 'provider:', 'distro:']);
$pdo = Database::connect();
$logger = new Logger('cve-sync');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

$syncEngine = new CveFeedSyncEngine($pdo, $logger);
$service = new CveFeedService($pdo, $syncEngine, $logger);

if (!empty($options['all'])) {
    echo "Syncing all enabled bulk feeds...\n";
    $results = $service->syncAllEnabled();
    foreach ($results as $result) {
        echo sprintf(
            "Feed #%d: %s — %s\n",
            $result['feed_id'],
            $result['status'],
            $result['message'],
        );
    }
    exit(0);
}

if (!empty($options['feed'])) {
    $feedId = (int) $options['feed'];
    echo "Syncing feed #{$feedId}...\n";
    $result = $service->syncFeed($feedId);
    echo $result['result']['message'] . "\n";
    exit(0);
}

$where = ['f.enabled = 1', "p.sync_mode IN ('bulk', 'both')"];
$params = [];

if (!empty($options['provider'])) {
    $where[] = 'f.provider_id = :provider';
    $params['provider'] = (string) $options['provider'];
}
if (!empty($options['distro'])) {
    $where[] = 'd.distro_name = :distro';
    $params['distro'] = (string) $options['distro'];
}

$sql = 'SELECT f.id, COALESCE(d.distro_name, \'Global\') AS distro_name, f.provider_id
        FROM distro_cve_feeds f
        LEFT JOIN distro d ON d.id = f.distro_id
        JOIN cve_feed_providers p ON p.id = f.provider_id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY f.priority, f.id';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$feeds = $stmt->fetchAll();

if ($feeds === []) {
    fwrite(STDERR, "No matching feeds found.\n");
    exit(1);
}

foreach ($feeds as $feed) {
    $feedId = (int) $feed['id'];
    echo sprintf(
        "Syncing %s / %s (feed #%d)...\n",
        $feed['distro_name'],
        $feed['provider_id'],
        $feedId,
    );
    try {
        $result = $service->syncFeed($feedId);
        echo '  ' . $result['result']['message'] . "\n";
    } catch (Throwable $e) {
        fwrite(STDERR, '  ERROR: ' . $e->getMessage() . "\n");
    }
}

echo "Done.\n";
