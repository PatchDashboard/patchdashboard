<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class SshKeyService
{
    private const KEY_COMMENT = 'patchdashboard-managed';
    private const KEY_BASENAME = 'patchdashboard_ed25519';
    private const BOOTSTRAP_BASENAME = 'enrollment_bootstrap';

    private string $storageDir;

    public function __construct()
    {
        $this->storageDir = dirname(__DIR__, 2) . '/storage/ssh';
    }

    /**
     * @return array{private_key_path: string, public_key_path: string, public_key: string, fingerprint: string}
     */
    public function ensureKeyPair(): array
    {
        $this->ensureStorageDir();

        $privatePath = $this->storageDir . '/' . self::KEY_BASENAME;
        $publicPath = $privatePath . '.pub';

        if (!is_file($privatePath)) {
            $command = sprintf(
                'ssh-keygen -t ed25519 -f %s -N %s -C %s 2>&1',
                escapeshellarg($privatePath),
                escapeshellarg(''),
                escapeshellarg(self::KEY_COMMENT)
            );
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            if ($exitCode !== 0 || !is_file($privatePath)) {
                throw new \RuntimeException(trim(implode("\n", $output)) ?: 'ssh-keygen failed.');
            }
            chmod($privatePath, 0600);
            if (is_file($publicPath)) {
                chmod($publicPath, 0644);
            }
        }

        return $this->keyPairFromPaths($privatePath, $publicPath);
    }

    /**
     * Optional key PatchDashboard uses to reach servers during enrollment.
     *
     * @return array{private_key_path: string, public_key_path: string, public_key: string, fingerprint: string}|null
     */
    public function getBootstrapKeyPair(): ?array
    {
        $privatePath = $this->storageDir . '/' . self::BOOTSTRAP_BASENAME;
        $publicPath = $privatePath . '.pub';
        if (!is_file($privatePath) || !is_file($publicPath)) {
            return null;
        }

        return $this->keyPairFromPaths($privatePath, $publicPath);
    }

    /**
     * @return array{private_key_path: string, public_key_path: string, public_key: string, fingerprint: string}
     */
    public function setBootstrapKeyPair(string $privateKey, ?string $publicKey = null): array
    {
        $this->ensureStorageDir();
        $privatePath = $this->storageDir . '/' . self::BOOTSTRAP_BASENAME;
        $publicPath = $privatePath . '.pub';

        return $this->storeKeyPair($privatePath, $publicPath, $privateKey, $publicKey);
    }

    public function clearBootstrapKey(): void
    {
        foreach ([self::BOOTSTRAP_BASENAME, self::BOOTSTRAP_BASENAME . '.pub'] as $name) {
            $path = $this->storageDir . '/' . $name;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Key used for outbound SSH during enrollment (bootstrap if configured, else dashboard key).
     *
     * @return array{private_key_path: string, public_key_path: string, public_key: string, fingerprint: string}
     */
    public function getConnectionKeyPair(): array
    {
        return $this->getBootstrapKeyPair() ?? $this->ensureKeyPair();
    }

    public function getPublicKey(): ?string
    {
        $publicPath = $this->storageDir . '/' . self::KEY_BASENAME . '.pub';
        if (!is_file($publicPath)) {
            return null;
        }

        return trim((string) file_get_contents($publicPath));
    }

    public function fingerprint(string $publicKey): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pd-ssh-fp-');
        if ($tmp === false) {
            return hash('sha256', $publicKey);
        }

        file_put_contents($tmp, $publicKey . "\n");
        $command = sprintf('ssh-keygen -lf %s 2>/dev/null', escapeshellarg($tmp));
        @unlink($tmp);

        $output = [];
        exec($command, $output);
        if ($output === []) {
            return hash('sha256', $publicKey);
        }

        $parts = preg_split('/\s+/', trim($output[0])) ?: [];
        return $parts[1] ?? hash('sha256', $publicKey);
    }

    public function deployAuthorizedKey(string $host, string $sshUser, ?string $password = null): bool
    {
        $keys = $this->ensureKeyPair();
        $marker = self::KEY_COMMENT;
        $publicKey = $keys['public_key'];
        $remoteCmd = sprintf(
            'mkdir -p ~/.ssh && chmod 700 ~/.ssh && touch ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys && grep -qF %s ~/.ssh/authorized_keys 2>/dev/null || echo %s >> ~/.ssh/authorized_keys',
            escapeshellarg($marker),
            escapeshellarg($publicKey . ' ' . $marker)
        );

        $privateKeyPath = ($password === null || $password === '')
            ? $this->getConnectionKeyPair()['private_key_path']
            : null;

        return $this->runSsh($host, $sshUser, $remoteCmd, 30, $privateKeyPath, $password);
    }

    public function removeAuthorizedKey(string $host, string $sshUser): bool
    {
        $connection = $this->getConnectionKeyPair();
        $marker = self::KEY_COMMENT;
        $remoteCmd = sprintf(
            "test -f ~/.ssh/authorized_keys && sed -i '/%s/d' ~/.ssh/authorized_keys || true",
            addcslashes($marker, '/')
        );

        return $this->runSsh($host, $sshUser, $remoteCmd, 15, $connection['private_key_path']);
    }

    /**
     * @return array{ok: bool, output: string}
     */
    public function runEnrollmentScript(
        string $host,
        string $sshUser,
        string $apiBase,
        string $authKey,
        ?string $serverAlias = null,
        ?string $password = null,
    ): array {
        $rootDir = dirname(__DIR__, 3);
        $script = $rootDir . '/agents/client/enroll-server.sh';
        if (!is_file($script)) {
            throw new \RuntimeException('Enrollment script not found.');
        }

        if ($password !== null && $password !== '' && !$this->hasSshpass()) {
            throw new \RuntimeException('sshpass is required for password-based SSH enrollment');
        }

        $env = [
            'PD_API_BASE=' . escapeshellarg(rtrim($apiBase, '/')),
            'PD_AUTH_KEY=' . escapeshellarg($authKey),
        ];

        if ($password !== null && $password !== '') {
            $passFile = tempnam(sys_get_temp_dir(), 'pd-sshpass-');
            if ($passFile === false) {
                throw new \RuntimeException('Unable to create temp file for SSH password');
            }
            file_put_contents($passFile, $password);
            chmod($passFile, 0600);
            $env[] = 'SSH_PASSWORD_FILE=' . escapeshellarg($passFile);
        } else {
            $connection = $this->getConnectionKeyPair();
            $env[] = 'PDASH_SSH_KEY=' . escapeshellarg($connection['private_key_path']);
        }

        $dashboardKeys = $this->ensureKeyPair();
        $env[] = 'PDASH_DASHBOARD_PUB=' . escapeshellarg($dashboardKeys['public_key_path']);

        $command = implode(' ', $env) . ' ' . escapeshellarg($script)
            . ' --host ' . escapeshellarg($host)
            . ' --user ' . escapeshellarg($sshUser)
            . ($serverAlias !== null && $serverAlias !== ''
                ? ' --alias ' . escapeshellarg($serverAlias)
                : '')
            . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if (isset($passFile)) {
            @unlink($passFile);
        }

        return [
            'ok' => $exitCode === 0,
            'output' => trim(implode("\n", $output)),
        ];
    }

    /**
     * @return array{private_key_path: string, public_key_path: string, public_key: string, fingerprint: string}
     */
    private function storeKeyPair(string $privatePath, string $publicPath, string $privateKey, ?string $publicKey): array
    {
        $normalizedPrivate = $this->normalizePrivateKey($privateKey);
        if ($normalizedPrivate === '') {
            throw new \InvalidArgumentException('Invalid private key format');
        }

        $tmpPrivate = tempnam(sys_get_temp_dir(), 'pd-ssh-priv-');
        if ($tmpPrivate === false) {
            throw new \RuntimeException('Unable to create temp file');
        }

        file_put_contents($tmpPrivate, $normalizedPrivate);
        chmod($tmpPrivate, 0600);

        $derivedPublic = trim((string) shell_exec(
            sprintf('ssh-keygen -y -f %s 2>/dev/null', escapeshellarg($tmpPrivate))
        ));
        if ($derivedPublic === '') {
            @unlink($tmpPrivate);
            throw new \InvalidArgumentException('Unable to read private key (is it passphrase-protected?)');
        }

        if ($publicKey !== null && $publicKey !== '') {
            $normalizedPublic = $this->normalizePublicKey($publicKey);
            if ($normalizedPublic !== $derivedPublic) {
                @unlink($tmpPrivate);
                throw new \InvalidArgumentException('Public key does not match the private key');
            }
        }

        if (!rename($tmpPrivate, $privatePath)) {
            @unlink($tmpPrivate);
            throw new \RuntimeException('Unable to save private key');
        }
        chmod($privatePath, 0600);
        file_put_contents($publicPath, $derivedPublic . ' ' . self::KEY_COMMENT . "\n");
        chmod($publicPath, 0644);

        return $this->keyPairFromPaths($privatePath, $publicPath);
    }

    /**
     * @return array{private_key_path: string, public_key_path: string, public_key: string, fingerprint: string}
     */
    private function keyPairFromPaths(string $privatePath, string $publicPath): array
    {
        $publicKey = trim((string) file_get_contents($publicPath));

        return [
            'private_key_path' => $privatePath,
            'public_key_path' => $publicPath,
            'public_key' => $publicKey,
            'fingerprint' => $this->fingerprint($publicKey),
        ];
    }

    private function ensureStorageDir(): void
    {
        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0770, true) && !is_dir($this->storageDir)) {
            throw new \RuntimeException('Unable to create SSH key storage directory.');
        }
    }

    private function normalizePrivateKey(string $key): string
    {
        $key = str_replace("\r\n", "\n", trim($key));
        if (
            str_contains($key, 'BEGIN OPENSSH PRIVATE KEY')
            || str_contains($key, 'BEGIN RSA PRIVATE KEY')
            || str_contains($key, 'BEGIN EC PRIVATE KEY')
        ) {
            return $key . "\n";
        }

        return '';
    }

    private function normalizePublicKey(string $key): string
    {
        $line = trim(str_replace("\r\n", "\n", $key));
        $parts = preg_split('/\s+/', $line) ?: [];
        if (count($parts) < 2) {
            throw new \InvalidArgumentException('Invalid public key format');
        }

        return $parts[0] . ' ' . $parts[1];
    }

    private function runSsh(
        string $host,
        string $sshUser,
        string $remoteCmd,
        int $timeout = 30,
        ?string $privateKeyPath = null,
        ?string $password = null,
    ): bool {
        if ($password !== null && $password !== '') {
            return $this->runSshWithPassword($host, $sshUser, $remoteCmd, $timeout, $password);
        }

        $privateKeyPath ??= $this->getConnectionKeyPair()['private_key_path'];

        $command = sprintf(
            'ssh -i %s -o BatchMode=yes -o ConnectTimeout=%d -o StrictHostKeyChecking=accept-new %s@%s %s 2>/dev/null',
            escapeshellarg($privateKeyPath),
            $timeout,
            escapeshellarg($sshUser),
            escapeshellarg($host),
            escapeshellarg($remoteCmd)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        return $exitCode === 0;
    }

    private function runSshWithPassword(
        string $host,
        string $sshUser,
        string $remoteCmd,
        int $timeout,
        string $password,
    ): bool {
        if (!$this->hasSshpass()) {
            throw new \RuntimeException('sshpass is required for password-based SSH enrollment');
        }

        $passFile = tempnam(sys_get_temp_dir(), 'pd-sshpass-');
        if ($passFile === false) {
            return false;
        }

        file_put_contents($passFile, $password);
        chmod($passFile, 0600);

        $command = sprintf(
            'sshpass -f %s ssh -o PreferredAuthentications=password -o PubkeyAuthentication=no -o ConnectTimeout=%d -o StrictHostKeyChecking=accept-new %s@%s %s 2>/dev/null',
            escapeshellarg($passFile),
            $timeout,
            escapeshellarg($sshUser),
            escapeshellarg($host),
            escapeshellarg($remoteCmd)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        @unlink($passFile);

        return $exitCode === 0;
    }

    private function hasSshpass(): bool
    {
        $output = [];
        $exitCode = 0;
        exec('command -v sshpass 2>/dev/null', $output, $exitCode);

        return $exitCode === 0;
    }
}
