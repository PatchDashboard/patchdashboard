<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\AuthService;
use PatchDashboard\Api\Services\ConsoleSessionService;
use PatchDashboard\Api\Services\ServerService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ConsoleController
{
    public function __construct(
        private ServerService $serverService,
        private ConsoleSessionService $consoleSessionService,
        private AuthService $authService,
    ) {
    }

    public function createSession(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }

        $server = $this->serverService->findById((int) $args['id']);
        if ($server === null) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }

        if (!(bool) $server['trusted']) {
            return $this->json($response, ['error' => 'Server must be trusted before opening a console'], 422);
        }

        $host = trim((string) ($server['server_ip'] ?? ''));
        if ($host === '') {
            return $this->json($response, ['error' => 'Server has no IP address configured'], 422);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $password = (string) ($body['password'] ?? '');
        if ($password === '') {
            return $this->json($response, ['error' => 'Password confirmation is required'], 422);
        }

        $user = (array) $request->getAttribute('user');
        $userId = (int) ($user['sub'] ?? $user['id'] ?? 0);
        if ($userId <= 0 || !$this->authService->verifyUserPassword($userId, $password)) {
            return $this->json($response, ['error' => 'Invalid password'], 401);
        }

        $session = $this->consoleSessionService->createForServer($server, $userId);

        return $this->json($response, ['session' => $session]);
    }

    public function validateSession(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isInternalConsoleRequest($request)) {
            return $this->json($response, ['error' => 'Forbidden'], 403);
        }

        $token = trim($request->getHeaderLine('X-Console-Token'));
        if ($token === '') {
            $params = $request->getQueryParams();
            $token = trim((string) ($params['token'] ?? ''));
        }

        $payload = $this->consoleSessionService->verifyToken($token, consume: false);
        if ($payload === null) {
            return $this->json($response, ['valid' => false], 401);
        }

        return $this->json($response, ['valid' => true, 'session' => $payload]);
    }

    private function isAdmin(ServerRequestInterface $request): bool
    {
        $user = (array) $request->getAttribute('user');
        return !empty($user['admin']);
    }

    private function isInternalConsoleRequest(ServerRequestInterface $request): bool
    {
        $expected = (string) ($_ENV['CONSOLE_INTERNAL_SECRET'] ?? '');
        if ($expected === '') {
            $expected = (string) ($_ENV['CONSOLE_SESSION_SECRET'] ?? $_ENV['JWT_SECRET'] ?? 'dev-console-secret');
        }

        $provided = $request->getHeaderLine('X-Console-Internal-Secret');
        return $provided !== '' && hash_equals($expected, $provided);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
