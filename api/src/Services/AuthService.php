<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

use Firebase\JWT\JWT;

final class AuthService
{
    public function __construct(
        private \PDO $pdo,
        private string $jwtSecret,
        private int $jwtTtl,
        private string $legacySalt
    ) {
    }

    public function login(string $username, string $password): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE user_id = :user_id LIMIT 1');
        $stmt->execute(['user_id' => $username]);
        $user = $stmt->fetch();

        if (!$user || !(bool) $user['active']) {
            return null;
        }

        if (!$this->verifyPassword($password, $user)) {
            return null;
        }

        if ($this->needsRehash($user['password'])) {
            $this->rehashPassword((int) $user['id'], $password);
        }

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
            'user' => [
                'id' => (int) $user['id'],
                'user_id' => $user['user_id'],
                'display_name' => $user['display_name'],
                'email' => $user['email'],
                'admin' => (bool) $user['admin'],
            ],
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
