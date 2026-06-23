<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\PatchService;
use PatchDashboard\Api\Services\PatchVulnerabilityService;
use PatchDashboard\Api\Services\SuppressionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PatchController
{
    public function __construct(
        private PatchService $patchService,
        private SuppressionService $suppressionService,
        private PatchVulnerabilityService $patchVulnerabilityService
    ) {
    }

    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, $this->patchService->dashboard($this->suppressionService));
    }

    public function forServer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $serverId = (int) $args['id'];
        $server = $this->getServerName($serverId);
        if ($server === null) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }

        $patches = $this->patchService->forServer($server, $this->suppressionService);
        $context = $this->patchService->getDistroContextForServer($server);

        $patches = array_map(function (array $patch) use ($context) {
            if ($context !== null) {
                $patch['reference_url'] = $this->patchVulnerabilityService->buildReferenceUrl(
                    (string) $context['distro_name'],
                    $context['codename'] !== null ? (string) $context['codename'] : null,
                    $context['version_num'] !== null ? (string) $context['version_num'] : null,
                    (string) $patch['package_name']
                );
            }
            return $patch;
        }, $patches);

        return $this->json($response, [
            'patches' => $patches,
            'distro' => $context,
        ]);
    }

    public function enrichVulnerabilities(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $serverId = (int) $args['id'];
        if ($this->getServerName($serverId) === null) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }

        $updated = $this->patchVulnerabilityService->enrichServerPatches($serverId);
        return $this->json($response, [
            'updated' => $updated,
            'message' => "Refreshed vulnerability data for {$updated} patches.",
        ]);
    }

    public function installAll(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $updated = $this->patchService->installAll((int) $args['id'], $this->suppressionService);
        return $this->json($response, ['updated' => $updated]);
    }

    public function packagesForServer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $server = $this->getServerName((int) $args['id']);
        if ($server === null) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }

        return $this->json($response, [
            'packages' => $this->patchService->packagesForServer($server),
        ]);
    }

    public function searchPackages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = trim((string) ($request->getQueryParams()['q'] ?? ''));
        if ($query === '') {
            return $this->json($response, ['results' => []]);
        }

        return $this->json($response, [
            'results' => $this->patchService->searchPackages($query),
        ]);
    }

    private function getServerName(int $id): ?string
    {
        $pdo = \PatchDashboard\Api\Config\Database::connect();
        $stmt = $pdo->prepare('SELECT server_name FROM servers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ? (string) $row['server_name'] : null;
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
