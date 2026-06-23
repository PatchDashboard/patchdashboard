<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\AuthService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AuthController
{
    public function __construct(private AuthService $authService)
    {
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $username = trim((string) ($body['username'] ?? ''));
        $password = (string) ($body['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->json($response, ['error' => 'Username and password are required'], 422);
        }

        $result = $this->authService->login($username, $password);
        if ($result === null) {
            return $this->json($response, ['error' => 'Invalid username or password'], 401);
        }

        return $this->json($response, $result);
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $jwtUser = (array) $request->getAttribute('user');
        $user = $this->authService->getUserById((int) $jwtUser['sub']);
        if ($user === null) {
            return $this->json($response, ['error' => 'User not found'], 404);
        }
        return $this->json($response, ['user' => $user]);
    }

    public function updateMe(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $jwtUser = (array) $request->getAttribute('user');
        $body = (array) ($request->getParsedBody() ?? []);

        if (isset($body['confirm_password'], $body['password']) && $body['password'] !== $body['confirm_password']) {
            return $this->json($response, ['error' => 'Passwords do not match'], 422);
        }

        unset($body['confirm_password'], $body['admin'], $body['active'], $body['user_id']);

        $user = $this->authService->updateProfile((int) $jwtUser['sub'], $body);
        if ($user === null) {
            return $this->json($response, ['error' => 'User not found'], 404);
        }
        return $this->json($response, ['user' => $user]);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
