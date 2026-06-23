<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

final class ConsoleSessionService
{
    private const TTL_SECONDS = 300;

    public function __construct(private ConsoleSessionStore $store)
    {
    }

    public function createForServer(array $server, int $userId): array
    {
        $payload = [
            'server_id' => (int) $server['id'],
            'user_id' => $userId,
            'server_alias' => (string) ($server['server_alias'] ?? ''),
            'host' => (string) ($server['server_ip'] ?? ''),
            'ssh_user' => (string) ($server['ssh_user'] ?? 'root'),
            'created_at' => gmdate('c'),
        ];

        $token = $this->store->create($payload, self::TTL_SECONDS);
        $wsPublic = rtrim((string) ($_ENV['CONSOLE_WS_PUBLIC_URL'] ?? 'ws://localhost:8090'), '/');

        return [
            'token' => $token,
            'ws_url' => $wsPublic,
            'expires_in' => self::TTL_SECONDS,
            'server' => [
                'id' => $payload['server_id'],
                'server_alias' => $payload['server_alias'],
                'server_ip' => $payload['host'],
                'ssh_user' => $payload['ssh_user'],
            ],
            'dev_stub' => filter_var($_ENV['CONSOLE_DEV_STUB'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function verifyToken(string $token, bool $consume = false): ?array
    {
        return $consume ? $this->store->consume($token) : $this->store->get($token);
    }
}
