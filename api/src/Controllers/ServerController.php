<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\ServerService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ServerController
{
    public function __construct(private ServerService $serverService)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, ['servers' => $this->serverService->listAll()]);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $server = $this->serverService->findById((int) $args['id']);
        if ($server === null) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }
        return $this->json($response, ['server' => $server]);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $server = $this->serverService->update((int) $args['id'], $body);
        if ($server === null) {
            return $this->json($response, ['error' => 'Server not found'], 404);
        }
        return $this->json($response, ['server' => $server]);
    }

    public function activate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->serverService->setTrusted((int) $args['id'], true);
        return $this->json($response, ['status' => 'activated']);
    }

    public function deactivate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->serverService->setTrusted((int) $args['id'], false);
        return $this->json($response, ['status' => 'deactivated']);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->serverService->delete((int) $args['id']);
        return $this->json($response, ['status' => 'deleted']);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
