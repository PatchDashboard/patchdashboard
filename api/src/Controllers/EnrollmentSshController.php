<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\SshKeyService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class EnrollmentSshController
{
    public function __construct(private SshKeyService $sshKeyService)
    {
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }

        $dashboard = $this->safeDashboardKeys();
        $bootstrap = $this->sshKeyService->getBootstrapKeyPair();

        return $this->json($response, [
            'dashboard' => $dashboard,
            'bootstrap' => $bootstrap !== null
                ? [
                    'configured' => true,
                    'public_key' => $bootstrap['public_key'],
                    'fingerprint' => $bootstrap['fingerprint'],
                ]
                : ['configured' => false],
        ]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $privateKey = trim((string) ($body['private_key'] ?? ''));
        $publicKey = trim((string) ($body['public_key'] ?? ''));

        if ($privateKey === '') {
            return $this->json($response, ['error' => 'private_key is required'], 422);
        }

        try {
            $stored = $this->sshKeyService->setBootstrapKeyPair(
                $privateKey,
                $publicKey !== '' ? $publicKey : null,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => 'Unable to store SSH key: ' . $e->getMessage()], 500);
        }

        return $this->json($response, [
            'bootstrap' => [
                'configured' => true,
                'public_key' => $stored['public_key'],
                'fingerprint' => $stored['fingerprint'],
            ],
        ]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }

        $this->sshKeyService->clearBootstrapKey();

        return $this->json($response, ['status' => 'removed']);
    }

    /**
     * @return array{public_key: ?string, fingerprint: ?string}
     */
    private function safeDashboardKeys(): array
    {
        try {
            $keys = $this->sshKeyService->ensureKeyPair();

            return [
                'public_key' => $keys['public_key'],
                'fingerprint' => $keys['fingerprint'],
            ];
        } catch (\Throwable) {
            return [
                'public_key' => $this->sshKeyService->getPublicKey(),
                'fingerprint' => null,
            ];
        }
    }

    private function isAdmin(ServerRequestInterface $request): bool
    {
        $user = (array) $request->getAttribute('user');
        return !empty($user['admin']);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
