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

    public function isClientKeyRevoked(string $clientKey): bool
    {
        if (!$this->tableExists('revoked_client_keys')) {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT 1 FROM revoked_client_keys WHERE client_key = :client_key LIMIT 1');
        $stmt->execute(['client_key' => $clientKey]);
        return (bool) $stmt->fetchColumn();
    }

    public function findByServerName(string $serverName): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM servers WHERE server_name = :server_name LIMIT 1');
        $stmt->execute(['server_name' => $serverName]);
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
        $stmt = $this->pdo->prepare(
            'SELECT s.*, d.distro_name, d.icon_path, d.upgrade_command, dv.version_num, dv.codename
             FROM servers s
             LEFT JOIN distro d ON d.id = s.distro_id
             LEFT JOIN distro_version dv ON dv.id = s.distro_version
             WHERE s.id = :id
             LIMIT 1'
        );
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
        if ($this->isClientKeyRevoked($clientKey)) {
            return;
        }

        $existing = $this->findByServerName($clientHost);
        if ($existing !== null) {
            if (!empty($existing['client_key'])) {
                return;
            }

            $this->reassignClientKey(
                (int) $existing['id'],
                $clientKey,
                $clientOs,
                $clientOsVer,
                $serverIp,
                $capabilities
            );
            return;
        }

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

    private function reassignClientKey(
        int $serverId,
        string $clientKey,
        string $clientOs,
        string $clientOsVer,
        string $serverIp,
        ?array $capabilities
    ): void {
        $distroId = $this->resolveDistroId($clientOs);
        $versionId = $this->resolveDistroVersionId($distroId, $clientOsVer);

        $fields = [
            'client_key = :client_key',
            'server_ip = :server_ip',
            'distro_id = :distro_id',
            'distro_version = :distro_version',
            'client_key_revoked_at = NULL',
        ];
        $params = [
            'id' => $serverId,
            'client_key' => $clientKey,
            'server_ip' => $serverIp,
            'distro_id' => $distroId,
            'distro_version' => $versionId,
        ];

        if ($capabilities !== null) {
            $fields[] = 'host_capabilities = :host_capabilities';
            $params['host_capabilities'] = json_encode($capabilities);
        }

        $sql = 'UPDATE servers SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);
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
        $allowed = ['server_alias', 'server_group', 'server_ip', 'distro_id', 'distro_version', 'scan_containers'];
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

    /**
     * Revoke trust: clear agent credentials, pending commands, and dashboard SSH access.
     *
     * @return array{client_key_revoked: bool, ssh_key_removed: bool}
     */
    public function revokeTrust(int $id, ?SshKeyService $sshKeys = null): array
    {
        $server = $this->findById($id);
        if ($server === null) {
            throw new \InvalidArgumentException('Server not found.');
        }

        $clientKeyRevoked = false;
        $sshKeyRemoved = false;
        $clientKey = (string) ($server['client_key'] ?? '');

        if ($clientKey !== '') {
            $this->recordRevokedClientKey($clientKey, $id);
            $clientKeyRevoked = true;
        }

        $this->clearPowerCommand($id);

        $fields = [
            'trusted = 0',
            'client_key = NULL',
            'reboot_cmd_sent = 0',
        ];
        if ($this->columnExists('shutdown_cmd_sent')) {
            $fields[] = 'shutdown_cmd_sent = 0';
        }
        if ($this->columnExists('client_key_revoked_at')) {
            $fields[] = 'client_key_revoked_at = NOW()';
        }
        if ($this->columnExists('power_cmd_action')) {
            $fields[] = 'power_cmd_action = NULL';
            $fields[] = 'power_cmd_mode = NULL';
            $fields[] = 'power_cmd_scheduled_at = NULL';
        }

        $this->pdo->prepare('UPDATE servers SET ' . implode(', ', $fields) . ' WHERE id = :id')
            ->execute(['id' => $id]);

        $host = trim((string) ($server['server_ip'] ?? ''));
        $sshUser = trim((string) ($server['ssh_user'] ?? 'root'));
        if (
            $sshKeys !== null
            && $host !== ''
            && !empty($server['dashboard_ssh_fingerprint'])
        ) {
            $sshKeyRemoved = $sshKeys->removeAuthorizedKey($host, $sshUser);
            if ($this->columnExists('dashboard_ssh_fingerprint')) {
                $this->pdo->prepare('UPDATE servers SET dashboard_ssh_fingerprint = NULL WHERE id = :id')
                    ->execute(['id' => $id]);
            }
        }

        return [
            'client_key_revoked' => $clientKeyRevoked,
            'ssh_key_removed' => $sshKeyRemoved,
        ];
    }

    public function recordRevokedClientKey(string $clientKey, int $serverId): void
    {
        if (!$this->tableExists('revoked_client_keys')) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO revoked_client_keys (client_key, server_id) VALUES (:client_key, :server_id)'
        );
        $stmt->execute(['client_key' => $clientKey, 'server_id' => $serverId]);
    }

    public function markSshEnrolled(int $id, string $fingerprint): void
    {
        if (!$this->columnExists('dashboard_ssh_fingerprint')) {
            return;
        }

        $this->pdo->prepare('UPDATE servers SET dashboard_ssh_fingerprint = :fingerprint WHERE id = :id')
            ->execute(['fingerprint' => $fingerprint, 'id' => $id]);
    }

    public function findByIpOrName(string $serverIp, ?string $serverAlias = null): ?array
    {
        if ($serverAlias !== null && $serverAlias !== '') {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM servers WHERE server_alias = :alias OR server_name = :alias LIMIT 1'
            );
            $stmt->execute(['alias' => $serverAlias]);
            $row = $stmt->fetch();
            if ($row) {
                return $row;
            }
        }

        $stmt = $this->pdo->prepare('SELECT * FROM servers WHERE server_ip = :server_ip LIMIT 1');
        $stmt->execute(['server_ip' => $serverIp]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM servers WHERE id = :id')->execute(['id' => $id]);
    }

    public function scheduleReboot(int $id, string $mode = 'graceful', ?string $scheduledAt = null): bool
    {
        return $this->schedulePowerCommand($id, 'reboot', $mode, $scheduledAt);
    }

    public function scheduleShutdown(int $id, string $mode = 'graceful', ?string $scheduledAt = null): bool
    {
        return $this->schedulePowerCommand($id, 'shutdown', $mode, $scheduledAt);
    }

    public function schedulePowerCommand(int $id, string $action, string $mode, ?string $scheduledAt): bool
    {
        $server = $this->findById($id);
        if ($server === null || !(bool) $server['trusted']) {
            return false;
        }

        if (!in_array($action, ['reboot', 'shutdown'], true)) {
            return false;
        }

        if (!in_array($mode, ['graceful', 'forced', 'scheduled'], true)) {
            return false;
        }

        if ($mode === 'scheduled') {
            if ($scheduledAt === null || trim($scheduledAt) === '') {
                return false;
            }
            $when = strtotime($scheduledAt);
            if ($when === false || $when <= time()) {
                return false;
            }
        } else {
            $scheduledAt = null;
        }

        if (!$this->columnExists('power_cmd_action')) {
            return $this->scheduleLegacyPowerCommand($id, $action, $mode, $scheduledAt);
        }

        $this->pdo->prepare(
            'UPDATE servers SET
                power_cmd_action = :action,
                power_cmd_mode = :mode,
                power_cmd_scheduled_at = :scheduled_at,
                reboot_cmd_sent = 0,
                shutdown_cmd_sent = 0
             WHERE id = :id'
        )->execute([
            'action' => $action,
            'mode' => $mode,
            'scheduled_at' => $scheduledAt,
            'id' => $id,
        ]);

        return true;
    }

    /**
     * @return array{action: string, mode: string}|null
     */
    public function consumePendingPowerCommand(int $id): ?array
    {
        if (!$this->columnExists('power_cmd_action')) {
            $legacy = $this->consumeLegacyPowerCommand($id);
            if ($legacy === null) {
                return null;
            }
            return [
                'action' => $legacy['shutdown'] ? 'shutdown' : 'reboot',
                'mode' => 'graceful',
            ];
        }

        $stmt = $this->pdo->prepare(
            'SELECT power_cmd_action, power_cmd_mode, power_cmd_scheduled_at,
                    reboot_cmd_sent, shutdown_cmd_sent
             FROM servers WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $action = $row['power_cmd_action'];
        $mode = $row['power_cmd_mode'] ?? 'graceful';

        if ($action === null || $action === '') {
            return $this->consumeLegacyPowerCommandAsModern($id, $row);
        }

        if ($mode === 'scheduled') {
            $scheduledAt = $row['power_cmd_scheduled_at'];
            if ($scheduledAt === null || strtotime((string) $scheduledAt) > time()) {
                return null;
            }
            $mode = 'graceful';
        }

        $this->clearPowerCommand($id);

        return [
            'action' => (string) $action,
            'mode' => (string) $mode,
        ];
    }

    private function scheduleLegacyPowerCommand(int $id, string $action, string $mode, ?string $scheduledAt): bool
    {
        if ($mode === 'scheduled') {
            return false;
        }

        if ($action === 'shutdown' && $this->columnExists('shutdown_cmd_sent')) {
            $this->pdo->prepare(
                'UPDATE servers SET shutdown_cmd_sent = 1, reboot_cmd_sent = 0 WHERE id = :id'
            )->execute(['id' => $id]);
            return true;
        }

        if ($action === 'reboot') {
            $sql = 'UPDATE servers SET reboot_cmd_sent = 1';
            if ($this->columnExists('shutdown_cmd_sent')) {
                $sql .= ', shutdown_cmd_sent = 0';
            }
            $sql .= ' WHERE id = :id';
            $this->pdo->prepare($sql)->execute(['id' => $id]);
            return true;
        }

        return false;
    }

    /**
     * @return array{reboot: bool, shutdown: bool}|null
     */
    private function consumeLegacyPowerCommand(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT reboot_cmd_sent' . ($this->columnExists('shutdown_cmd_sent') ? ', shutdown_cmd_sent' : '') .
            ' FROM servers WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $shutdown = $this->columnExists('shutdown_cmd_sent') && (int) ($row['shutdown_cmd_sent'] ?? 0) === 1;
        $reboot = !$shutdown && (int) $row['reboot_cmd_sent'] === 1;

        if (!$shutdown && !$reboot) {
            return null;
        }

        $this->clearPowerCommand($id);
        return ['reboot' => $reboot, 'shutdown' => $shutdown];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{action: string, mode: string}|null
     */
    private function consumeLegacyPowerCommandAsModern(int $id, array $row): ?array
    {
        $shutdown = $this->columnExists('shutdown_cmd_sent') && (int) ($row['shutdown_cmd_sent'] ?? 0) === 1;
        $reboot = !$shutdown && (int) ($row['reboot_cmd_sent'] ?? 0) === 1;
        if (!$shutdown && !$reboot) {
            return null;
        }

        $this->clearPowerCommand($id);
        return [
            'action' => $shutdown ? 'shutdown' : 'reboot',
            'mode' => 'graceful',
        ];
    }

    private function clearPowerCommand(int $id): void
    {
        $sql = 'UPDATE servers SET reboot_cmd_sent = 0';
        if ($this->columnExists('shutdown_cmd_sent')) {
            $sql .= ', shutdown_cmd_sent = 0';
        }
        if ($this->columnExists('power_cmd_action')) {
            $sql .= ', power_cmd_action = NULL, power_cmd_mode = NULL, power_cmd_scheduled_at = NULL';
        }
        $sql .= ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute(['id' => $id]);
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

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
        );
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}
