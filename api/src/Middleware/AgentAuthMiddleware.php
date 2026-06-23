<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Middleware;

use PatchDashboard\Api\Services\ServerService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class AgentAuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $clientKey = $request->getHeaderLine('X-CLIENT-KEY');
        if ($clientKey === '') {
            return $this->forbidden('Missing X-CLIENT-KEY header');
        }

        $pdo = \PatchDashboard\Api\Config\Database::connect();
        $serverService = new ServerService($pdo);

        if ($serverService->isClientKeyRevoked($clientKey)) {
            $request = $request->withAttribute('agent', [
                'client_key' => $clientKey,
                'registered' => false,
                'revoked' => true,
            ]);
            return $handler->handle($request);
        }

        $server = $serverService->findByClientKey($clientKey);

        if ($server === null) {
            $request = $request->withAttribute('agent', [
                'client_key' => $clientKey,
                'registered' => false,
            ]);
            return $handler->handle($request);
        }

        $request = $request->withAttribute('agent', [
            'client_key' => $clientKey,
            'registered' => true,
            'server' => $server,
            'trusted' => (int) $server['trusted'] === 1,
        ]);

        return $handler->handle($request);
    }

    private function forbidden(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(403);
    }
}
