<?php

declare(strict_types=1);

namespace PatchDashboard\Api;

use DI\ContainerBuilder;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PatchDashboard\Api\Config\Database;
use PatchDashboard\Api\Services\AlertService;
use PatchDashboard\Api\Services\AuthService;
use PatchDashboard\Api\Services\ContainerScanService;
use PatchDashboard\Api\Services\DistroService;
use PatchDashboard\Api\Services\MfaService;
use PatchDashboard\Api\Services\PatchService;
use PatchDashboard\Api\Services\ServerService;
use PatchDashboard\Api\Services\SuppressionService;
use PatchDashboard\Api\Services\UserService;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class AppFactory
{
    public static function createContainer(): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        $builder->addDefinitions([
            LoggerInterface::class => function (): LoggerInterface {
                $logger = new Logger('patchdashboard');
                $logger->pushHandler(new StreamHandler('php://stderr', Logger::DEBUG));
                return $logger;
            },
            \PDO::class => fn (): \PDO => Database::connect(),
            MfaService::class => fn (ContainerInterface $c): MfaService => new MfaService(
                $c->get(\PDO::class),
                $_ENV['MFA_ENCRYPTION_KEY'] ?? $_ENV['JWT_SECRET'] ?? 'insecure-dev-secret',
            ),
            AuthService::class => fn (ContainerInterface $c) => new AuthService(
                $c->get(\PDO::class),
                $c->get(MfaService::class),
                $_ENV['JWT_SECRET'] ?? 'insecure-dev-secret',
                (int) ($_ENV['JWT_TTL'] ?? 86400),
                $_ENV['PW_SALT'] ?? ''
            ),
        ]);

        return $builder->build();
    }
}
