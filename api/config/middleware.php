<?php

declare(strict_types=1);

use PatchDashboard\Api\Middleware\CorsMiddleware;
use PatchDashboard\Api\Middleware\JsonBodyParserMiddleware;
use Slim\App;

return function (App $app): void {
    $app->addBodyParsingMiddleware();
    $app->add(new JsonBodyParserMiddleware());
    $app->add(new CorsMiddleware());
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware(
        filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
        true,
        true
    );
};
