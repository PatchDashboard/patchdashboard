<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\InstallService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SetupController
{
    public function __construct(private InstallService $installService)
    {
    }

    public function status(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, $this->installService->status());
    }

    public function verifyToken(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->installService->isInstalled()) {
            return $this->json($response, ['error' => 'Already installed'], 404);
        }

        if (!$this->installService->isWebSetupPending()) {
            return $this->json($response, ['error' => 'Web setup is not available'], 404);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $token = trim((string) ($body['token'] ?? ''));

        if (!$this->installService->validateToken($token)) {
            return $this->json($response, ['valid' => false], 403);
        }

        return $this->json($response, ['valid' => true]);
    }

    public function complete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($this->installService->isInstalled()) {
            return $this->json($response, ['error' => 'Already installed'], 404);
        }

        if (!$this->installService->isWebSetupPending()) {
            return $this->json($response, ['error' => 'Web setup is not available'], 404);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $token = trim((string) ($body['token'] ?? ''));

        try {
            $result = $this->installService->completeSetup(
                $token,
                (string) ($body['adminUser'] ?? $body['admin_user'] ?? 'admin'),
                (string) ($body['adminEmail'] ?? $body['admin_email'] ?? ''),
                (string) ($body['adminPass'] ?? $body['admin_pass'] ?? ''),
                (string) ($body['company'] ?? ''),
            );

            return $this->json($response, [
                'ok' => true,
                'installKey' => $result['installKey'],
                'dashboardPublicKey' => $result['dashboardPublicKey'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        }
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
