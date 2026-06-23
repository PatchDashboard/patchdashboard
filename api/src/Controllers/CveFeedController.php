<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\CveFeedService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class CveFeedController
{
    public function __construct(private CveFeedService $cveFeedService)
    {
    }

    public function providers(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, ['providers' => $this->cveFeedService->listProviders()]);
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, ['distros' => $this->cveFeedService->listFeedsGroupedByDistro()]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $providerId = trim((string) ($body['provider_id'] ?? ''));
        $distroId = array_key_exists('distro_id', $body) && $body['distro_id'] !== null
            ? (int) $body['distro_id']
            : null;

        try {
            $feed = $this->cveFeedService->createFeed($distroId, $providerId);
            return $this->json($response, ['feed' => $feed], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 422);
        } catch (\RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 409);
        }
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }

        $feedId = (int) $args['id'];
        $body = (array) ($request->getParsedBody() ?? []);
        $feed = $this->cveFeedService->updateFeed($feedId, $body);
        if ($feed === null) {
            return $this->json($response, ['error' => 'Feed not found'], 404);
        }

        return $this->json($response, ['feed' => $feed]);
    }

    public function sync(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }

        $feedId = (int) $args['id'];
        try {
            $result = $this->cveFeedService->syncFeed($feedId);
            return $this->json($response, $result);
        } catch (\InvalidArgumentException) {
            return $this->json($response, ['error' => 'Feed not found'], 404);
        } catch (\RuntimeException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        } catch (\Throwable $e) {
            return $this->json($response, ['error' => $e->getMessage()], 500);
        }
    }

    public function syncAll(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }

        $results = $this->cveFeedService->syncAllEnabled();
        return $this->json($response, ['results' => $results]);
    }

    public function logs(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $feedId = (int) $args['id'];
        $params = $request->getQueryParams();
        $limit = isset($params['limit']) ? (int) $params['limit'] : 20;

        return $this->json($response, [
            'logs' => $this->cveFeedService->listSyncLogs($feedId, $limit),
        ]);
    }

    public function lookup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $distroId = isset($params['distro_id']) ? (int) $params['distro_id'] : null;
        $package = isset($params['package']) ? (string) $params['package'] : null;
        $cveId = isset($params['cve_id']) ? (string) $params['cve_id'] : null;

        if ($distroId !== null && $package !== null && $package !== '') {
            return $this->json($response, [
                'match' => $this->cveFeedService->matchPackage($distroId, $package),
            ]);
        }

        return $this->json($response, [
            'advisories' => $this->cveFeedService->searchAdvisories($distroId, $package, $cveId),
        ]);
    }

    public function catalog(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $cveId = strtoupper((string) $args['cveId']);
        $entry = $this->cveFeedService->getCatalogEntry($cveId);
        if ($entry === null) {
            return $this->json($response, ['error' => 'CVE not found in local catalog'], 404);
        }

        return $this->json($response, ['cve' => $entry]);
    }

    private function isAdmin(ServerRequestInterface $request): bool
    {
        $user = $request->getAttribute('user');
        return is_array($user) && !empty($user['admin']);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
