<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Config;

use Predis\Client;

final class RedisFactory
{
    private static ?Client $client = null;

    public static function client(): Client
    {
        if (self::$client === null) {
            $url = (string) ($_ENV['REDIS_URL'] ?? '');
            if ($url === '') {
                $host = (string) ($_ENV['REDIS_HOST'] ?? '127.0.0.1');
                $port = (int) ($_ENV['REDIS_PORT'] ?? 6379);
                $url = "redis://{$host}:{$port}";
            }

            self::$client = new Client($url);
        }

        return self::$client;
    }
}
