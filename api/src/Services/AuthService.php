<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class AuthService
{
    private const MFA_TOKEN_TTL = 300;

    public function __construct(
        private \PDO $pdo,
        private MfaService $mfaService,
        private string $jwtSecret,
        private int $jwtTtl,
        private string $legacySalt,
    ) {
    }

    public function login(string $username, string $password): ?array
    {
        $user = $this->findAuthUser($username);
        if ($user === null) {
            return null;
        }

        if (!$this->verifyPassword($password, $user)) {
            return null;
        }

        if ($this->needsRehash($user['password'])) {
            $this->rehashPassword((int) $user['id'], $password);
        }

        if ($this->mfaService->isEnabled((int) $user['id'])) {
            return [
                'mfa_required' => true,
                'mfa_token' => $this->issueMfaToken($user),
            ];
        }

        return $this->issueSession($user);
    }

    public function verifyMfaLogin(string $mfaToken, string $code): ?array
    {
        $userId = $this->parseMfaToken($mfaToken);
        if ($userId === null) {
            return null;
        }

        if (!$this->mfaService->verifyLoginCode($userId, $code)) {
            return null;
        }

        $user = $this->findUserById($userId);
        if ($user === null || !(bool) $user['active']) {
            return null;
        }

        return $this->issueSession($user);
    }

    public function verifyUserPassword(int $userId, string $password): bool
    {
        $stmt = $this->pdo->prepare('SELECT password, active FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();

        if (!$user || !(bool) $user['active']) {
            return false;
        }

        return $this->verifyPassword($password, $user);
    }

    public function getUserById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, email, admin, display_name, active, receive_alerts, last_seen, totp_enabled
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        return $user ? $this->publicUser($user) : null;
    }

    public function updateProfile(int $userId, array $data): ?array
    {
        $fields = [];
        $params = ['id' => $userId];

        foreach (['email', 'display_name', 'receive_alerts'] as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[$field] = $field === 'receive_alerts'
                    ? ((bool) $data[$field] ? 1 : 0)
                    : $data[$field];
            }
        }

        if (!empty($data['password'])) {
            $fields[] = 'password = :password';
            $params['password'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }

        if ($fields === []) {
            return $this->getUserById($userId);
        }

        $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $this->pdo->prepare($sql)->execute($params);
        return $this->getUserById($userId);
    }

    /**
     * @param array<string, mixed> $user
     * @return array{token: string, user: array<string, mixed>}
     */
    private function issueSession(array $user): array
    {
        $this->pdo->prepare('UPDATE users SET last_seen = NOW() WHERE id = :id')
            ->execute(['id' => $user['id']]);

        $payload = [
            'sub' => (int) $user['id'],
            'user_id' => $user['user_id'],
            'display_name' => $user['display_name'],
            'admin' => (bool) $user['admin'],
            'iat' => time(),
            'exp' => time() + $this->jwtTtl,
        ];

        return [
            'token' => JWT::encode($payload, $this->jwtSecret, 'HS256'),
            'user' => $this->publicUser($user),
        ];
    }

    /**
     * @param array<string, mixed> $user
     */
    private function issueMfaToken(array $user): string
    {
        return JWT::encode([
            'sub' => (int) $user['id'],
            'purpose' => 'mfa_pending',
            'iat' => time(),
            'exp' => time() + self::MFA_TOKEN_TTL,
        ], $this->jwtSecret, 'HS256');
    }

    private function parseMfaToken(string $token): ?int
    {
        try {
            $payload = (array) JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
        } catch (\Throwable) {
            return null;
        }

        if (($payload['purpose'] ?? '') !== 'mfa_pending') {
            return null;
        }

        $userId = (int) ($payload['sub'] ?? 0);
        return $userId > 0 ? $userId : null;
    }

    private function findAuthUser(string $username): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $username]);
        $user = $stmt->fetch();

        if (!$user || !(bool) $user['active']) {
            return null;
        }

        return $user;
    }

    private function findUserById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'user_id' => $user['user_id'],
            'display_name' => $user['display_name'],
            'email' => $user['email'],
            'admin' => (bool) $user['admin'],
            'active' => (bool) ($user['active'] ?? 1),
            'receive_alerts' => (bool) ($user['receive_alerts'] ?? 0),
            'last_seen' => $user['last_seen'] ?? null,
            'mfa_enabled' => (bool) ($user['totp_enabled'] ?? 0),
        ];
    }

    private function verifyPassword(string $password, array $user): bool
    {
        if (str_starts_with($user['password'], '$2y$') || str_starts_with($user['password'], '$2a$')) {
            return password_verify($password, $user['password']);
        }

        $legacy = hash('sha256', $password . $this->legacySalt);
        return hash_equals($user['password'], $legacy);
    }

    private function needsRehash(string $hash): bool
    {
        return !str_starts_with($hash, '$2y$') && !str_starts_with($hash, '$2a$');
    }

    private function rehashPassword(int $userId, string $password): void
    {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->pdo->prepare('UPDATE users SET password = :password WHERE id = :id')
            ->execute(['password' => $hash, 'id' => $userId]);
    }
}
