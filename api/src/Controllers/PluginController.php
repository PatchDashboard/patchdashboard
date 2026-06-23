<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\PluginService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class PluginController
{
    public function __construct(private PluginService $pluginService)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }
        return $this->json($response, [
            'plugins' => $this->pluginService->listPlugins(),
            'pages' => $this->pluginService->listPages(),
        ]);
    }

    public function updatePlugin(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }
        $plugin = $this->pluginService->updatePlugin((int) $args['id'], (array) ($request->getParsedBody() ?? []));
        if ($plugin === null) {
            return $this->json($response, ['error' => 'Plugin not found'], 404);
        }
        return $this->json($response, ['plugin' => $plugin]);
    }

    public function updatePage(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }
        $page = $this->pluginService->updatePage((int) $args['id'], (array) ($request->getParsedBody() ?? []));
        if ($page === null) {
            return $this->json($response, ['error' => 'Page not found'], 404);
        }
        return $this->json($response, ['page' => $page]);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }

    private function isAdmin(ServerRequestInterface $request): bool
    {
        $user = (array) $request->getAttribute('user');
        return !empty($user['admin']);
    }
}
