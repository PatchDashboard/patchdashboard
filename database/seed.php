#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Load development mock data for PatchDashboard v2.
 *
 * Usage:
 *   php database/seed.php [--fresh]
 *
 * --fresh  Clears mock servers/patches/containers before seeding (keeps users).
 */

$fresh = in_array('--fresh', $argv ?? [], true);

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'patchdashboard';
$dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
$user = getenv('DB_USER') ?: 'patchdashboard';
$pass = getenv('DB_PASS') ?: 'patchdashboard';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

echo "Seeding mock data" . ($fresh ? ' (fresh)' : '') . "...\n";

if ($fresh) {
    clearMockData($pdo);
}

seedCompany($pdo);
seedUsers($pdo);
$serverIds = seedServers($pdo);
seedPatches($pdo);
seedPackages($pdo);
seedSuppressions($pdo);
seedContainers($pdo, $serverIds);
seedAlerts($pdo);

echo "Mock data seeded successfully.\n";

function clearMockData(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ([
        'alert_events',
        'container_vulnerabilities',
        'containers',
        'container_vuln_suppressions',
        'patches',
        'patch_allpackages',
        'servers',
    ] as $table) {
        $pdo->exec("DELETE FROM {$table}");
    }
    $suppressionTable = tableExists($pdo, 'suppressed') ? 'suppressed' : 'supressed';
    $pdo->exec("DELETE FROM {$suppressionTable} WHERE package_name LIKE 'mock-%' OR server_name LIKE '%.example.com'");
    $pdo->exec("DELETE FROM webhook_endpoints WHERE name LIKE 'Mock%'");
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    echo "Cleared existing mock rows.\n";
}

function seedCompany(PDO $pdo): void
{
    $stmt = $pdo->query('SELECT COUNT(*) FROM company');
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $pdo->prepare(
        'INSERT INTO company (name, display_name, install_key) VALUES (:name, :display_name, :install_key)'
    )->execute([
        'name' => 'patchdashboard',
        'display_name' => 'Test, INC',
        'install_key' => 'dev-install-key-change-in-production',
    ]);
    echo "  company\n";
}

function seedUsers(PDO $pdo): void
{
    $users = [
        ['user_id' => 'operator', 'email' => 'operator@example.com', 'display_name' => 'Ops Operator', 'admin' => 0, 'password' => 'operator', 'receive_alerts' => 1],
        ['user_id' => 'viewer', 'email' => 'viewer@example.com', 'display_name' => 'Read Only', 'admin' => 0, 'password' => 'viewer', 'receive_alerts' => 0],
        ['user_id' => 'backup-admin', 'email' => 'backup-admin@example.com', 'display_name' => 'Backup Admin', 'admin' => 1, 'password' => 'admin123', 'receive_alerts' => 1],
    ];

    $insert = $pdo->prepare(
        'INSERT INTO users (user_id, email, admin, display_name, password, active, receive_alerts, last_seen)
         VALUES (:user_id, :email, :admin, :display_name, :password, 1, :receive_alerts, NOW())
         ON DUPLICATE KEY UPDATE
           email = VALUES(email),
           display_name = VALUES(display_name),
           admin = VALUES(admin),
           receive_alerts = VALUES(receive_alerts),
           active = 1'
    );

    foreach ($users as $row) {
        $insert->execute([
            'user_id' => $row['user_id'],
            'email' => $row['email'],
            'admin' => $row['admin'],
            'display_name' => $row['display_name'],
            'password' => password_hash($row['password'], PASSWORD_BCRYPT),
            'receive_alerts' => $row['receive_alerts'],
        ]);
    }
    echo "  users (operator/viewer/backup-admin)\n";
}

function seedServers(PDO $pdo): array
{
    $servers = [
        ['name' => 'web01.example.com', 'alias' => 'Web Front 01', 'group' => 'production', 'ip' => '10.0.1.11', 'distro' => 'Ubuntu', 'version' => '24.04_Server', 'trusted' => 1, 'capabilities' => ['docker' => true, 'podman' => false, 'proxmox' => false], 'ssh_user' => 'root'],
        ['name' => 'web02.example.com', 'alias' => 'Web Front 02', 'group' => 'production', 'ip' => '10.0.1.12', 'distro' => 'Ubuntu', 'version' => '22.04_Server', 'trusted' => 1, 'capabilities' => ['docker' => true, 'podman' => false, 'proxmox' => false], 'ssh_user' => 'deploy'],
        ['name' => 'db01.example.com', 'alias' => 'Database Primary', 'group' => 'production', 'ip' => '10.0.2.10', 'distro' => 'Debian', 'version' => '12', 'trusted' => 1, 'capabilities' => ['docker' => false, 'podman' => false, 'proxmox' => false], 'ssh_user' => 'root'],
        ['name' => 'pve01.example.com', 'alias' => 'Proxmox Host', 'group' => 'virtualization', 'ip' => '10.0.0.5', 'distro' => 'Debian', 'version' => '12', 'trusted' => 1, 'capabilities' => ['docker' => false, 'podman' => false, 'proxmox' => true], 'ssh_user' => 'root'],
        ['name' => 'app01.example.com', 'alias' => 'App Server RHEL', 'group' => 'production', 'ip' => '10.0.1.20', 'distro' => 'RHEL', 'version' => '9', 'trusted' => 1, 'capabilities' => ['docker' => false, 'podman' => true, 'proxmox' => false], 'ssh_user' => 'ansible'],
        ['name' => 'legacy01.example.com', 'alias' => 'Legacy CentOS 7', 'group' => 'legacy', 'ip' => '10.0.9.50', 'distro' => 'CentOS', 'version' => '7', 'trusted' => 1, 'capabilities' => ['docker' => true, 'podman' => false, 'proxmox' => false], 'ssh_user' => 'root'],
        ['name' => 'staging01.example.com', 'alias' => 'Staging (untrusted)', 'group' => 'staging', 'ip' => '10.0.3.15', 'distro' => 'Rocky Linux', 'version' => '9', 'trusted' => 0, 'capabilities' => ['docker' => true, 'podman' => false, 'proxmox' => false], 'ssh_user' => 'root'],
    ];

    $lookupDistro = $pdo->prepare('SELECT id FROM distro WHERE distro_name = :name LIMIT 1');
    $lookupVersion = $pdo->prepare(
        'SELECT id FROM distro_version WHERE distro_id = :distro_id AND version_num = :version LIMIT 1'
    );
    $insert = $pdo->prepare(
        'INSERT INTO servers
         (server_name, server_alias, server_group, distro_id, distro_version, server_ip, client_key, trusted, last_seen, last_checked, host_capabilities, scan_containers, ssh_user)
         VALUES
         (:server_name, :server_alias, :server_group, :distro_id, :distro_version, :server_ip, :client_key, :trusted, NOW(), NOW(), :host_capabilities, 1, :ssh_user)
         ON DUPLICATE KEY UPDATE
           server_alias = VALUES(server_alias),
           server_group = VALUES(server_group),
           distro_id = VALUES(distro_id),
           distro_version = VALUES(distro_version),
           server_ip = VALUES(server_ip),
           trusted = VALUES(trusted),
           last_seen = NOW(),
           host_capabilities = VALUES(host_capabilities),
           ssh_user = VALUES(ssh_user)'
    );

    $ids = [];
    $index = 1;
    foreach ($servers as $server) {
        $lookupDistro->execute(['name' => $server['distro']]);
        $distroId = (int) $lookupDistro->fetchColumn();
        $lookupVersion->execute(['distro_id' => $distroId, 'version' => $server['version']]);
        $versionId = (int) $lookupVersion->fetchColumn();

        $clientKey = hash('sha256', 'mock-client-key-' . $server['name']);
        $insert->execute([
            'server_name' => $server['name'],
            'server_alias' => $server['alias'],
            'server_group' => $server['group'],
            'distro_id' => $distroId,
            'distro_version' => $versionId,
            'server_ip' => $server['ip'],
            'client_key' => $clientKey,
            'trusted' => $server['trusted'],
            'host_capabilities' => json_encode($server['capabilities']),
            'ssh_user' => $server['ssh_user'],
        ]);

        $stmt = $pdo->prepare('SELECT id FROM servers WHERE server_name = :name LIMIT 1');
        $stmt->execute(['name' => $server['name']]);
        $ids[$server['name']] = (int) $stmt->fetchColumn();
        $index++;
    }

    echo "  servers (" . count($servers) . ")\n";
    return $ids;
}

function seedPatches(PDO $pdo): void
{
    $patchSets = [
        'web01.example.com' => [
            ['nginx', '1.24.0-2ubuntu7', '1.24.0-2ubuntu7.4'],
            ['openssl', '3.0.13-0ubuntu3', '3.0.13-0ubuntu3.5'],
            ['curl', '8.5.0-2ubuntu10', '8.5.0-2ubuntu10.6'],
        ],
        'web02.example.com' => [
            ['nginx', '1.18.0-6ubuntu14', '1.18.0-6ubuntu14.7'],
            ['python3', '3.10.12-1~22.04', '3.10.12-1~22.04.14'],
        ],
        'db01.example.com' => [
            ['mariadb-server', '1:10.11.11-0+deb12u1', '1:10.11.14-0+deb12u2'],
            ['libssl3', '3.0.17-1~deb12u2', '3.0.18-1~deb12u1'],
        ],
        'pve01.example.com' => [
            ['pve-manager', '8.3.3', '8.4.0'],
            ['libproxmox-backup-qemu0', '1.4.0', '1.5.0'],
        ],
        'app01.example.com' => [
            ['httpd', '2.4.62-1.el9', '2.4.62-1.el9_6.4'],
            ['kernel', '5.14.0-503.14.1.el9_5', '5.14.0-503.34.1.el9_5'],
            ['podman', '5:5.4.0-5.el9_6', '5:5.4.0-9.el9_6'],
        ],
        'legacy01.example.com' => [
            ['kernel', '3.10.0-1160.119.1.el7', '3.10.0-1160.126.1.el7'],
            ['openssh', '7.4p1-23.el7_9', '7.4p1-23.el7_9'],
        ],
        'staging01.example.com' => [
            ['systemd', '252-46.el9', '252-51.el9'],
        ],
    ];

    $insert = $pdo->prepare(
        'INSERT INTO patches (server_name, package_name, current, new, urgency, to_upgrade, upgraded)
         VALUES (:server_name, :package_name, :current, :new, :urgency, 0, 0)'
    );

    foreach ($patchSets as $serverName => $patches) {
        $pdo->prepare('DELETE FROM patches WHERE server_name = :server_name')->execute(['server_name' => $serverName]);
        foreach ($patches as [$pkg, $current, $new]) {
            $insert->execute([
                'server_name' => $serverName,
                'package_name' => $pkg,
                'current' => $current,
                'new' => $new,
                'urgency' => str_contains($pkg, 'kernel') || str_contains($pkg, 'openssl') ? 'high' : 'medium',
            ]);
        }
    }

    echo "  patches\n";
}

function seedPackages(PDO $pdo): void
{
    $packages = [
        'web01.example.com' => [
            ['nginx', '1.24.0-2ubuntu7'],
            ['openssl', '3.0.13-0ubuntu3'],
            ['curl', '8.5.0-2ubuntu10'],
            ['bash', '5.2.21-2ubuntu4'],
        ],
        'db01.example.com' => [
            ['mariadb-server', '1:10.11.11-0+deb12u1'],
            ['libssl3', '3.0.17-1~deb12u2'],
        ],
        'app01.example.com' => [
            ['httpd', '2.4.62-1.el9'],
            ['podman', '5:5.4.0-5.el9_6'],
        ],
    ];

    $insert = $pdo->prepare(
        'INSERT INTO patch_allpackages (server_name, package_name, package_version)
         VALUES (:server_name, :package_name, :package_version)'
    );

    foreach ($packages as $serverName => $rows) {
        $pdo->prepare('DELETE FROM patch_allpackages WHERE server_name = :server_name')
            ->execute(['server_name' => $serverName]);
        foreach ($rows as [$name, $version]) {
            $insert->execute([
                'server_name' => $serverName,
                'package_name' => $name,
                'package_version' => $version,
            ]);
        }
    }

    echo "  patch_allpackages\n";
}

function seedSuppressions(PDO $pdo): void
{
    $table = tableExists($pdo, 'suppressed') ? 'suppressed' : 'supressed';
    $rows = [
        ['mock-kernel-meta', '0'],
        ['mock-docs', 'legacy01.example.com'],
    ];

    $insert = $pdo->prepare("INSERT IGNORE INTO {$table} (package_name, server_name) VALUES (:package_name, :server_name)");
    foreach ($rows as [$package, $server]) {
        $insert->execute(['package_name' => $package, 'server_name' => $server]);
    }

    echo "  suppressions\n";
}

function seedContainers(PDO $pdo, array $serverIds): void
{
    if (!tableExists($pdo, 'containers')) {
        return;
    }

    $containerSets = [
        'web01.example.com' => [
            ['docker', 'web01-nginx', 'web01-nginx', 'nginx:1.25-alpine', 'running', [
                ['CVE-2024-7347', 'MEDIUM', 'nginx', '1.25.5', '1.25.5-r1', 'NGINX buffer issue'],
                ['CVE-2025-0001', 'HIGH', 'libcrypto3', '3.1.4-r5', '3.1.8-r0', 'OpenSSL mock advisory'],
            ]],
            ['docker', 'web01-api', 'web01-api', 'node:20-bookworm', 'running', [
                ['CVE-2024-22020', 'HIGH', 'nodejs', '20.11.0', '20.18.1', 'Node.js mock CVE'],
            ]],
        ],
        'pve01.example.com' => [
            ['lxc', '100', 'monitoring', 'debian:12', 'running', [
                ['CVE-2024-37371', 'CRITICAL', 'krb5', '1.20.1-2', '1.20.1-6+deb12u2', 'Kerberos mock CVE'],
            ]],
            ['lxc', '101', 'backup', 'debian:12', 'running', [
                ['CVE-2023-45853', 'LOW', 'zlib', '1:1.2.13.dfsg', '1:1.2.13.dfsg-1', 'zlib mock CVE'],
            ]],
        ],
        'app01.example.com' => [
            ['podman', 'app-worker', 'app-worker', 'registry.local/worker:latest', 'running', [
                ['CVE-2024-10963', 'HIGH', 'python3', '3.9.18', '3.9.21', 'Python mock CVE'],
            ]],
        ],
    ];

    $insertContainer = $pdo->prepare(
        'INSERT INTO containers (server_id, runtime, runtime_id, name, image_ref, status, last_scanned_at)
         VALUES (:server_id, :runtime, :runtime_id, :name, :image_ref, :status, NOW())'
    );
    $insertVuln = $pdo->prepare(
        'INSERT INTO container_vulnerabilities
         (container_id, cve_id, severity, package_name, installed_version, fixed_version, title, first_seen, last_seen)
         VALUES (:container_id, :cve_id, :severity, :package_name, :installed_version, :fixed_version, :title, NOW(), NOW())'
    );

    $pdo->exec('DELETE FROM container_vulnerabilities');
    $pdo->exec('DELETE FROM containers');

    foreach ($containerSets as $serverName => $containers) {
        $serverId = $serverIds[$serverName] ?? null;
        if ($serverId === null) {
            continue;
        }

        foreach ($containers as [$runtime, $runtimeId, $name, $image, $status, $vulns]) {
            $insertContainer->execute([
                'server_id' => $serverId,
                'runtime' => $runtime,
                'runtime_id' => $runtimeId,
                'name' => $name,
                'image_ref' => $image,
                'status' => $status,
            ]);
            $containerId = (int) $pdo->lastInsertId();

            foreach ($vulns as [$cve, $severity, $package, $installed, $fixed, $title]) {
                $insertVuln->execute([
                    'container_id' => $containerId,
                    'cve_id' => $cve,
                    'severity' => $severity,
                    'package_name' => $package,
                    'installed_version' => $installed,
                    'fixed_version' => $fixed,
                    'title' => $title,
                ]);
            }
        }
    }

    echo "  containers + vulnerabilities\n";
}

function seedAlerts(PDO $pdo): void
{
    if (!tableExists($pdo, 'webhook_endpoints')) {
        return;
    }

    $pdo->prepare(
        'INSERT IGNORE INTO webhook_endpoints (id, name, url, secret, enabled, slack_format)
         VALUES (1, :name, :url, :secret, 1, 1)'
    )->execute([
        'name' => 'Mock Slack Alerts',
        'url' => 'https://hooks.example.com/services/mock/slack',
        'secret' => 'mock-webhook-secret',
    ]);

    if (tableExists($pdo, 'alert_events')) {
        $pdo->exec('DELETE FROM alert_events');
        $insert = $pdo->prepare(
            'INSERT INTO alert_events (channel, reference_id, status, summary, sent_at)
             VALUES (:channel, :reference_id, :status, :summary, NOW())'
        );
        $insert->execute([
            'channel' => 'email',
            'reference_id' => null,
            'status' => 'sent',
            'summary' => '2 new HIGH container vulnerabilities on web01.example.com',
        ]);
        $insert->execute([
            'channel' => 'webhook',
            'reference_id' => 1,
            'status' => 'sent',
            'summary' => '1 new CRITICAL container vulnerability on pve01.example.com',
        ]);
    }

    echo "  webhooks + alert events\n";
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SHOW TABLES LIKE :table');
    $stmt->execute(['table' => $table]);
    return (bool) $stmt->fetch();
}
