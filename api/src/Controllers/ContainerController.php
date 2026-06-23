<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\AlertService;
use PatchDashboard\Api\Services\ContainerScanService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ContainerController
{
    public function __construct(private ContainerScanService $containerScanService)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $serverId = $request->getQueryParams()['server_id'] ?? null;
        return $this->json($response, [
            'containers' => $this->containerScanService->list($serverId !== null ? (int) $serverId : null),
        ]);
    }

    public function vulnerabilities(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $severity = $request->getQueryParams()['severity'] ?? null;
        return $this->json($response, [
            'vulnerabilities' => $this->containerScanService->listVulnerabilities(
                is_string($severity) ? $severity : null
            ),
        ]);
    }

    public function vulnerabilitySummary(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, $this->containerScanService->summary());
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
