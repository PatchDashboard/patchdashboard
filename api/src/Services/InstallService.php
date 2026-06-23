<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

use PatchDashboard\Api\Config\Database;

final class InstallService
{
    private string $storageDir;
    private string $rootDir;

    public function __construct()
    {
        $apiDir = dirname(__DIR__, 2);
        $this->storageDir = $apiDir . '/storage';
        $this->rootDir = dirname($apiDir);
    }

    public function status(): array
    {
        if ($this->isInstalled()) {
            return [
                'installed' => true,
                'webSetupPending' => false,
                'requiresToken' => false,
            ];
        }

        $pending = $this->isWebSetupPending();

        return [
            'installed' => false,
            'webSetupPending' => $pending,
            'requiresToken' => $pending,
        ];
    }

    public function isInstalled(): bool
    {
        if (is_file($this->storageDir . '/install.lock')) {
            return true;
        }

        return $this->hasBootstrapData();
    }

    public function isWebSetupPending(): bool
    {
        return is_file($this->storageDir . '/setup.token') && !$this->isInstalled();
    }

    public function validateToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $path = $this->storageDir . '/setup.token';
        if (!is_file($path)) {
            return false;
        }

        $expected = trim((string) file_get_contents($path));

        return $expected !== '' && hash_equals($expected, $token);
    }

    /**
     * @return array{installKey: string}
     */
    public function completeSetup(
        string $token,
        string $adminUser,
        string $adminEmail,
        string $adminPass,
        string $company
    ): array {
        if ($this->isInstalled()) {
            throw new \RuntimeException('PatchDashboard is already installed.');
        }

        if (!$this->validateToken($token)) {
            throw new \RuntimeException('Invalid or expired setup token.');
        }

        $adminUser = trim($adminUser);
        $adminEmail = trim($adminEmail);
        $company = trim($company);

        if ($adminUser === '' || $adminEmail === '' || $adminPass === '' || $company === '') {
            throw new \InvalidArgumentException('All setup fields are required.');
        }

        if (strlen($adminPass) < 8) {
            throw new \InvalidArgumentException('Admin password must be at least 8 characters.');
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Admin email is invalid.');
        }

        $this->updateEnvValue('YOUR_COMPANY', $company);
        $this->runBootstrap($adminUser, $adminEmail, $adminPass, $company);
        $installKey = $this->readInstallKey();
        $this->finalizeInstall();

        return ['installKey' => $installKey];
    }

    private function hasBootstrapData(): bool
    {
        try {
            $pdo = Database::connect();
            $users = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $company = (int) $pdo->query('SELECT COUNT(*) FROM company')->fetchColumn();

            return $users > 0 && $company > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function updateEnvValue(string $key, string $value): void
    {
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (!is_file($envPath)) {
            throw new \RuntimeException('api/.env not found. Run the VM installer first.');
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException('Unable to read api/.env');
        }

        $prefix = $key . '=';
        $updated = false;
        foreach ($lines as $index => $line) {
            if (str_starts_with($line, $prefix)) {
                $lines[$index] = $prefix . $this->escapeEnvValue($value);
                $updated = true;
                break;
            }
        }

        if (!$updated) {
            $lines[] = $prefix . $this->escapeEnvValue($value);
        }

        file_put_contents($envPath, implode("\n", $lines) . "\n");
    }

    private function escapeEnvValue(string $value): string
    {
        if ($value === '' || preg_match('/[\s#="\']/', $value)) {
            return '"' . addcslashes($value, "\"\\") . '"';
        }

        return $value;
    }

    private function runBootstrap(
        string $adminUser,
        string $adminEmail,
        string $adminPass,
        string $company
    ): void {
        $script = $this->rootDir . '/database/bootstrap.php';
        if (!is_file($script)) {
            throw new \RuntimeException('database/bootstrap.php not found.');
        }

        $command = sprintf(
            'DB_HOST=%s DB_PORT=%s DB_NAME=%s DB_USER=%s DB_PASS=%s php %s --admin-user=%s --admin-email=%s --admin-pass=%s --company=%s 2>&1',
            escapeshellarg((string) ($_ENV['DB_HOST'] ?? '127.0.0.1')),
            escapeshellarg((string) ($_ENV['DB_PORT'] ?? '3306')),
            escapeshellarg((string) ($_ENV['DB_NAME'] ?? 'patchdashboard')),
            escapeshellarg((string) ($_ENV['DB_USER'] ?? 'patchdashboard')),
            escapeshellarg((string) ($_ENV['DB_PASS'] ?? '')),
            escapeshellarg($script),
            escapeshellarg($adminUser),
            escapeshellarg($adminEmail),
            escapeshellarg($adminPass),
            escapeshellarg($company)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException(trim(implode("\n", $output)) ?: 'Bootstrap failed.');
        }
    }

    private function readInstallKey(): string
    {
        $pdo = Database::connect();
        $key = $pdo->query('SELECT install_key FROM company LIMIT 1')->fetchColumn();

        if (!is_string($key) || $key === '') {
            throw new \RuntimeException('Company install key was not created.');
        }

        return $key;
    }

    private function finalizeInstall(): void
    {
        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0770, true) && !is_dir($this->storageDir)) {
            throw new \RuntimeException('Unable to create api/storage directory.');
        }

        $lockPath = $this->storageDir . '/install.lock';
        $payload = json_encode(['installed_at' => gmdate('c')], JSON_THROW_ON_ERROR);
        if (file_put_contents($lockPath, $payload . "\n") === false) {
            throw new \RuntimeException('Unable to write install.lock.');
        }

        $tokenPath = $this->storageDir . '/setup.token';
        if (is_file($tokenPath) && !unlink($tokenPath)) {
            throw new \RuntimeException('Unable to remove setup token.');
        }
    }
}
