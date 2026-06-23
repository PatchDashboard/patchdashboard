<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Services;

use PatchDashboard\Api\Config\RedisFactory;

final class ConsoleSessionStore
{
    private const KEY_PREFIX = 'pd:console:session:';

    /**
     * @param array<string, mixed> $session
     */
    public function create(array $session, int $ttlSeconds): string
    {
        $token = bin2hex(random_bytes(32));
        $key = self::KEY_PREFIX . $token;

        RedisFactory::client()->setex($key, $ttlSeconds, json_encode($session, JSON_THROW_ON_ERROR));

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $token): ?array
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $json = RedisFactory::client()->get(self::KEY_PREFIX . $token);
        if (!is_string($json) || $json === '') {
            return null;
        }

        /** @var array<string, mixed>|null $session */
        $session = json_decode($json, true);

        return is_array($session) ? $session : null;
    }

    /**
     * Fetch session data and delete the token (one WebSocket connection per token).
     *
     * @return array<string, mixed>|null
     */
    public function consume(string $token): ?array
    {
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $key = self::KEY_PREFIX . $token;
        $redis = RedisFactory::client();

        $json = $redis->get($key);
        if (!is_string($json) || $json === '') {
            return null;
        }

        $redis->del([$key]);

        /** @var array<string, mixed>|null $session */
        $session = json_decode($json, true);

        return is_array($session) ? $session : null;
    }
}
