<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\SuppressionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class SuppressionController
{
    public function __construct(private SuppressionService $suppressionService)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, ['suppressions' => $this->suppressionService->list()]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $package = trim((string) ($body['package_name'] ?? ''));
        $server = (string) ($body['server_name'] ?? '0');

        if ($package === '') {
            return $this->json($response, ['error' => 'package_name is required'], 422);
        }

        $created = $this->suppressionService->create($package, $server);
        return $this->json($response, ['suppression' => $created], 201);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $deleted = $this->suppressionService->delete((int) $args['id']);
        if (!$deleted) {
            return $this->json($response, ['error' => 'Suppression not found'], 404);
        }
        return $this->json($response, ['status' => 'deleted']);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
