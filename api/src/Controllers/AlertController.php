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
        $body = (array) ($request->getParsedBody() ?? []);
        $rules = $this->alertService->updateRules($body, $this->isAdmin($request));
        return $this->json($response, ['rules' => $rules]);
    }

    public function slackTemplateDefaults(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, $this->alertService->getSlackTemplateDefaults());
    }

    public function previewSlackTemplate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (!$this->isAdmin($request)) {
            return $this->json($response, ['error' => 'Admin access required'], 403);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $preview = $this->alertService->previewSlackTemplate([
            'header' => $body['slack_header'] ?? $body['header'] ?? null,
            'body' => $body['slack_body_template'] ?? $body['body'] ?? null,
            'footer' => $body['slack_footer_template'] ?? $body['footer'] ?? null,
        ]);

        return $this->json($response, ['preview' => $preview]);
    }

    public function webhooks(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, ['webhooks' => $this->alertService->listWebhooks()]);
    }

    public function createWebhook(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        try {
            $webhook = $this->alertService->createWebhook($body);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 422);
        }
        return $this->json($response, ['webhook' => $webhook], 201);
    }

    public function updateWebhook(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $webhook = $this->alertService->updateWebhook((int) $args['id'], (array) ($request->getParsedBody() ?? []));
        if ($webhook === null) {
            return $this->json($response, ['error' => 'Webhook not found'], 404);
        }
        return $this->json($response, ['webhook' => $webhook]);
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

    private function isAdmin(ServerRequestInterface $request): bool
    {
        $user = (array) $request->getAttribute('user');
        return !empty($user['admin']);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
