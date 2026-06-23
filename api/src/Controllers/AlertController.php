<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\AlertService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AlertController
{
    public function __construct(private AlertService $alertService)
    {
    }

    public function rules(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, ['rules' => $this->alertService->getRules()]);
    }

    public function updateRules(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $rules = $this->alertService->updateRules((array) ($request->getParsedBody() ?? []));
        return $this->json($response, ['rules' => $rules]);
    }

    public function webhooks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, ['webhooks' => $this->alertService->listWebhooks()]);
    }

    public function createWebhook(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        if (empty($body['name']) || empty($body['url'])) {
            return $this->json($response, ['error' => 'name and url are required'], 422);
        }
        $webhook = $this->alertService->createWebhook($body);
        return $this->json($response, ['webhook' => $webhook], 201);
    }

    public function deleteWebhook(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        if (!$this->alertService->deleteWebhook((int) $args['id'])) {
            return $this->json($response, ['error' => 'Webhook not found'], 404);
        }
        return $this->json($response, ['status' => 'deleted']);
    }

    public function testWebhook(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return $this->json($response, $this->alertService->testWebhook((int) $args['id']));
    }

    public function events(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, ['events' => $this->alertService->listEvents()]);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
