<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\ContainerScanService;
use PatchDashboard\Api\Services\PatchService;
use PatchDashboard\Api\Services\PatchVulnerabilityService;
use PatchDashboard\Api\Services\ServerService;
use PatchDashboard\Api\Services\SuppressionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PatchController
{
    public function __construct(
        private PatchService $patchService,
        private SuppressionService $suppressionService,
        private PatchVulnerabilityService $patchVulnerabilityService,
        private ServerService $serverService,
        private ContainerScanService $containerScanService,
    ) {
    }

    public function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = $this->patchService->dashboard($this->suppressionService);
        $nested = $this->containerScanService->nestedContainersByServer();

        $servers = [];
        foreach ($data['servers'] as $row) {
            $server = $this->serverService->findById((int) $row['server_id']);
            if ($server !== null && $this->serverService->isContainerHost($server)) {
                continue;
            }

            $row['containers'] = $nested[(int) $row['server_id']] ?? [];
            $servers[] = $row;
        }

        $data['servers'] = $servers;
        $data['server_count'] = count($servers);

        return $this->json($response, $data);
    }

    public function forServer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $serverId = (int) $args['id'];
        $pdo = \PatchDashboard\Api\Config\Database::connect();
        $stmt = $pdo->prepare(
            'SELECT s.*, d.distro_name, d.upgrade_command, dv.version_num, dv.codename
             FROM servers s
             LEFT JOIN distro d ON d.id = s.distro_id
             LEFT JOIN distro_version dv ON dv.id = s.distro_version
             WHERE s.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $serverId]);
        $serverRow = $stmt->fetch();
        if (!$serverRow) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }

        $server = (string) $serverRow['server_name'];
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

        $installable = array_column(
            array_filter($patches, fn (array $p) => empty($p['suppressed'])),
            'package_name'
        );

        return $this->json($response, [
            'server' => [
                'id' => (int) $serverRow['id'],
                'server_name' => $serverRow['server_name'],
                'server_alias' => $serverRow['server_alias'],
                'is_container' => $this->serverService->isContainerHost($serverRow),
            ],
            'patches' => $patches,
            'distro' => $context,
            'upgrade_command' => $this->patchService->buildUpgradeCommand($server, $installable),
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
        $body = (array) ($request->getParsedBody() ?? []);
        $reboot = !empty($body['reboot']);
        $rebootMode = (string) ($body['reboot_mode'] ?? 'graceful');
        if ($reboot && !in_array($rebootMode, ['graceful', 'forced'], true)) {
            $rebootMode = 'graceful';
        }
        $serverId = (int) $args['id'];
        $server = $this->serverService->findById($serverId);
        if ($server === null) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }
        if ($this->serverService->isContainerHost($server)) {
            return $this->json($response, [
                'error' => 'Patch install is disabled for container workloads. Untrust to remove this node.',
            ], 403);
        }

        $updated = $this->patchService->installAll($serverId, $this->suppressionService);
        $rebootScheduled = false;
        if ($reboot && $updated > 0) {
            $rebootScheduled = $this->serverService->schedulePowerCommand($serverId, 'reboot', $rebootMode, null);
        }
        return $this->json($response, [
            'updated' => $updated,
            'reboot_scheduled' => $rebootScheduled,
            'reboot_mode' => $rebootScheduled ? $rebootMode : null,
        ]);
    }

    public function packagesForServer(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $pdo = \PatchDashboard\Api\Config\Database::connect();
        $stmt = $pdo->prepare('SELECT id, server_name, server_alias FROM servers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $args['id']]);
        $serverRow = $stmt->fetch();
        if (!$serverRow) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }

        $server = (string) $serverRow['server_name'];
        return $this->json($response, [
            'server' => [
                'id' => (int) $serverRow['id'],
                'server_name' => $serverRow['server_name'],
                'server_alias' => $serverRow['server_alias'],
            ],
            'packages' => $this->patchService->packagesForServer($server),
        ]);
    }

    public function searchPackages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $query = trim((string) ($params['q'] ?? ''));
        $exact = filter_var($params['exact'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($query === '') {
            return $this->json($response, ['results' => [], 'query' => '']);
        }

        return $this->json($response, [
            'query' => $query,
            'exact' => $exact,
            'results' => $this->patchService->searchPackages($query, $exact),
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
