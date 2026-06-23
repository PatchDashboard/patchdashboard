<?php

declare(strict_types=1);

use PatchDashboard\Api\Controllers\AgentController;
use PatchDashboard\Api\Controllers\AlertController;
use PatchDashboard\Api\Controllers\AuthController;
use PatchDashboard\Api\Controllers\ContainerController;
use PatchDashboard\Api\Controllers\ConsoleController;
use PatchDashboard\Api\Controllers\CveFeedController;
use PatchDashboard\Api\Controllers\CveController;
use PatchDashboard\Api\Controllers\DistroController;
use PatchDashboard\Api\Controllers\EnrollmentSshController;
use PatchDashboard\Api\Controllers\HealthController;
use PatchDashboard\Api\Controllers\MfaController;
use PatchDashboard\Api\Controllers\PatchController;
use PatchDashboard\Api\Controllers\PluginController;
use PatchDashboard\Api\Controllers\ServerController;
use PatchDashboard\Api\Controllers\SetupController;
use PatchDashboard\Api\Controllers\SuppressionController;
use PatchDashboard\Api\Controllers\UserController;
use PatchDashboard\Api\Controllers\VulnerabilityController;
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
        $group->post('/auth/mfa/verify', [MfaController::class, 'verifyLogin']);
        $group->get('/auth/me', [AuthController::class, 'me'])->add(AuthMiddleware::class);
        $group->patch('/auth/me', [AuthController::class, 'updateMe'])->add(AuthMiddleware::class);
        $group->post('/auth/mfa/setup', [MfaController::class, 'setup'])->add(AuthMiddleware::class);
        $group->post('/auth/mfa/confirm', [MfaController::class, 'confirm'])->add(AuthMiddleware::class);
        $group->post('/auth/mfa/disable', [MfaController::class, 'disable'])->add(AuthMiddleware::class);
        $group->get('/console/sessions/validate', [ConsoleController::class, 'validateSession']);

        $group->group('', function (RouteCollectorProxy $auth): void {
            $auth->get('/dashboard', [PatchController::class, 'dashboard']);
            $auth->get('/servers/onboarding', [ServerController::class, 'onboarding']);
            $auth->get('/settings/enrollment-ssh-key', [EnrollmentSshController::class, 'show']);
            $auth->put('/settings/enrollment-ssh-key', [EnrollmentSshController::class, 'update']);
            $auth->delete('/settings/enrollment-ssh-key', [EnrollmentSshController::class, 'delete']);
            $auth->get('/servers', [ServerController::class, 'index']);
            $auth->get('/servers/{id}', [ServerController::class, 'show']);
            $auth->patch('/servers/{id}', [ServerController::class, 'update']);
            $auth->post('/servers/{id}/console/session', [ConsoleController::class, 'createSession']);
            $auth->post('/servers/enroll', [ServerController::class, 'enroll']);
            $auth->post('/servers/{id}/activate', [ServerController::class, 'activate']);
            $auth->post('/servers/{id}/deactivate', [ServerController::class, 'deactivate']);
            $auth->post('/servers/{id}/untrust', [ServerController::class, 'deactivate']);
            $auth->post('/servers/{id}/reboot', [ServerController::class, 'reboot']);
            $auth->post('/servers/{id}/shutdown', [ServerController::class, 'shutdown']);
            $auth->delete('/servers/{id}', [ServerController::class, 'delete']);
            $auth->get('/servers/{id}/patches', [PatchController::class, 'forServer']);
            $auth->post('/servers/{id}/patches/enrich-vulnerabilities', [PatchController::class, 'enrichVulnerabilities']);
            $auth->post('/servers/{id}/patches/install', [PatchController::class, 'installAll']);
            $auth->get('/servers/{id}/packages', [PatchController::class, 'packagesForServer']);
            $auth->get('/servers/{id}/workloads', [ServerController::class, 'workloads']);
            $auth->get('/search/packages', [PatchController::class, 'searchPackages']);

            $auth->get('/suppressions', [SuppressionController::class, 'index']);
            $auth->post('/suppressions', [SuppressionController::class, 'create']);
            $auth->delete('/suppressions/{id}', [SuppressionController::class, 'delete']);

            $auth->get('/distros', [DistroController::class, 'index']);

            $auth->get('/users', [UserController::class, 'index']);
            $auth->post('/users', [UserController::class, 'create']);
            $auth->patch('/users/{id}', [UserController::class, 'update']);
            $auth->delete('/users/{id}', [UserController::class, 'delete']);

            $auth->get('/plugins', [PluginController::class, 'index']);
            $auth->patch('/plugins/{id}', [PluginController::class, 'updatePlugin']);
            $auth->patch('/plugins/pages/{id}', [PluginController::class, 'updatePage']);

            $auth->get('/containers', [ContainerController::class, 'index']);
            $auth->get('/containers/vulnerabilities', [ContainerController::class, 'vulnerabilities']);
            $auth->get('/containers/vulnerabilities/summary', [ContainerController::class, 'vulnerabilitySummary']);
            $auth->get('/vulnerabilities/search', [VulnerabilityController::class, 'search']);
            $auth->get('/vulnerabilities/{cveId}', [VulnerabilityController::class, 'detail']);
            $auth->post('/cve/refresh-all', [CveController::class, 'refreshAll']);
            $auth->post('/servers/{id}/cve/refresh', [CveController::class, 'refreshServer']);

            $auth->get('/cve-feeds/providers', [CveFeedController::class, 'providers']);
            $auth->get('/cve-feeds', [CveFeedController::class, 'index']);
            $auth->post('/cve-feeds', [CveFeedController::class, 'create']);
            $auth->get('/cve-feeds/lookup', [CveFeedController::class, 'lookup']);
            $auth->get('/cve-catalog/{cveId}', [CveFeedController::class, 'catalog']);
            $auth->put('/cve-feeds/{id}', [CveFeedController::class, 'update']);
            $auth->post('/cve-feeds/{id}/sync', [CveFeedController::class, 'sync']);
            $auth->post('/cve-feeds/sync-all', [CveFeedController::class, 'syncAll']);
            $auth->get('/cve-feeds/{id}/logs', [CveFeedController::class, 'logs']);

            $auth->get('/alerts/rules', [AlertController::class, 'rules']);
            $auth->put('/alerts/rules', [AlertController::class, 'updateRules']);
            $auth->get('/alerts/slack/template-defaults', [AlertController::class, 'slackTemplateDefaults']);
            $auth->post('/alerts/slack/preview', [AlertController::class, 'previewSlackTemplate']);
            $auth->get('/alerts/webhooks', [AlertController::class, 'webhooks']);
            $auth->post('/alerts/webhooks', [AlertController::class, 'createWebhook']);
            $auth->patch('/alerts/webhooks/{id}', [AlertController::class, 'updateWebhook']);
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
