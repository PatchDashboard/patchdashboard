<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\ContainerScanService;
use PatchDashboard\Api\Services\PatchService;
use PatchDashboard\Api\Services\PatchVulnerabilityService;
use PatchDashboard\Api\Services\ServerService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AgentController
{
    public function __construct(
        private ServerService $serverService,
        private PatchService $patchService,
        private PatchVulnerabilityService $patchVulnerabilityService,
        private ContainerScanService $containerScanService
    ) {
    }

    public function checkIn(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $agent = (array) $request->getAttribute('agent');
        $clientKey = (string) $agent['client_key'];
        $clientHost = $request->getHeaderLine('X-CLIENT-HOST');
        $clientOs = $request->getHeaderLine('X-CLIENT-OS');
        $clientOsVer = $request->getHeaderLine('X-CLIENT-OS-VER');
        $body = (array) ($request->getParsedBody() ?? []);

        if (empty($agent['registered'])) {
            $this->serverService->registerFromAgent(
                $clientKey,
                $clientHost !== '' ? $clientHost : 'UNKNOWN SERVER',
                $clientOs,
                $clientOsVer,
                $request->getServerParams()['REMOTE_ADDR'] ?? '',
                $body['capabilities'] ?? null
            );

            return $this->legacyResponse($response, false, '', false, false);
        }

        if (empty($agent['trusted'])) {
            return $this->legacyResponse($response, false, '', false, false);
        }

        $this->serverService->touchLastSeen($clientKey);
        $checkPatches = $this->serverService->shouldCheckPatches($clientKey);
        $checkCveScan = $this->serverService->shouldRunCveScan($clientKey);
        $installKey = $this->serverService->getInstallKey();

        return $this->legacyResponse($response, true, $installKey, $checkPatches, $checkCveScan);
    }

    public function submitPatches(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $agent = (array) $request->getAttribute('agent');
        if (empty($agent['registered']) || empty($agent['trusted'])) {
            return $this->json($response, ['error' => 'Unauthorized agent'], 403);
        }

        $server = $agent['server'];
        $raw = (string) $request->getBody();
        $lines = preg_split('/\R/', $raw) ?: [];

        $this->patchService->replacePatches(
            (string) $server['server_name'],
            $lines,
            $this->patchVulnerabilityService
        );
        return $this->json($response, ['status' => 'ok']);
    }

    public function submitPackages(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $agent = (array) $request->getAttribute('agent');
        if (empty($agent['registered']) || empty($agent['trusted'])) {
            return $this->json($response, ['error' => 'Unauthorized agent'], 403);
        }

        $server = $agent['server'];
        $raw = (string) $request->getBody();
        $lines = preg_split('/\R/', $raw) ?: [];

        $this->patchService->replacePackages((string) $server['server_name'], $lines);
        return $this->json($response, ['status' => 'ok']);
    }

    public function submitContainerScan(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $agent = (array) $request->getAttribute('agent');
        if (empty($agent['registered']) || empty($agent['trusted'])) {
            return $this->json($response, ['error' => 'Unauthorized agent'], 403);
        }

        $payload = (array) ($request->getParsedBody() ?? []);
        $result = $this->containerScanService->ingest((int) $agent['server']['id'], $payload);
        return $this->json($response, $result);
    }

    public function getCommands(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $agent = (array) $request->getAttribute('agent');
        if (empty($agent['registered']) || empty($agent['trusted'])) {
            return $this->json($response, ['commands' => []]);
        }

        $serverName = (string) $agent['server']['server_name'];
        $pdo = \PatchDashboard\Api\Config\Database::connect();
        $stmt = $pdo->prepare(
            'SELECT package_name FROM patches WHERE server_name = :server_name AND to_upgrade = 1 AND upgraded = 0'
        );
        $stmt->execute(['server_name' => $serverName]);
        $packages = array_column($stmt->fetchAll(), 'package_name');

        return $this->json($response, ['commands' => ['upgrade_packages' => $packages]]);
    }

    private function legacyResponse(
        ResponseInterface $response,
        bool $allowed,
        string $installKey,
        bool $checkPatches,
        bool $checkCveScan = false
    ): ResponseInterface {
        $body = sprintf(
            "allowed='%s'\nkey_to_check='%s'\ncheck_patches='%s'\ncheck_cve_scan='%s'",
            $allowed ? 'TRUE' : 'FALSE',
            $allowed ? $installKey : 'FALSE',
            $checkPatches ? 'TRUE' : 'FALSE',
            $checkCveScan ? 'TRUE' : 'FALSE'
        );
        $response->getBody()->write($body);
        return $response->withHeader('Content-Type', 'text/plain');
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
