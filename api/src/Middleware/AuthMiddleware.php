<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PatchDashboard\Api\Services\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/Bearer\s+(.+)/i', $header, $matches)) {
            return $this->unauthorized('Missing bearer token');
        }

        try {
            $secret = $_ENV['JWT_SECRET'] ?? 'insecure-dev-secret';
            $payload = JWT::decode($matches[1], new Key($secret, 'HS256'));
            $request = $request->withAttribute('user', (array) $payload);
        } catch (\Throwable) {
            return $this->unauthorized('Invalid or expired token');
        }

        return $handler->handle($request);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $response = new Response();
        $response->getBody()->write(json_encode(['error' => $message]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(401);
    }
}
