<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class SshKeyService
{
    private const KEY_COMMENT = 'patchdashboard-managed';
    private const KEY_BASENAME = 'patchdashboard_ed25519';

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
        if (!is_dir($this->storageDir) && !mkdir($this->storageDir, 0770, true) && !is_dir($this->storageDir)) {
            throw new \RuntimeException('Unable to create SSH key storage directory.');
        }

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

        $publicKey = trim((string) file_get_contents($publicPath));

        return [
            'private_key_path' => $privatePath,
            'public_key_path' => $publicPath,
            'public_key' => $publicKey,
            'fingerprint' => $this->fingerprint($publicKey),
        ];
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
        $command = sprintf('ssh-keygen -lf %s 2>/dev/null', escapeshellarg($publicKey));
        $output = [];
        exec($command, $output);
        if ($output === []) {
            return hash('sha256', $publicKey);
        }

        $parts = preg_split('/\s+/', trim($output[0])) ?: [];
        return $parts[1] ?? hash('sha256', $publicKey);
    }

    public function deployAuthorizedKey(string $host, string $sshUser): bool
    {
        $keys = $this->ensureKeyPair();
        $marker = self::KEY_COMMENT;
        $publicKey = $keys['public_key'];
        $remoteCmd = sprintf(
            'mkdir -p ~/.ssh && chmod 700 ~/.ssh && touch ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys && grep -qF %s ~/.ssh/authorized_keys 2>/dev/null || echo %s >> ~/.ssh/authorized_keys',
            escapeshellarg($marker),
            escapeshellarg($publicKey . ' ' . $marker)
        );

        return $this->runSsh($host, $sshUser, $keys['private_key_path'], $remoteCmd);
    }

    public function removeAuthorizedKey(string $host, string $sshUser): bool
    {
        $keys = $this->ensureKeyPair();
        $marker = self::KEY_COMMENT;
        $remoteCmd = sprintf(
            "test -f ~/.ssh/authorized_keys && sed -i '/%s/d' ~/.ssh/authorized_keys || true",
            addcslashes($marker, '/')
        );

        return $this->runSsh($host, $sshUser, $keys['private_key_path'], $remoteCmd, 15);
    }

    /**
     * @return array{ok: bool, output: string}
     */
    public function runEnrollmentScript(
        string $host,
        string $sshUser,
        string $apiBase,
        string $authKey,
        ?string $serverAlias = null
    ): array {
        $rootDir = dirname(__DIR__, 3);
        $script = $rootDir . '/agents/client/enroll-server.sh';
        if (!is_file($script)) {
            throw new \RuntimeException('Enrollment script not found.');
        }

        $keys = $this->ensureKeyPair();
        $command = sprintf(
            'PDASH_SSH_KEY=%s PD_API_BASE=%s PD_AUTH_KEY=%s %s --host %s --user %s%s 2>&1',
            escapeshellarg($keys['private_key_path']),
            escapeshellarg(rtrim($apiBase, '/')),
            escapeshellarg($authKey),
            escapeshellarg($script),
            escapeshellarg($host),
            escapeshellarg($sshUser),
            $serverAlias !== null && $serverAlias !== ''
                ? ' --alias ' . escapeshellarg($serverAlias)
                : ''
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        return [
            'ok' => $exitCode === 0,
            'output' => trim(implode("\n", $output)),
        ];
    }

    private function runSsh(string $host, string $sshUser, string $privateKeyPath, string $remoteCmd, int $timeout = 30): bool
    {
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
}
