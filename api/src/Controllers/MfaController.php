<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\AuthService;
use PatchDashboard\Api\Services\MfaService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MfaController
{
    public function __construct(
        private AuthService $authService,
        private MfaService $mfaService,
    ) {
    }

    public function verifyLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $mfaToken = trim((string) ($body['mfa_token'] ?? ''));
        $code = trim((string) ($body['code'] ?? ''));

        if ($mfaToken === '' || $code === '') {
            return $this->json($response, ['error' => 'MFA token and code are required'], 422);
        }

        $result = $this->authService->verifyMfaLogin($mfaToken, $code);
        if ($result === null) {
            return $this->json($response, ['error' => 'Invalid or expired authentication code'], 401);
        }

        return $this->json($response, $result);
    }

    public function setup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $jwtUser = (array) $request->getAttribute('user');
        $userId = (int) ($jwtUser['sub'] ?? 0);
        $accountName = trim((string) ($jwtUser['user_id'] ?? ''));

        if ($userId <= 0 || $accountName === '') {
            return $this->json($response, ['error' => 'User not found'], 404);
        }

        try {
            $setup = $this->mfaService->beginSetup($userId, $accountName);
            return $this->json($response, ['setup' => $setup]);
        } catch (\RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 409);
        }
    }

    public function confirm(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $jwtUser = (array) $request->getAttribute('user');
        $userId = (int) ($jwtUser['sub'] ?? 0);
        $body = (array) ($request->getParsedBody() ?? []);
        $code = trim((string) ($body['code'] ?? ''));

        if ($code === '') {
            return $this->json($response, ['error' => 'Authentication code is required'], 422);
        }

        try {
            $this->mfaService->confirmSetup($userId, $code);
            $user = $this->authService->getUserById($userId);
            return $this->json($response, ['user' => $user]);
        } catch (\RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }
    }

    public function disable(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $jwtUser = (array) $request->getAttribute('user');
        $userId = (int) ($jwtUser['sub'] ?? 0);
        $body = (array) ($request->getParsedBody() ?? []);
        $password = (string) ($body['password'] ?? '');

        if ($password === '') {
            return $this->json($response, ['error' => 'Password is required to disable MFA'], 422);
        }

        if (!$this->authService->verifyUserPassword($userId, $password)) {
            return $this->json($response, ['error' => 'Invalid password'], 401);
        }

        $this->mfaService->disable($userId);
        $user = $this->authService->getUserById($userId);

        return $this->json($response, ['user' => $user]);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
