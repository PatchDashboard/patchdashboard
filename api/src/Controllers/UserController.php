<?php

declare(strict_types=1);

namespace PatchDashboard\Api\Controllers;

use PatchDashboard\Api\Services\UserService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class UserController
{
    public function __construct(private UserService $userService)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->json($response, ['users' => $this->userService->list()]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = (array) ($request->getParsedBody() ?? []);
        if (empty($body['user_id']) || empty($body['email']) || empty($body['password'])) {
            return $this->json($response, ['error' => 'user_id, email, and password are required'], 422);
        }

        $user = $this->userService->create($body);
        return $this->json($response, ['user' => $user], 201);
    }

    public function update(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $user = $this->userService->update((int) $args['id'], (array) ($request->getParsedBody() ?? []));
        if ($user === null) {
            return $this->json($response, ['error' => 'User not found'], 404);
        }
        return $this->json($response, ['user' => $user]);
    }

    public function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->userService->delete((int) $args['id']);
        return $this->json($response, ['status' => 'deleted']);
    }

    private function json(ResponseInterface $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
