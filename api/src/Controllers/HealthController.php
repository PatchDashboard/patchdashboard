<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class HealthController
{
    public function health(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'version' => '2.0.0-dev',
            'service' => 'patchdashboard-api',
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
