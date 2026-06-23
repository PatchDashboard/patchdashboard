<?php

declare(strict_types=1);

use PatchDashboard\Api\AppFactory;
use Slim\Factory\AppFactory as SlimAppFactory;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
if (file_exists(dirname(__DIR__) . '/.env')) {
    $dotenv->load();
}

SlimAppFactory::setContainer(AppFactory::createContainer());
$app = SlimAppFactory::create();

(require __DIR__ . '/../config/routes.php')($app);
(require __DIR__ . '/../config/middleware.php')($app);

$app->run();
