<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\ContainerScanService;
use PatchDashboard\Api\Services\VulnerabilitySearchService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ContainerController
{
    public function __construct(
        private ContainerScanService $containerScanService,
        private VulnerabilitySearchService $vulnerabilitySearchService,
    ) {
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
        $params = $request->getQueryParams();
        $filterKeys = ['q', 'cve', 'package', 'severity', 'server_id', 'runtime', 'distro_id'];
        $hasFilters = false;
        foreach ($filterKeys as $key) {
            if (isset($params[$key]) && $params[$key] !== '') {
                $hasFilters = true;
                break;
            }
        }

        if ($hasFilters) {
            $params['source'] = 'installed';
            $rows = $this->vulnerabilitySearchService->listInstalled($params);
            $vulnerabilities = array_values(array_filter($rows, fn (array $row) => ($row['kind'] ?? '') === 'trivy'));
            $vulnerabilities = array_map(function (array $row): array {
                return [
                    'cve_id' => $row['cve_id'],
                    'severity' => $row['severity'],
                    'package_name' => $row['package_name'],
                    'container_name' => $row['container_name'],
                    'server_alias' => $row['server_alias'],
                    'server_id' => $row['server_id'],
                    'runtime' => $row['runtime'],
                    'title' => $row['title'],
                    'installed_version' => $row['installed_version'],
                    'fixed_version' => $row['fixed_version'],
                ];
            }, $vulnerabilities);

            return $this->json($response, ['vulnerabilities' => $vulnerabilities]);
        }

        $severity = $params['severity'] ?? null;
        return $this->json($response, [
            'vulnerabilities' => $this->containerScanService->listVulnerabilities(
                is_string($severity) && $severity !== '' ? $severity : null
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
