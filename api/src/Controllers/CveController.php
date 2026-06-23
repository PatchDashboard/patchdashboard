<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\ServerService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CveController
{
    public function __construct(private ServerService $serverService)
    {
    }

    public function refreshAll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $count = $this->serverService->forceCveScanForAllTrusted();
        return $this->json($response, [
            'queued' => $count,
            'message' => "Queued full CVE refresh for {$count} servers. Agents will scan on next check-in or daily cron.",
        ]);
    }

    public function refreshServer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $serverId = (int) $args['id'];
        $server = $this->serverService->findById($serverId);
        if ($server === null) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }

        $this->serverService->forceCveScanForServer($serverId);
        return $this->json($response, [
            'status' => 'queued',
            'message' => 'Server queued for full CVE scan on next agent check-in.',
        ]);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
