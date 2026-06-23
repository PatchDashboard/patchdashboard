<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class MfaService
{
    private const ISSUER = 'PatchDashboard';
    private const PERIOD = 30;
    private const DIGITS = 6;

    public function __construct(
        private \PDO $pdo,
        private string $encryptionKey,
    ) {
    }

    public function isEnabled(int $userId): bool
    {
        $row = $this->loadMfaRow($userId);
        return $row !== null && (bool) $row['totp_enabled'];
    }

    /**
     * @return array{secret: string, otpauth_uri: string}
     */
    public function beginSetup(int $userId, string $accountName): array
    {
        if ($this->isEnabled($userId)) {
            throw new \RuntimeException('MFA is already enabled');
        }

        $secret = $this->generateSecret();
        $this->pdo->prepare(
            'UPDATE users SET totp_secret = :secret, totp_enabled = 0, totp_confirmed_at = NULL WHERE id = :id'
        )->execute([
            'secret' => $this->encryptSecret($secret),
            'id' => $userId,
        ]);

        return [
            'secret' => $secret,
            'otpauth_uri' => $this->buildOtpAuthUri($accountName, $secret),
        ];
    }

    public function confirmSetup(int $userId, string $code): void
    {
        $row = $this->loadMfaRow($userId);
        if ($row === null || empty($row['totp_secret'])) {
            throw new \RuntimeException('MFA setup has not been started');
        }

        if ((bool) $row['totp_enabled']) {
            throw new \RuntimeException('MFA is already enabled');
        }

        $secret = $this->decryptSecret((string) $row['totp_secret']);
        if (!$this->verifyCode($secret, $code)) {
            throw new \RuntimeException('Invalid authentication code');
        }

        $this->pdo->prepare(
            'UPDATE users SET totp_enabled = 1, totp_confirmed_at = NOW() WHERE id = :id'
        )->execute(['id' => $userId]);
    }

    public function verifyLoginCode(int $userId, string $code): bool
    {
        $row = $this->loadMfaRow($userId);
        if ($row === null || !(bool) $row['totp_enabled'] || empty($row['totp_secret'])) {
            return false;
        }

        $secret = $this->decryptSecret((string) $row['totp_secret']);
        return $this->verifyCode($secret, $code);
    }

    public function disable(int $userId): void
    {
        $this->pdo->prepare(
            'UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_confirmed_at = NULL WHERE id = :id'
        )->execute(['id' => $userId]);
    }

    private function loadMfaRow(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT totp_secret, totp_enabled FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function buildOtpAuthUri(string $accountName, string $secret): string
    {
        $label = rawurlencode(self::ISSUER . ':' . $accountName);
        $issuer = rawurlencode(self::ISSUER);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $label,
            $secret,
            $issuer,
            self::DIGITS,
            self::PERIOD,
        );
    }

    private function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    private function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $normalized = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $normalized)) {
            return false;
        }

        $key = $this->base32Decode($secret);
        $timeSlice = (int) floor(time() / self::PERIOD);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->calculateTotp($key, $timeSlice + $offset), $normalized)) {
                return true;
            }
        }

        return false;
    }

    private function calculateTotp(string $key, int $counter): string
    {
        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        ) % (10 ** self::DIGITS);

        return str_pad((string) $value, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private function encryptSecret(string $plain): string
    {
        $key = hash('sha256', $this->encryptionKey, true);
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher === false) {
            throw new \RuntimeException('Unable to encrypt MFA secret');
        }

        return base64_encode($iv . $cipher);
    }

    private function decryptSecret(string $stored): string
    {
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) < 17) {
            throw new \RuntimeException('Invalid MFA secret payload');
        }

        $iv = substr($raw, 0, 16);
        $cipher = substr($raw, 16);
        $key = hash('sha256', $this->encryptionKey, true);
        $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($plain === false || $plain === '') {
            throw new \RuntimeException('Unable to decrypt MFA secret');
        }

        return $plain;
    }

    private function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $encoded .= $alphabet[bindec($chunk)];
        }

        return $encoded;
    }

    private function base32Decode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper(preg_replace('/[^A-Z2-7]/', '', $data) ?? '');
        $binary = '';

        foreach (str_split($data) as $char) {
            $index = strpos($alphabet, $char);
            if ($index === false) {
                continue;
            }
            $binary .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                break;
            }
            $decoded .= chr(bindec($chunk));
        }

        return $decoded;
    }
}
