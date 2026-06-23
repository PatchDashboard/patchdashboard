<?php

declare(strict_types=1);

use PatchDashboard\Api\Controllers\AgentController;
use PatchDashboard\Api\Controllers\AlertController;
use PatchDashboard\Api\Controllers\AuthController;
use PatchDashboard\Api\Controllers\ContainerController;
use PatchDashboard\Api\Controllers\CveController;
use PatchDashboard\Api\Controllers\DistroController;
use PatchDashboard\Api\Controllers\HealthController;
use PatchDashboard\Api\Controllers\PatchController;
use PatchDashboard\Api\Controllers\ServerController;
use PatchDashboard\Api\Controllers\SetupController;
use PatchDashboard\Api\Controllers\SuppressionController;
use PatchDashboard\Api\Controllers\UserController;
use PatchDashboard\Api\Middleware\AuthMiddleware;
use PatchDashboard\Api\Middleware\AgentAuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    $app->get('/health', [HealthController::class, 'health']);

    $app->group('/api/v1', function (RouteCollectorProxy $group): void {
        $group->get('/setup/status', [SetupController::class, 'status']);
        $group->post('/setup/verify-token', [SetupController::class, 'verifyToken']);
        $group->post('/setup/complete', [SetupController::class, 'complete']);

        $group->post('/auth/login', [AuthController::class, 'login']);
        $group->get('/auth/me', [AuthController::class, 'me'])->add(AuthMiddleware::class);

        $group->group('', function (RouteCollectorProxy $auth): void {
            $auth->get('/dashboard', [PatchController::class, 'dashboard']);
            $auth->get('/servers', [ServerController::class, 'index']);
            $auth->get('/servers/{id}', [ServerController::class, 'show']);
            $auth->patch('/servers/{id}', [ServerController::class, 'update']);
            $auth->post('/servers/{id}/activate', [ServerController::class, 'activate']);
            $auth->post('/servers/{id}/deactivate', [ServerController::class, 'deactivate']);
            $auth->delete('/servers/{id}', [ServerController::class, 'delete']);
            $auth->get('/servers/{id}/patches', [PatchController::class, 'forServer']);
            $auth->post('/servers/{id}/patches/enrich-vulnerabilities', [PatchController::class, 'enrichVulnerabilities']);
            $auth->post('/servers/{id}/patches/install', [PatchController::class, 'installAll']);
            $auth->get('/servers/{id}/packages', [PatchController::class, 'packagesForServer']);
            $auth->get('/search/packages', [PatchController::class, 'searchPackages']);

            $auth->get('/suppressions', [SuppressionController::class, 'index']);
            $auth->post('/suppressions', [SuppressionController::class, 'create']);
            $auth->delete('/suppressions/{id}', [SuppressionController::class, 'delete']);

            $auth->get('/distros', [DistroController::class, 'index']);

            $auth->get('/users', [UserController::class, 'index']);
            $auth->post('/users', [UserController::class, 'create']);
            $auth->patch('/users/{id}', [UserController::class, 'update']);
            $auth->delete('/users/{id}', [UserController::class, 'delete']);

            $auth->get('/containers', [ContainerController::class, 'index']);
            $auth->get('/containers/vulnerabilities', [ContainerController::class, 'vulnerabilities']);
            $auth->get('/containers/vulnerabilities/summary', [ContainerController::class, 'vulnerabilitySummary']);
            $auth->post('/cve/refresh-all', [CveController::class, 'refreshAll']);
            $auth->post('/servers/{id}/cve/refresh', [CveController::class, 'refreshServer']);

            $auth->get('/alerts/rules', [AlertController::class, 'rules']);
            $auth->put('/alerts/rules', [AlertController::class, 'updateRules']);
            $auth->get('/alerts/webhooks', [AlertController::class, 'webhooks']);
            $auth->post('/alerts/webhooks', [AlertController::class, 'createWebhook']);
            $auth->delete('/alerts/webhooks/{id}', [AlertController::class, 'deleteWebhook']);
            $auth->post('/alerts/webhooks/{id}/test', [AlertController::class, 'testWebhook']);
            $auth->get('/alerts/events', [AlertController::class, 'events']);
        })->add(AuthMiddleware::class);

        $group->group('/agent', function (RouteCollectorProxy $agent): void {
            $agent->post('/check-in', [AgentController::class, 'checkIn']);
            $agent->post('/patches', [AgentController::class, 'submitPatches']);
            $agent->post('/packages', [AgentController::class, 'submitPackages']);
            $agent->post('/containers/scan', [AgentController::class, 'submitContainerScan']);
            $agent->get('/commands', [AgentController::class, 'getCommands']);
        })->add(AgentAuthMiddleware::class);
    });
};
